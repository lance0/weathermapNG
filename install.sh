#!/bin/bash
# WeathermapNG Enhanced Installer v2
# One-click installation with better error handling and automation

set -euo pipefail  # Exit on error, undefined vars, pipe failures

# ============================================================================
# Configuration & Setup
# ============================================================================

SCRIPT_VERSION="2.0.0"
PLUGIN_NAME="WeathermapNG"
GITHUB_REPO="https://github.com/lance0/weathermapNG.git"
LOG_FILE="/tmp/weathermapng_install_$(date +%Y%m%d_%H%M%S).log"
INSTALL_MODE="${1:-express}"  # express, custom, docker, dev

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Installation state for rollback
INSTALL_STEPS=()
PLUGIN_DIR=""
LIBRENMS_PATH=""
DOCKER_MODE=false

# ============================================================================
# Logging Functions
# ============================================================================

log() {
    local message="$1"
    echo -e "${GREEN}[✓]${NC} $message"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] INFO: $message" >> "$LOG_FILE"
}

error() {
    local message="$1"
    echo -e "${RED}[✗]${NC} $message" >&2
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $message" >> "$LOG_FILE"
}

warn() {
    local message="$1"
    echo -e "${YELLOW}[!]${NC} $message"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] WARN: $message" >> "$LOG_FILE"
}

info() {
    local message="$1"
    echo -e "${BLUE}[i]${NC} $message"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] INFO: $message" >> "$LOG_FILE"
}

step() {
    local message="$1"
    echo -e "${CYAN}==>${NC} $message"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] STEP: $message" >> "$LOG_FILE"
}

# ============================================================================
# Prerequisite Checks
# ============================================================================

check_prerequisites() {
    step "Checking prerequisites..."
    
    local missing_deps=()
    
    # Check PHP version
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_VERSION;")
        if [[ $(php -r "echo version_compare('$PHP_VERSION', '8.0.0', '>=') ? 'true' : 'false';") == "false" ]]; then
            error "PHP 8.0+ required (found: $PHP_VERSION)"
            exit 1
        fi
        log "PHP $PHP_VERSION found"
    else
        missing_deps+=("php")
    fi
    
    # Check required commands
    local required_commands=("git" "composer" "mysql")
    for cmd in "${required_commands[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            missing_deps+=("$cmd")
        else
            log "$cmd found"
        fi
    done
    
    # Check PHP extensions
    local required_extensions=("gd" "json" "mbstring" "mysqli")
    for ext in "${required_extensions[@]}"; do
        if ! php -m 2>/dev/null | grep -qi "^$ext$"; then
            missing_deps+=("php-$ext")
        else
            log "PHP extension $ext found"
        fi
    done
    
    # Check PDO (uppercase in php -m output, included in php-common)
    if ! php -m 2>/dev/null | grep -q "^PDO$"; then
        # PDO should be in php-common
        if command -v apt-get &> /dev/null; then
            PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "")
            if [[ -n "$PHP_VERSION" ]]; then
                missing_deps+=("php${PHP_VERSION}-common")
            else
                missing_deps+=("php-common")
            fi
        else
            missing_deps+=("php-common")
        fi
    else
        log "PHP extension PDO found"
    fi
    
    # Check for PDO MySQL driver (lowercase in php -m output)
    if ! php -m 2>/dev/null | grep -q "^pdo_mysql$"; then
        # Need php-mysql for PDO MySQL support
        if command -v apt-get &> /dev/null; then
            # Ubuntu/Debian - use version-specific package
            PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "")
            if [[ -n "$PHP_VERSION" ]]; then
                missing_deps+=("php${PHP_VERSION}-mysql")
            else
                missing_deps+=("php-mysql")
            fi
        elif command -v yum &> /dev/null || command -v dnf &> /dev/null; then
            missing_deps+=("php-mysqlnd")
        else
            missing_deps+=("php-mysql")
        fi
    else
        log "PHP extension pdo_mysql found"
    fi
    
    # Report missing dependencies
    if [[ ${#missing_deps[@]} -gt 0 ]]; then
        error "Missing dependencies: ${missing_deps[*]}"
        info "Install them with:"
        
        # Detect package manager
        if command -v apt-get &> /dev/null; then
            info "  sudo apt-get install ${missing_deps[*]}"
        elif command -v yum &> /dev/null; then
            info "  sudo yum install ${missing_deps[*]}"
        elif command -v dnf &> /dev/null; then
            info "  sudo dnf install ${missing_deps[*]}"
        fi
        
        if [[ "$INSTALL_MODE" != "express" ]]; then
            read -p "Continue anyway? (y/N): " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                exit 1
            fi
        else
            exit 1
        fi
    fi
    
    INSTALL_STEPS+=("prerequisites")
}

# ============================================================================
# Environment Detection
# ============================================================================

detect_environment() {
    step "Detecting environment..."
    
    # Check for Docker
    if [[ -f "/.dockerenv" ]] || [[ -n "${DOCKER_CONTAINER:-}" ]] || [[ -n "${LIBRENMS_DOCKER:-}" ]]; then
        DOCKER_MODE=true
        log "Docker environment detected"
    else
        log "Standard host environment detected"
    fi
    
    # Check user context
    if [[ $EUID -eq 0 ]] && [[ "$DOCKER_MODE" == "false" ]]; then
        error "Do not run as root. Please run as librenms user or use sudo."
        info "Try: sudo -u librenms $0"
        exit 1
    fi
    
    INSTALL_STEPS+=("environment")
}

# ============================================================================
# LibreNMS Detection
# ============================================================================

detect_librenms() {
    step "Locating LibreNMS installation..."
    
    # Check common paths
    local paths=(
        "/opt/librenms"
        "/usr/local/librenms"
        "/var/www/librenms"
        "/app"
        "${LIBRENMS_PATH:-}"
    )
    
    for path in "${paths[@]}"; do
        if [[ -n "$path" ]] && [[ -f "$path/bootstrap/app.php" ]] || [[ -f "$path/librenms.php" ]]; then
            LIBRENMS_PATH="$path"
            log "Found LibreNMS at: $LIBRENMS_PATH"
            break
        fi
    done
    
    # If not found, ask user
    if [[ -z "$LIBRENMS_PATH" ]]; then
        if [[ "$INSTALL_MODE" == "express" ]]; then
            error "Could not find LibreNMS installation"
            info "Set LIBRENMS_PATH environment variable and try again"
            exit 1
        else
            read -p "Enter LibreNMS path: " -r LIBRENMS_PATH
            if [[ ! -f "$LIBRENMS_PATH/bootstrap/app.php" ]]; then
                error "Invalid LibreNMS path"
                exit 1
            fi
        fi
    fi
    
    # Set plugin directory
    PLUGIN_DIR="$LIBRENMS_PATH/html/plugins/$PLUGIN_NAME"
    
    INSTALL_STEPS+=("librenms")
}

# ============================================================================
# Installation Functions
# ============================================================================

download_plugin() {
    step "Downloading WeathermapNG..."
    
    if [[ -d "$PLUGIN_DIR" ]]; then
        warn "Plugin directory already exists"
        if [[ "$INSTALL_MODE" == "express" ]]; then
            log "Updating existing installation..."
            cd "$PLUGIN_DIR"
            git pull origin main 2>/dev/null || true
        else
            read -p "Update existing installation? (Y/n): " -n 1 -r
            echo
            if [[ $REPLY =~ ^[Nn]$ ]]; then
                log "Keeping existing installation"
                return
            fi
            cd "$PLUGIN_DIR"
            git pull origin main
        fi
    else
        cd "$LIBRENMS_PATH/html/plugins"
        log "Cloning repository..."
        git clone "$GITHUB_REPO" "$PLUGIN_NAME"
    fi
    
    INSTALL_STEPS+=("download")
}

install_dependencies() {
    step "Installing dependencies..."
    
    cd "$PLUGIN_DIR"
    
    # Install composer dependencies
    if [[ "$INSTALL_MODE" == "dev" ]]; then
        log "Installing development dependencies..."
        composer install --optimize-autoloader
    else
        log "Installing production dependencies..."
        composer install --no-dev --optimize-autoloader --no-interaction
    fi
    
    INSTALL_STEPS+=("dependencies")
}

install_v2_hooks() {
    step "Registering v2 plugin hooks (app/Plugins)..."

    local target_dir="$LIBRENMS_PATH/app/Plugins/$PLUGIN_NAME"
    mkdir -p "$target_dir/resources/views"

    # Copy hook classes
    if cp -f "$PLUGIN_DIR/app/Plugins/$PLUGIN_NAME/"{Menu.php,Page.php,Settings.php} "$target_dir/" 2>/dev/null; then
        log "Hook classes installed to app/Plugins/$PLUGIN_NAME"
    else
        warn "Could not copy hook classes — check paths"
    fi

    # Copy views for hooks
    if cp -f "$PLUGIN_DIR/app/Plugins/$PLUGIN_NAME/resources/views/"*.blade.php "$target_dir/resources/views/" 2>/dev/null; then
        log "Hook views installed"
    else
        warn "Could not copy hook views — check paths"
    fi
}

run_migrations() {
    step "Setting up database..."
    
    cd "$PLUGIN_DIR"
    
    # Run our manual database setup script
    if [[ -f "database/setup.php" ]]; then
        log "Running database setup script..."
        if php database/setup.php; then
            log "Database tables created successfully"
            INSTALL_STEPS+=("database")
            return
        else
            warn "Could not create database tables automatically"
        fi
    fi
    
    # Fallback: provide manual instructions
    warn "Automatic database setup failed"
    info "To create tables manually, choose one of these options:"
    echo
    info "Option 1 - Run setup script:"
    info "  cd $PLUGIN_DIR"
    info "  php database/setup.php"
    echo
    info "Option 2 - Import SQL directly:"
    info "  mysql -u librenms -p librenms < $PLUGIN_DIR/database/schema.sql"
    echo
    
    if [[ "$INSTALL_MODE" != "express" ]]; then
        read -p "Continue installation anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            error "Installation cancelled"
            exit 1
        fi
    fi
    
    INSTALL_STEPS+=("database")
}

set_permissions() {
    step "Setting permissions..."
    
    # Detect web user
    local web_user="librenms"
    if [[ "$DOCKER_MODE" == "true" ]]; then
        web_user="www-data"
    elif id "www-data" &>/dev/null; then
        web_user="www-data"
    fi
    
    # Set ownership (may require sudo)
    if [[ $EUID -eq 0 ]] || sudo -n true 2>/dev/null; then
        sudo chown -R "$web_user:$web_user" "$PLUGIN_DIR"
        log "Ownership set to $web_user"
    else
        warn "Cannot set ownership (need sudo). Run manually:"
        info "  sudo chown -R $web_user:$web_user $PLUGIN_DIR"
    fi
    
    # Set permissions
    chmod -R 755 "$PLUGIN_DIR"
    chmod -R 775 "$PLUGIN_DIR/output" 2>/dev/null || mkdir -p "$PLUGIN_DIR/output" && chmod 775 "$PLUGIN_DIR/output"
    [[ -f "$PLUGIN_DIR/bin/map-poller.php" ]] && chmod +x "$PLUGIN_DIR/bin/map-poller.php"
    
    INSTALL_STEPS+=("permissions")
}

setup_cron() {
    step "Setting up scheduled tasks..."
    
    if [[ "$DOCKER_MODE" == "true" ]]; then
        info "Docker detected - skipping cron setup"
        info "Add polling to your container orchestration"
        return
    fi
    
    local cron_line="*/5 * * * * librenms php $PLUGIN_DIR/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1"
    
    # Try to add to system cron
    if [[ -f "/etc/cron.d/librenms" ]]; then
        if ! grep -q "weathermapng" /etc/cron.d/librenms 2>/dev/null; then
            if [[ $EUID -eq 0 ]] || sudo -n true 2>/dev/null; then
                echo "$cron_line" | sudo tee -a /etc/cron.d/librenms > /dev/null
                log "Cron job added"
            else
                warn "Cannot add cron job (need sudo). Add manually:"
                info "  $cron_line"
            fi
        else
            log "Cron job already exists"
        fi
    else
        # Try user crontab
        (crontab -l 2>/dev/null | grep -q weathermapng) || (crontab -l 2>/dev/null; echo "$cron_line") | crontab -
        log "Added to user crontab"
    fi
    
    INSTALL_STEPS+=("cron")
}

enable_plugin() {
    step "Enabling plugin..."
    
    cd "$LIBRENMS_PATH"
    
    # Try to enable via lnms CLI (correct command)
    if [[ -f "lnms" ]]; then
        if ./lnms plugin:enable WeathermapNG 2>/dev/null; then
            log "Plugin enabled successfully"
        else
            warn "Could not enable plugin automatically"
            info "Enable manually via:"
            echo
            info "Option 1 - Command line:"
            info "  cd $LIBRENMS_PATH"
            info "  ./lnms plugin:enable WeathermapNG"
            echo
            info "Option 2 - Web interface:"
            info "  Overview → Plugins → Plugin Admin → WeathermapNG → Enable"
        fi
    else
        info "Enable plugin in LibreNMS:"
        echo
        info "Option 1 - Find lnms command:"
        info "  find $LIBRENMS_PATH -name 'lnms' -type f"
        info "  ./lnms plugin:enable WeathermapNG"
        echo
        info "Option 2 - Web interface:"
        info "  Overview → Plugins → Plugin Admin → WeathermapNG → Enable"
    fi
    
    INSTALL_STEPS+=("enable")
}

# ============================================================================
# Verification
# ============================================================================

verify_installation() {
    step "Verifying installation..."
    
    cd "$PLUGIN_DIR"
    
    if [[ -f "verify.php" ]]; then
        php verify.php
    fi
    
    # Quick health check
    local health_url="http://localhost/plugin/WeathermapNG/health"
    if command -v curl &> /dev/null; then
        if curl -s "$health_url" | grep -q "healthy"; then
            log "Health check passed"
        else
            warn "Health check failed or not accessible"
        fi
    fi
    
    INSTALL_STEPS+=("verify")
}

# ============================================================================
# Rollback Function
# ============================================================================

rollback() {
    error "Installation failed. Rolling back..."
    
    for step in "${INSTALL_STEPS[@]}"; do
        case $step in
            "download")
                [[ -d "$PLUGIN_DIR" ]] && rm -rf "$PLUGIN_DIR"
                warn "Removed plugin directory"
                ;;
            "database")
                warn "Database changes may need manual cleanup"
                ;;
            "cron")
                if [[ -f "/etc/cron.d/librenms" ]]; then
                    sudo sed -i '/weathermapng/d' /etc/cron.d/librenms 2>/dev/null || true
                fi
                crontab -l | grep -v weathermapng | crontab - 2>/dev/null || true
                ;;
        esac
    done
    
    error "Installation rolled back. Check log: $LOG_FILE"
    exit 1
}

# ============================================================================
# Success Message
# ============================================================================

show_success() {
    echo
    echo -e "${GREEN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║        WeathermapNG Installation Successful! 🎉        ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════╝${NC}"
    echo
    log "Installation completed successfully!"
    info "Log file: $LOG_FILE"
    echo
    echo -e "${CYAN}Next steps:${NC}"
    echo "  1. Enable plugin in LibreNMS (if not done automatically)"
    echo "  2. Visit: http://your-librenms/plugin/WeathermapNG"
    echo "  3. Create your first network map!"
    echo
    info "For help: https://github.com/lance0/weathermapNG"
}

# ============================================================================
# Main Installation Flow
# ============================================================================

main() {
    echo -e "${CYAN}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║          WeathermapNG Enhanced Installer v2.0          ║${NC}"
    echo -e "${CYAN}║                  Mode: $INSTALL_MODE                   ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════╝${NC}"
    echo
    
    # Set error trap
    trap rollback ERR
    
    # Run installation steps
    check_prerequisites
    detect_environment
    detect_librenms
    download_plugin
    install_dependencies
    install_v2_hooks
    run_migrations
    set_permissions
    setup_cron
    enable_plugin
    verify_installation
    
    # Clear error trap
    trap - ERR
    
    show_success
}

# ============================================================================
# Script Entry Point
# ============================================================================

# Show help if requested
if [[ "${1:-}" == "--help" ]] || [[ "${1:-}" == "-h" ]]; then
    echo "WeathermapNG Enhanced Installer"
    echo ""
    echo "Usage: $0 [mode]"
    echo ""
    echo "Modes:"
    echo "  express  - Fully automatic installation (default)"
    echo "  custom   - Interactive installation with prompts"
    echo "  docker   - Optimized for Docker containers"
    echo "  dev      - Development mode with all dependencies"
    echo ""
    echo "Examples:"
    echo "  $0                    # Express installation"
    echo "  $0 custom            # Interactive mode"
    echo "  $0 docker            # Docker installation"
    echo ""
    exit 0
fi

# Start installation
main "$@"
