#!/bin/bash
# WeathermapNG One-Click Installer
# Usage: ./install.sh

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}" >&2
}

warn() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

# Global variables
DOCKER_MODE=false
LIBRENMS_PATH=""
DOCKER_ROOT=false

# Detect Docker environment
detect_docker() {
    # Check for Docker environment variables
    if [[ -n "$DOCKER_CONTAINER" ]] || [[ -f "/.dockerenv" ]] || [[ -n "$LIBRENMS_DOCKER" ]]; then
        return 0  # True, we're in Docker
    fi

    # Check for common Docker indicators
    if [[ -d "/var/lib/docker" ]] || [[ -S "/var/run/docker.sock" ]]; then
        if [[ -n "$FORCE_DOCKER" ]]; then
            warn "🐳 Docker environment detected (forced)"
            return 0
        fi
    fi

    return 1  # False, not in Docker
}

# Detect LibreNMS installation path
detect_librenms_paths() {
    # Try common LibreNMS paths
    possible_paths=(
        "/opt/librenms"           # Standard installation
        "/app"                    # Some containers
        "/var/www/html"          # Web containers
        "/usr/share/librenms"    # Some distros
        "/data/librenms"         # Custom containers
    )

    for path in "${possible_paths[@]}"; do
        if [[ -f "$path/bootstrap/app.php" ]] || [[ -f "$path/librenms.php" ]] || [[ -f "$path/app.php" ]]; then
            LIBRENMS_PATH="$path"
            log "✅ Found LibreNMS at: $LIBRENMS_PATH"
            return 0
        fi
    done

    # Check environment variable
    if [[ -n "$LIBRENMS_PATH" ]]; then
        if [[ -f "$LIBRENMS_PATH/bootstrap/app.php" ]] || [[ -f "$LIBRENMS_PATH/librenms.php" ]]; then
            log "✅ Using LIBRENMS_PATH: $LIBRENMS_PATH"
            return 0
        fi
    fi

    # Fallback: ask user or use current directory
    if [[ -f "./bootstrap/app.php" ]] || [[ -f "./librenms.php" ]]; then
        LIBRENMS_PATH="$(pwd)"
        log "✅ Using current directory: $LIBRENMS_PATH"
        return 0
    fi

    error "Could not find LibreNMS installation"
    error "Please set LIBRENMS_PATH environment variable or run from LibreNMS directory"
    exit 1
}

# Handle user context
handle_user_context() {
    if [[ $EUID -eq 0 ]]; then
        if [[ "$DOCKER_MODE" == "true" ]]; then
            log "🔧 Running as root in Docker container"
            DOCKER_ROOT=true
        else
            error "Do not run as root in standard installation. Run as librenms user."
            exit 1
        fi
    else
        CURRENT_USER=$(whoami)
        log "🔧 Running as user: $CURRENT_USER"
        DOCKER_ROOT=false
    fi
}

# Main installation logic
if detect_docker; then
    DOCKER_MODE=true
    log "🐳 Docker environment detected - using container-optimized installation"
    handle_user_context
    detect_librenms_paths
    install_docker
else
    log "🖥️  Standard installation detected"
    handle_user_context
    detect_librenms_paths
    install_standard
fi

# Docker-optimized installation
install_docker() {
    log "🐳 Starting Docker-optimized installation..."

    # 1. Install dependencies (skip if already in image)
    install_dependencies_docker

    # 2. Run migrations with container database
    run_migrations_docker

    # 3. Set container-appropriate permissions
    set_docker_permissions

    # 4. Handle cron (or skip for containers)
    handle_docker_cron

    # 5. Create container-specific config
    create_docker_config

    # 6. Verify installation
    verify_docker_installation
}

# Standard installation
install_standard() {
    log "🖥️  Starting standard installation..."

    # Check LibreNMS installation
    if [[ ! -d "$LIBRENMS_PATH" ]]; then
        error "LibreNMS not found at $LIBRENMS_PATH"
        exit 1
    fi

    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    if [[ $(php -r "echo version_compare('$PHP_VERSION', '8.0.0', '<');") == "1" ]]; then
        error "PHP 8.0+ required. Current version: $PHP_VERSION"
        exit 1
    fi

    # Check if GD extension is loaded
    if ! php -m | grep -q gd; then
        error "GD extension not loaded. Please install php-gd"
        exit 1
    fi

    # Install via Composer
    cd "$LIBRENMS_PATH/html/plugins"
    if [[ ! -d "WeathermapNG" ]]; then
        log "📥 Cloning WeathermapNG..."
        git clone https://github.com/lance0/weathermapNG.git
    fi

    cd WeathermapNG
    log "📦 Installing dependencies..."
    composer install --no-dev --optimize-autoloader

    # Run migrations automatically
    log "📊 Running database migrations..."
    cd "$LIBRENMS_PATH"

    # Try artisan plugin command first (if LibreNMS supports it)
    if php artisan plugin:migrate WeathermapNG 2>/dev/null; then
        log "✅ Migrations completed via artisan"
    else
        log "⚠️  Artisan migration failed, trying manual migration..."
        php -r "
        require 'vendor/autoload.php';
        require 'bootstrap/app.php';
        \$plugin = new \LibreNMS\Plugins\WeathermapNG\WeathermapNG();
        try {
            \$plugin->activate();
            echo 'Manual migration completed\n';
        } catch (Exception \$e) {
            echo 'Migration failed: ' . \$e->getMessage() . '\n';
            exit(1);
        }
        "
    fi

    # Set permissions
    log "🔐 Setting permissions..."
    chown -R librenms:librenms "$LIBRENMS_PATH/html/plugins/WeathermapNG"
    chmod -R 755 "$LIBRENMS_PATH/html/plugins/WeathermapNG"
    chmod -R 775 "$LIBRENMS_PATH/html/plugins/WeathermapNG/output"

    if [[ -f "$LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php" ]]; then
        chmod +x "$LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php"
    fi

    # Enable plugin (if LibreNMS has plugin management)
    log "⚡ Checking plugin status..."
    if php artisan plugin:list 2>/dev/null | grep -q "WeathermapNG"; then
        if php artisan plugin:enable WeathermapNG 2>/dev/null; then
            log "✅ Plugin enabled via artisan"
        else
            warn "⚠️  Could not enable plugin via artisan, please enable manually in web interface"
        fi
    else
        info "ℹ️  Plugin not registered with artisan, please enable in LibreNMS web interface"
    fi

    # Set up cron job
    log "⏰ Setting up cron job..."
    CRON_LINE="*/5 * * * * librenms $LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1"
    CRON_FILE="/etc/cron.d/librenms"

    if [[ -f "$CRON_FILE" ]]; then
        if ! grep -q "weathermapng" "$CRON_FILE" 2>/dev/null; then
            echo "$CRON_LINE" >> "$CRON_FILE"
            log "✅ Cron job added"
        else
            log "ℹ️  Cron job already exists"
        fi
    else
        warn "⚠️  Cron file not found at $CRON_FILE, please add cron job manually"
    fi

    # Verify installation
    log "🔍 Verifying installation..."

    # Check if tables were created
    TABLES_CREATED=false
    if mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';" 2>/dev/null | grep -q "wmng_"; then
        TABLES_CREATED=true
        log "✅ Database tables created"
    else
        warn "⚠️  Database tables may not have been created"
    fi

    # Check if output directory is writable
    if [[ -w "$LIBRENMS_PATH/html/plugins/WeathermapNG/output" ]]; then
        log "✅ Output directory writable"
    else
        warn "⚠️  Output directory not writable"
    fi

    log ""
    log "🎉 WeathermapNG installation completed!"
    log "🌐 Access at: https://your-librenms/plugins/weathermapng"
    log ""

    if [[ "$TABLES_CREATED" == "true" ]]; then
        log "📝 Next steps:"
        log "   1. Log into LibreNMS web interface"
        log "   2. Go to Overview → Plugins → Plugin Admin"
        log "   3. Verify WeathermapNG is enabled"
        log "   4. Create your first network map!"
    else
        warn "⚠️  Installation may have issues. Please check:"
        warn "   - Database credentials"
        warn "   - File permissions"
        warn "   - PHP extensions"
        warn "   - Check /var/log/librenms/weathermapng_install.log for details"
    fi
}

# Docker-specific functions
install_dependencies_docker() {
    log "📦 Installing dependencies in container..."
    if [[ -f "composer.json" ]]; then
        # Check if composer is available
        if command -v composer &> /dev/null; then
            composer install --no-dev --optimize-autoloader
        else
            warn "⚠️  Composer not found in container, assuming dependencies are pre-installed"
        fi
    fi
}

run_migrations_docker() {
    log "📊 Running database migrations in container..."

    # Try to find and run LibreNMS bootstrap
    if [[ -f "$LIBRENMS_PATH/bootstrap/app.php" ]]; then
        cd "$LIBRENMS_PATH"
        php -r "
        require 'vendor/autoload.php';
        require 'bootstrap/app.php';
        \$plugin = new \LibreNMS\Plugins\WeathermapNG\WeathermapNG();
        try {
            \$plugin->activate();
            echo 'Migration completed successfully\n';
        } catch (Exception \$e) {
            echo 'Migration failed: ' . \$e->getMessage() . '\n';
            exit(1);
        }
        "
    else
        warn "⚠️  Could not find LibreNMS bootstrap, trying alternative migration..."
        # Fallback: try to run migration directly
        if [[ -f "database/migrations/2025_08_29_000001_create_weathermapng_tables.php" ]]; then
            php -r "
            \$migration = require 'database/migrations/2025_08_29_000001_create_weathermapng_tables.php';
            try {
                \$migration->up();
                echo 'Direct migration completed\n';
            } catch (Exception \$e) {
                echo 'Direct migration failed: ' . \$e->getMessage() . '\n';
            }
            "
        fi
    fi
}

set_docker_permissions() {
    log "🔐 Setting container-appropriate permissions..."

    # In containers, we might be running as root or different user
    if [[ "$DOCKER_ROOT" == "true" ]]; then
        # Running as root in container
        chmod -R 755 "$LIBRENMS_PATH/html/plugins/WeathermapNG"
        chmod -R 775 "$LIBRENMS_PATH/html/plugins/WeathermapNG/output"
        if [[ -f "$LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php" ]]; then
            chmod +x "$LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php"
        fi
    else
        # Try to set appropriate ownership
        if command -v chown &> /dev/null; then
            # Try common container users
            for user in www-data nginx apache librenms; do
                if id "$user" &>/dev/null; then
                    chown -R "$user:$user" "$LIBRENMS_PATH/html/plugins/WeathermapNG" 2>/dev/null
                    break
                fi
            done
        fi
        chmod -R 755 "$LIBRENMS_PATH/html/plugins/WeathermapNG"
        chmod -R 775 "$LIBRENMS_PATH/html/plugins/WeathermapNG/output"
    fi
}

handle_docker_cron() {
    # Option 1: Skip cron (let container orchestration handle it)
    if [[ -n "$SKIP_CRON" ]] || [[ "$DOCKER_ROOT" == "true" ]]; then
        warn "⚠️  Skipping cron setup in container environment"
        warn "   Use container orchestration (Docker Compose, Kubernetes) for scheduling"
        create_cron_instructions
        return 0
    fi

    # Option 2: Try container cron
    if [[ -f "/etc/cron.d/librenms" ]] && [[ -w "/etc/cron.d/librenms" ]]; then
        setup_container_cron
    else
        warn "⚠️  Cron not available in container - manual scheduling required"
        create_cron_instructions
    fi
}

setup_container_cron() {
    CRON_LINE="*/5 * * * * librenms $LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1"
    CRON_FILE="/etc/cron.d/librenms"

    if ! grep -q "weathermapng" "$CRON_FILE" 2>/dev/null; then
        echo "$CRON_LINE" >> "$CRON_FILE"
        log "✅ Cron job added to container"
    else
        log "ℹ️  Cron job already exists in container"
    fi
}

create_cron_instructions() {
    log "📋 Container Cron Instructions:"
    log "   Add this to your Docker Compose or orchestration:"
    log "   "
    log "   command: >"
    log "     bash -c \""
    log "       while true; do"
    log "         php $LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php"
    log "         sleep 300"
    log "       done"
    log "     \""
}

create_docker_config() {
    log "⚙️  Creating Docker-optimized configuration..."

    CONFIG_FILE="$LIBRENMS_PATH/html/plugins/WeathermapNG/config/weathermapng.php"
    if [[ ! -f "$CONFIG_FILE" ]]; then
        cat > "$CONFIG_FILE" << 'EOF'
<?php
return [
    'docker_mode' => true,
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300,
    'thresholds' => [50, 80, 95],
    'scale' => 'bits',
    'rrd_base' => env('LIBRENMS_RRD_BASE', '/opt/librenms/rrd'),
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300,
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        'node_warning' => '#ffc107',
        'node_unknown' => '#6c757d',
        'link_normal' => '#28a745',
        'link_warning' => '#ffc107',
        'link_critical' => '#dc3545',
        'background' => '#ffffff',
    ],
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'security' => [
        'allow_embed' => true,
        'max_image_size' => 2048,
    ],
    'editor' => [
        'grid_size' => 20,
        'snap_to_grid' => true,
        'auto_save' => true,
        'auto_save_interval' => 30,
    ],
    // Docker-specific settings
    'log_to_stdout' => env('LOG_TO_STDOUT', true),
    'log_file' => env('WEATHERMAP_LOG', '/dev/stdout'),
];
EOF
        log "✅ Docker configuration created"
    else
        log "ℹ️  Configuration file already exists"
    fi
}

verify_docker_installation() {
    log "🔍 Verifying Docker installation..."

    # Check if output directory exists and is writable
    if [[ -w "$LIBRENMS_PATH/html/plugins/WeathermapNG/output" ]]; then
        log "✅ Output directory writable"
    else
        warn "⚠️  Output directory not writable"
    fi

    # Check if poller script exists and is executable
    if [[ -f "$LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php" ]]; then
        if [[ -x "$LIBRENMS_PATH/html/plugins/WeathermapNG/bin/map-poller.php" ]]; then
            log "✅ Poller script executable"
        else
            warn "⚠️  Poller script not executable"
        fi
    else
        warn "⚠️  Poller script not found"
    fi

    # Check if vendor directory exists (Composer dependencies)
    if [[ -d "$LIBRENMS_PATH/html/plugins/WeathermapNG/vendor" ]]; then
        log "✅ Dependencies available"
    else
        warn "⚠️  Dependencies not found - ensure they're installed in container image"
    fi

    log ""
    log "🎉 Docker installation completed!"
    log "🌐 Access at: https://your-librenms/plugins/weathermapng"
    log ""
    log "📋 Container-specific notes:"
    log "   - Cron scheduling should be handled by your container orchestration"
    log "   - Use Docker Compose or Kubernetes for automated polling"
    log "   - Check container logs for WeathermapNG output"
    log ""
    log "📖 For Docker help, see: https://github.com/lance0/weathermapNG#docker-installation"
}