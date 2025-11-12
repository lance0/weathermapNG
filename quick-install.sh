#!/bin/bash
# WeathermapNG Quick Install
# Simple one-command installation

set -e

echo "🚀 WeathermapNG Quick Install"
echo "=============================="

# Check if we're in the plugin directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Please run this from the WeathermapNG plugin directory"
    echo "   cd /opt/librenms/html/plugins/WeathermapNG"
    exit 1
fi

echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --quiet

echo "🗄️  Setting up database..."
php database/setup.php > /dev/null 2>&1 || echo "⚠️  Database setup may need manual attention"

echo "🔧 Configuring LibreNMS..."
cd /opt/librenms
php artisan cache:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1
php artisan config:clear > /dev/null 2>&1

echo "🔑 Setting permissions..."
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG 2>/dev/null || true

echo "✅ Installation complete!"
echo ""
echo "🌐 Visit: https://your-server/plugin/WeathermapNG"
echo ""
echo "📖 If you encounter issues, see: https://github.com/lance0/weathermapNG/blob/main/INSTALL.md"