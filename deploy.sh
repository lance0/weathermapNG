#!/bin/bash
# WeathermapNG Deployment/Update Script
# Use this to update an existing installation

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}WeathermapNG Deployment${NC}"
echo "========================"

# Detect environment
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
LIBRENMS_PATH="${LIBRENMS_PATH:-/opt/librenms}"

# Check if we're in the right directory
if [ ! -f "$PLUGIN_DIR/composer.json" ]; then
    echo "Error: Not in WeathermapNG directory"
    exit 1
fi

# Find LibreNMS
if [ ! -d "$LIBRENMS_PATH" ]; then
    for path in /opt/librenms /usr/local/librenms /data; do
        if [ -f "$path/artisan" ]; then
            LIBRENMS_PATH="$path"
            break
        fi
    done
fi

echo "LibreNMS: $LIBRENMS_PATH"
echo "Plugin: $PLUGIN_DIR"
echo ""

# 1. Pull latest changes (if git repo)
echo -e "${GREEN}[1/5]${NC} Pulling latest changes..."
cd "$PLUGIN_DIR"
if [ -d ".git" ]; then
    git pull || echo -e "${YELLOW}Not a git repo or pull failed, skipping...${NC}"
else
    echo "  Not a git repo, skipping..."
fi

# 2. Install dependencies
echo -e "${GREEN}[2/5]${NC} Installing dependencies..."
composer install --no-dev --optimize-autoloader

# 3. Run database setup (handles migrations and updates)
echo -e "${GREEN}[3/5]${NC} Updating database..."
php database/setup.php

# 4. Clear caches
echo -e "${GREEN}[4/5]${NC} Clearing caches..."
if [ -f "$LIBRENMS_PATH/artisan" ]; then
    cd "$LIBRENMS_PATH"
    php artisan cache:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
    php artisan config:clear 2>/dev/null || true
else
    echo -e "${YELLOW}  Artisan not found, skipping cache clear${NC}"
fi

# 5. Set permissions (if not in Docker)
echo -e "${GREEN}[5/5]${NC} Setting permissions..."
if [ -f "/.dockerenv" ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
    echo "  Skipped in Docker"
else
    cd "$PLUGIN_DIR"
    chown -R librenms:librenms . 2>/dev/null || echo -e "${YELLOW}  Could not set ownership (try with sudo)${NC}"
fi

echo ""
echo -e "${GREEN}Deployment complete!${NC}"
echo ""
echo "Visit: https://your-server/plugin/WeathermapNG"
echo ""
echo "If you see errors, try:"
echo "  cd $LIBRENMS_PATH && php artisan view:clear"
