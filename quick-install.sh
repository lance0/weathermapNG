#!/bin/bash
# WeathermapNG Quick Install
# Simple one-command installation

set -e

echo "🚀 WeathermapNG Quick Install"
echo "=============================="

# Check if we're in plugin directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Please run this from WeathermapNG plugin directory"
    echo "   cd /opt/librenms/html/plugins/WeathermapNG"
    exit 1
fi

echo "🔍 Checking system..."
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP not found. Please install PHP 8.0+"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "   PHP Version: $PHP_VERSION"
if ! php -r 'exit(version_compare(PHP_VERSION, "8.0.0", ">=") ? 0 : 1);'; then
    echo "❌ Error: PHP 8.0+ required, found $PHP_VERSION"
    exit 1
fi

echo "📦 Installing dependencies..."
if ! composer install --no-dev --optimize-autoloader; then
    echo "❌ Error: Composer install failed"
    exit 1
fi

echo "🗄️  Setting up database..."
if ! php database/setup.php; then
    echo "❌ Error: Database setup failed"
    echo "   Check database connection and permissions"
    echo "   Manual setup: php database/setup.php"
    exit 1
fi

echo "🔍 Verifying database tables..."
TABLES=$(php -r "try { \$pdo = new PDO('mysql:host=localhost;dbname=librenms', getenv('DB_USERNAME') ?: 'librenms', getenv('DB_PASSWORD') ?: 'librenms'); \$stmt = \$pdo->query('SHOW TABLES LIKE \"wmng_%\"'); echo count(\$stmt->fetchAll(PDO::FETCH_COLUMN)); } catch(Exception \$e) { echo 0; }" 2>/dev/null || echo 0)
if [ "$TABLES" -lt 3 ]; then
    echo "❌ Error: Database tables not created properly"
    exit 1
fi
echo "   ✓ Found $TABLES database tables"

echo "🔧 Configuring LibreNMS..."
if [ ! -d "/opt/librenms" ]; then
    echo "⚠️  Warning: LibreNMS directory not found at /opt/librenms"
    echo "   Skipping cache clear (will need manual: php artisan cache:clear)"
else
    cd /opt/librenms
    php artisan cache:clear > /dev/null 2>&1 || echo "⚠️  Cache clear warning"
    php artisan view:clear > /dev/null 2>&1 || echo "⚠️  View clear warning"
    php artisan config:clear > /dev/null 2>&1 || echo "⚠️  Config clear warning"
fi

echo "🔑 Setting permissions..."
PLUGIN_DIR="/opt/librenms/html/plugins/WeathermapNG"
if [ -d "$PLUGIN_DIR" ]; then
    chown -R librenms:librenms "$PLUGIN_DIR" 2>/dev/null || echo "⚠️  Permission warning (may need manual fix)"
    chmod -R 755 "$PLUGIN_DIR/resources/views" 2>/dev/null || true
    OUTPUT_DIR="$PLUGIN_DIR/../output"
    if [ -d "$OUTPUT_DIR" ]; then
        chmod -R 775 "$OUTPUT_DIR" 2>/dev/null || true
    fi
else
    echo "⚠️  Warning: Plugin directory not found at $PLUGIN_DIR"
fi

echo ""
echo "✅ Installation complete!"
echo "=============================="
echo ""
echo "🌐 Visit: https://your-server/plugin/WeathermapNG"
echo ""
echo "📋 Next steps:"
echo "   1. Check plugin is enabled: ./lnms plugin:list"
echo "   2. Enable if needed: ./lnms plugin:enable WeathermapNG"
echo "   3. Visit web interface: /plugin/WeathermapNG"
echo ""
echo "📖 Troubleshooting: https://github.com/lance0/weathermapNG/blob/main/INSTALL.md"
echo "💡 Support: https://community.librenms.org/c/plugins/15"
echo ""