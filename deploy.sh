#!/bin/bash
# WeathermapNG Deployment Script for LibreNMS v2
# Simple, focused deployment to test server

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_NAME="WeathermapNG"
PLUGIN_DIR=$(cd "$(dirname "$0")" && pwd)  # Get absolute path
LIBRENMS_PATH="${LIBRENMS_PATH:-/opt/librenms}"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  WeathermapNG Deployment Script v2${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Function to print status
log() {
    echo -e "${GREEN}[✓]${NC} $1"
}

error() {
    echo -e "${RED}[✗]${NC} $1" >&2
    exit 1
}

warn() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Check if we're in the plugin directory
if [ ! -f "$PLUGIN_DIR/plugin.json" ]; then
    error "Not in WeathermapNG directory. Please run from plugin root."
fi

# Step 1: Pull latest changes
log "Pulling latest code from git..."
git pull origin main || warn "Could not pull from git (may not be a git repo)"

# Step 2: Install/update composer dependencies
if [ -f "$PLUGIN_DIR/composer.json" ]; then
    log "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader || warn "Composer install failed"
fi

# Step 3: Ensure plugin is in LibreNMS plugins directory
EXPECTED_DIR="$LIBRENMS_PATH/html/plugins/$PLUGIN_NAME"
if [ "$PLUGIN_DIR" != "$EXPECTED_DIR" ]; then
    # Check if we're already in a subdirectory of the expected location
    if [[ "$PLUGIN_DIR" == "$EXPECTED_DIR"/* ]] || [[ "$EXPECTED_DIR" == "$PLUGIN_DIR"/* ]]; then
        log "Plugin is in LibreNMS directory structure"
    else
        warn "Plugin not in standard LibreNMS location"
        warn "Expected: $EXPECTED_DIR"
        warn "Current: $PLUGIN_DIR"
        echo ""
        read -p "Create symlink to LibreNMS plugins directory? (y/n) " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            if [ -e "$EXPECTED_DIR" ]; then
                warn "Target already exists, skipping symlink"
            else
                log "Creating symlink..."
                sudo ln -sfn "$PLUGIN_DIR" "$EXPECTED_DIR"
            fi
        fi
    fi
else
    log "Plugin is in correct location"
fi

# Step 4: Run database migrations
log "Running database migrations..."
cd "$LIBRENMS_PATH"

# Check if artisan is available
if [ -f "artisan" ]; then
    php artisan migrate --path="html/plugins/$PLUGIN_NAME/database/migrations" --force || warn "Migrations may have already run"
else
    warn "Laravel artisan not found, trying legacy migration..."
    if [ -f "$PLUGIN_DIR/database/migrations/create_weathermapng_tables.php" ]; then
        php "$PLUGIN_DIR/database/migrations/create_weathermapng_tables.php"
    fi
fi

# Step 5: Fix permissions
log "Setting permissions..."
if [ -d "$LIBRENMS_PATH" ]; then
    # Get LibreNMS user (usually librenms or www-data)
    if [ -f "$LIBRENMS_PATH/.env" ]; then
        LIBRENMS_USER=$(grep -E "^LIBRENMS_USER=" "$LIBRENMS_PATH/.env" | cut -d'=' -f2 | tr -d '"' || echo "librenms")
    else
        LIBRENMS_USER=$(whoami)  # Use current user if can't determine
    fi
    
    log "Using LibreNMS user: $LIBRENMS_USER"
    
    # Set ownership (only if we have permission)
    if [ -w "$PLUGIN_DIR" ]; then
        chown -R "$LIBRENMS_USER:$LIBRENMS_USER" "$PLUGIN_DIR" 2>/dev/null || warn "Could not set ownership (may need sudo)"
    fi
    
    # Ensure output directories are writable
    [ -d "$PLUGIN_DIR/output" ] || mkdir -p "$PLUGIN_DIR/output"
    [ -d "$PLUGIN_DIR/output/maps" ] || mkdir -p "$PLUGIN_DIR/output/maps"
    [ -d "$PLUGIN_DIR/output/cache" ] || mkdir -p "$PLUGIN_DIR/output/cache"
    chmod -R 755 "$PLUGIN_DIR/output"
fi

# Step 6: Clear Laravel caches
log "Clearing caches..."
cd "$LIBRENMS_PATH"
if [ -f "artisan" ]; then
    php artisan config:clear
    php artisan cache:clear
    php artisan view:clear
    php artisan route:clear
fi

# Step 7: Register plugin hooks (v2 style)
log "Registering plugin hooks..."
if [ -f "$LIBRENMS_PATH/lnms" ]; then
    ./lnms plugin:enable "$PLUGIN_NAME" 2>/dev/null || warn "Plugin may already be enabled"
fi

# Step 8: Setup cron job for poller
log "Setting up cron job..."
if [ -f "$PLUGIN_DIR/bin/map-poller.php" ]; then
    # Make poller executable
    chmod +x "$PLUGIN_DIR/bin/map-poller.php"
    
    # Add to LibreNMS cron if not present
    CRON_FILE="/etc/cron.d/librenms"
    if [ -f "$CRON_FILE" ] && [ -w "$CRON_FILE" ]; then
        if ! grep -q "map-poller.php" "$CRON_FILE"; then
            echo "*/5 * * * * $LIBRENMS_USER php $PLUGIN_DIR/bin/map-poller.php >> /tmp/weathermapng.log 2>&1" | sudo tee -a "$CRON_FILE" > /dev/null
            log "Cron job added to $CRON_FILE"
        else
            warn "Cron job already exists in $CRON_FILE"
        fi
    else
        # Try user crontab as fallback
        if command -v crontab >/dev/null 2>&1; then
            if ! crontab -l 2>/dev/null | grep -q "map-poller.php"; then
                (crontab -l 2>/dev/null; echo "*/5 * * * * php $PLUGIN_DIR/bin/map-poller.php >> /tmp/weathermapng.log 2>&1") | crontab -
                log "Cron job added to user crontab"
            else
                warn "Cron job already exists in user crontab"
            fi
        else
            warn "Could not add cron job - please add manually"
            info "Add to cron: */5 * * * * php $PLUGIN_DIR/bin/map-poller.php"
        fi
    fi
else
    warn "Poller script not found at bin/map-poller.php"
fi

# Step 9: Verify installation
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Verification${NC}"
echo -e "${GREEN}========================================${NC}"

# Check if tables exist
if php -r "
    require '$LIBRENMS_PATH/vendor/autoload.php';
    require '$LIBRENMS_PATH/includes/init.php';
    \$exists = \Illuminate\Support\Facades\Schema::hasTable('wmng_maps');
    exit(\$exists ? 0 : 1);
" 2>/dev/null; then
    log "Database tables exist"
else
    warn "Database tables not found - run migrations manually"
fi

# Check if plugin appears in menu
if [ -f "$PLUGIN_DIR/app/Plugins/WeathermapNG/Menu.php" ]; then
    log "Menu hook found"
else
    error "Menu hook not found"
fi

# Check routes
if [ -f "$PLUGIN_DIR/routes.php" ]; then
    log "Routes file exists"
else
    warn "Routes file not found"
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Deployment Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Next steps:"
echo "1. Visit http://your-server/plugin/WeathermapNG"
echo "2. Check menu for 'Network Maps' entry"
echo "3. Create your first map"
echo ""
echo "If you encounter issues:"
echo "- Check logs: tail -f /tmp/weathermapng.log"
echo "- Run verification: php $PLUGIN_DIR/verify-deployment.php"
echo "- Check LibreNMS logs: tail -f $LIBRENMS_PATH/logs/librenms.log"