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

if [ "$(id -u)" -eq 0 ] && [ "$IN_DOCKER" = false ] && [ "${WEATHERMAPNG_ALLOW_ROOT:-0}" != "1" ]; then
    echo "Error: Do not run quick-install.sh as root on native LibreNMS installs."
    echo "Run it as the LibreNMS user so Composer does not create root-owned files:"
    echo "  chown -R librenms:librenms $PLUGIN_DIR"
    echo "  sudo -u librenms -H bash -lc 'cd $PLUGIN_DIR && ./quick-install.sh'"
    echo ""
    echo "If you really need to override this guard, set WEATHERMAPNG_ALLOW_ROOT=1."
    exit 1
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
echo "[1/7] Checking PHP..."
if ! command -v php &> /dev/null; then
    echo "Error: PHP not found"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "  PHP Version: $PHP_VERSION"
if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
    echo "Error: PHP 8.2+ required"
    exit 1
fi

# Install dependencies
echo "[2/7] Installing dependencies..."
cd "$PLUGIN_DIR"
if [ ! -w "$PLUGIN_DIR" ]; then
    echo "Error: Plugin directory is not writable by $(id -un)."
    echo "Fix ownership first, for example:"
    echo "  chown -R librenms:librenms $PLUGIN_DIR"
    exit 1
fi

if ! composer install --no-dev --optimize-autoloader 2>&1; then
    echo "Error: Composer install failed"
    exit 1
fi

# Register package with LibreNMS so Laravel package discovery loads routes and views
echo "[3/7] Registering with LibreNMS Composer..."
if [ -f "$LIBRENMS_PATH/composer.json" ]; then
    cd "$LIBRENMS_PATH"
    if ! COMPOSER_ALLOW_SUPERUSER=1 composer config repositories.weathermapng "{\"type\":\"path\",\"url\":\"$PLUGIN_DIR\",\"options\":{\"symlink\":true}}" 2>&1; then
        echo "Error: Could not register WeathermapNG as a Composer path repository"
        exit 1
    fi

    if ! FORCE=1 COMPOSER_ALLOW_SUPERUSER=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction 2>&1; then
        echo "Error: Could not add WeathermapNG to the LibreNMS Composer install"
        echo "  Try running from $LIBRENMS_PATH:"
        echo "  composer config repositories.weathermapng '{\"type\":\"path\",\"url\":\"$PLUGIN_DIR\",\"options\":{\"symlink\":true}}'"
        echo "  FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction"
        exit 1
    fi

    if [ -f "$LIBRENMS_PATH/artisan" ]; then
        php artisan package:discover 2>/dev/null || echo "  Warning: package:discover failed"
    fi
else
    echo "  Skipped: composer.json not found at $LIBRENMS_PATH"
fi

# Database setup
echo "[4/7] Setting up database..."
cd "$PLUGIN_DIR"
if ! php database/setup.php; then
    echo "Error: Database setup failed"
    echo "  Try running manually: php database/setup.php"
    exit 1
fi

# Enable plugin
echo "[5/7] Enabling plugin..."
if [ -f "$LIBRENMS_PATH/lnms" ]; then
    cd "$LIBRENMS_PATH"
    ./lnms plugin:enable WeathermapNG 2>/dev/null || echo "  Note: Plugin may already be enabled"

    echo "  Normalizing WeathermapNG plugin registration..."
    php <<'PHP'
<?php

try {
    $root = getcwd();
    if (!is_file($root . '/vendor/autoload.php') || !is_file($root . '/bootstrap/app.php')) {
        echo "  Warning: Could not find LibreNMS bootstrap; skipped plugin row normalization\n";
        exit(0);
    }

    require_once $root . '/vendor/autoload.php';
    $app = require_once $root . '/bootstrap/app.php';

    if (method_exists($app, 'make')) {
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        if (method_exists($kernel, 'bootstrap')) {
            $kernel->bootstrap();
        }
    }

    $schema = Illuminate\Support\Facades\Schema::class;
    $db = Illuminate\Support\Facades\DB::class;

    if (!$schema::hasTable('plugins')) {
        echo "  Warning: LibreNMS plugins table not found; skipped plugin row normalization\n";
        exit(0);
    }

    $columns = $schema::getColumnListing('plugins');
    $required = ['plugin_id', 'plugin_name', 'plugin_active'];
    $missing = array_diff($required, $columns);
    if (!empty($missing)) {
        echo "  Warning: LibreNMS plugins table is missing expected column(s): " . implode(', ', $missing) . "\n";
        echo "  Warning: Skipped plugin row normalization\n";
        exit(0);
    }

    $rows = $db::table('plugins')
        ->where('plugin_name', 'WeathermapNG')
        ->orderBy('plugin_id')
        ->get();

    if ($rows->isEmpty()) {
        echo "  Warning: No WeathermapNG plugin row found after enable step\n";
        exit(0);
    }

    $activeRows = $rows
        ->filter(fn ($row) => (int) $row->plugin_active === 1)
        ->values();

    $keep = $activeRows->isNotEmpty()
        ? $activeRows->sortByDesc('plugin_id')->first()
        : $rows->sortByDesc('plugin_id')->first();

    $db::table('plugins')
        ->where('plugin_name', 'WeathermapNG')
        ->where('plugin_id', $keep->plugin_id)
        ->update(['plugin_active' => 1]);

    $deleted = $db::table('plugins')
        ->where('plugin_name', 'WeathermapNG')
        ->where('plugin_id', '!=', $keep->plugin_id)
        ->delete();

    if ($deleted > 0) {
        echo "  Cleaned $deleted duplicate WeathermapNG plugin row(s); kept plugin_id {$keep->plugin_id}\n";
    } else {
        echo "  WeathermapNG plugin registration is already normalized\n";
    }
} catch (Throwable $e) {
    echo "  Warning: Plugin row normalization failed: {$e->getMessage()}\n";
}
PHP
else
    echo "  Skipped: lnms not found (enable manually with: ./lnms plugin:enable WeathermapNG)"
fi

# Clear caches and verify routes
echo "[6/7] Clearing caches and verifying routes..."
if [ -f "$LIBRENMS_PATH/artisan" ]; then
    cd "$LIBRENMS_PATH"
    php artisan optimize:clear 2>/dev/null || echo "  Warning: optimize:clear failed (may need sudo)"
    php artisan route:clear 2>/dev/null || echo "  Warning: route:clear failed"
    php artisan view:clear 2>/dev/null || echo "  Warning: view:clear failed"
    php artisan config:clear 2>/dev/null || echo "  Warning: config:clear failed"
    php artisan cache:clear 2>/dev/null || echo "  Warning: cache:clear failed"

    if php artisan route:list 2>/dev/null | grep -qiE "weathermap|wmng"; then
        echo "  WeathermapNG routes detected"
    else
        echo "  Warning: WeathermapNG routes were not detected"
        echo "  Check with: cd $LIBRENMS_PATH && php artisan route:list | grep -iE 'weathermap|wmng'"
    fi
else
    echo "  Skipped: artisan not found at $LIBRENMS_PATH"
fi

# Set permissions (skip in Docker as it's usually handled by entrypoint)
echo "[7/7] Setting permissions..."
mkdir -p "$PLUGIN_DIR/output/maps" "$PLUGIN_DIR/output/thumbnails" 2>/dev/null || echo "  Warning: Could not create output directories"
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
echo "  3. If LibreNMS validate.php flags wmng_* as extra tables, keep them."
echo "     JSON column collation warnings on wmng_* JSON columns are expected."
echo ""
echo "Optional - Create demo data:"
echo "  php $PLUGIN_DIR/database/seed-demo.php"
echo ""
echo "Troubleshooting: https://github.com/lance0/weathermapNG/blob/main/INSTALL.md"
