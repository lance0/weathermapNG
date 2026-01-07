#!/bin/bash
# WeathermapNG Quick Install
# Works with both native and Docker LibreNMS installations

set -e

echo "WeathermapNG Quick Install"
echo "=========================="

# Detect environment
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
LIBRENMS_PATH="${LIBRENMS_PATH:-/opt/librenms}"

# Check if we're in the plugin directory
if [ ! -f "$PLUGIN_DIR/composer.json" ]; then
    echo "Error: Please run this from the WeathermapNG plugin directory"
    exit 1
fi

# Detect if running in Docker
IN_DOCKER=false
if [ -f "/.dockerenv" ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
    IN_DOCKER=true
    echo "Detected Docker environment"
fi

# Find LibreNMS installation
if [ ! -d "$LIBRENMS_PATH" ]; then
    # Try common locations
    for path in /opt/librenms /usr/local/librenms /data; do
        if [ -f "$path/artisan" ] || [ -f "$path/lnms" ]; then
            LIBRENMS_PATH="$path"
            break
        fi
    done
fi

echo "LibreNMS path: $LIBRENMS_PATH"
echo "Plugin path: $PLUGIN_DIR"
echo ""

# Check PHP
echo "[1/6] Checking PHP..."
if ! command -v php &> /dev/null; then
    echo "Error: PHP not found"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "  PHP Version: $PHP_VERSION"
if ! php -r 'exit(version_compare(PHP_VERSION, "8.0.0", ">=") ? 0 : 1);'; then
    echo "Error: PHP 8.0+ required"
    exit 1
fi

# Install dependencies
echo "[2/6] Installing dependencies..."
cd "$PLUGIN_DIR"
if ! composer install --no-dev --optimize-autoloader 2>&1; then
    echo "Error: Composer install failed"
    exit 1
fi

# Database setup
echo "[3/6] Setting up database..."
if ! php database/setup.php; then
    echo "Error: Database setup failed"
    echo "  Try running manually: php database/setup.php"
    exit 1
fi

# Clear caches
echo "[4/6] Clearing caches..."
if [ -f "$LIBRENMS_PATH/artisan" ]; then
    cd "$LIBRENMS_PATH"
    php artisan cache:clear 2>/dev/null || echo "  Warning: cache:clear failed (may need sudo)"
    php artisan view:clear 2>/dev/null || echo "  Warning: view:clear failed"
    php artisan config:clear 2>/dev/null || echo "  Warning: config:clear failed"
else
    echo "  Skipped: artisan not found at $LIBRENMS_PATH"
fi

# Enable plugin
echo "[5/6] Enabling plugin..."
if [ -f "$LIBRENMS_PATH/lnms" ]; then
    cd "$LIBRENMS_PATH"
    ./lnms plugin:enable WeathermapNG 2>/dev/null || echo "  Note: Plugin may already be enabled"
else
    echo "  Skipped: lnms not found (enable manually with: ./lnms plugin:enable WeathermapNG)"
fi

# Set permissions (skip in Docker as it's usually handled by entrypoint)
echo "[6/6] Setting permissions..."
if [ "$IN_DOCKER" = false ]; then
    if [ -d "$PLUGIN_DIR" ]; then
        chown -R librenms:librenms "$PLUGIN_DIR" 2>/dev/null || echo "  Warning: Could not set ownership (may need sudo)"
    fi
else
    echo "  Skipped in Docker (handled by container)"
fi

echo ""
echo "Installation complete!"
echo "=========================="
echo ""
echo "Next steps:"
echo "  1. Visit: https://your-server/plugin/WeathermapNG"
echo "  2. Create your first map!"
echo ""
echo "Optional - Create demo data:"
echo "  php $PLUGIN_DIR/database/seed-demo.php"
echo ""
echo "Troubleshooting: https://github.com/lance0/weathermapNG/blob/main/INSTALL.md"
