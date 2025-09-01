#!/bin/bash
# WeathermapNG Deployment Script
# Simple deployment for LibreNMS v2 plugin

set -e  # Exit on error

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}WeathermapNG Deployment${NC}"
echo "========================"

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "Error: Not in WeathermapNG directory"
    exit 1
fi

# 1. Pull latest changes
echo -e "${GREEN}[1/5]${NC} Pulling latest changes..."
git pull || echo "Not a git repo, skipping..."

# 2. Install dependencies
echo -e "${GREEN}[2/5]${NC} Installing dependencies..."
composer install --no-dev --optimize-autoloader

# 3. Clear LibreNMS caches
echo -e "${GREEN}[3/5]${NC} Clearing caches..."
cd /opt/librenms
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear

# 4. Set permissions
echo -e "${GREEN}[4/5]${NC} Setting permissions..."
cd /opt/librenms/html/plugins/WeathermapNG
chown -R librenms:librenms .
chmod -R 755 .

# 5. Run migrations if needed
echo -e "${GREEN}[5/5]${NC} Checking database..."
cd /opt/librenms
php artisan migrate --path=html/plugins/WeathermapNG/database/migrations --force 2>/dev/null || echo "Migrations already run"

echo -e "${GREEN}✓ Deployment complete!${NC}"
echo ""
echo "Visit: https://your-server/plugin/WeathermapNG"
echo ""
echo "If you see errors, run:"
echo "  cd /opt/librenms"
echo "  php artisan view:clear"