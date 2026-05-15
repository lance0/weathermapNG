# Detailed Installation Guide

## Automated Install (Recommended)

For most users, use the automated installer:

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
sudo -u librenms -H bash -lc 'cd /opt/librenms/html/plugins/WeathermapNG && ./quick-install.sh'
```

Run the installer as the `librenms` user, not root. Running as root can leave root-owned Composer files in the plugin directory and cause later installs to fail with permission errors such as `file_put_contents(./composer.lock): Failed to open stream: Permission denied`.

## Docker Installation

If you're running LibreNMS in Docker, mount the plugin into the container:

### Docker Compose

Add these volumes to your LibreNMS service in `docker-compose.yml`:

```yaml
services:
  librenms:
    # ... existing config ...
    volumes:
      - librenms_data:/data
      - /path/to/weathermapNG:/opt/librenms/html/plugins/WeathermapNG:rw
```

Then run setup inside the container:

```bash
# Install dependencies
docker exec -u librenms <container_name> composer install -d /opt/librenms/html/plugins/WeathermapNG --no-dev

# Register the plugin package with LibreNMS Composer
docker exec -u librenms <container_name> bash -lc 'cd /opt/librenms && composer config repositories.weathermapng "{\"type\":\"path\",\"url\":\"html/plugins/WeathermapNG\",\"options\":{\"symlink\":true}}"'
docker exec -u librenms <container_name> bash -lc 'cd /opt/librenms && FORCE=1 composer require "librenms/weathermapng:*" --with-dependencies --no-interaction'
docker exec -u librenms <container_name> php /opt/librenms/artisan package:discover

# Setup database
docker exec -u librenms <container_name> php /opt/librenms/html/plugins/WeathermapNG/database/setup.php

# Enable plugin
docker exec -u librenms <container_name> /opt/librenms/lnms plugin:enable WeathermapNG

# Clear caches and verify routes
docker exec -u librenms <container_name> php /opt/librenms/artisan optimize:clear
docker exec -u librenms <container_name> php /opt/librenms/artisan route:clear
docker exec -u librenms <container_name> php /opt/librenms/artisan cache:clear
docker exec -u librenms <container_name> php /opt/librenms/artisan view:clear
docker exec -u librenms <container_name> php /opt/librenms/artisan config:clear
docker exec -u librenms <container_name> bash -lc 'cd /opt/librenms && php artisan route:list | grep -iE "weathermap|wmng"'
```

**Important**: Always run commands as the `librenms` user (`-u librenms`), not root.

**View Docker Logs**:
```bash
docker compose logs librenms
```

## Manual Installation

If you prefer manual control or the automated script doesn't work:

### 1. Clone and Dependencies
```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG
composer install --no-dev --optimize-autoloader
```

### 2. Register Composer Package
LibreNMS must know about the package before Laravel can discover the service provider, routes, and views:

```bash
cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
```

### 3. Database Setup
```bash
cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php
```

### 4. LibreNMS Configuration
```bash
cd /opt/librenms
php artisan optimize:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
php artisan config:clear
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

### 5. Enable Plugin
```bash
./lnms plugin:enable WeathermapNG
```

### 6. Verify Routes
```bash
php artisan route:list | grep -iE 'weathermap|wmng'
```

If this returns nothing, repeat the Composer registration step from the LibreNMS root and clear caches again.

### 7. Setup Background Polling (Optional)
```bash
# Add to cron for automatic updates
echo "*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php" | sudo tee -a /etc/cron.d/librenms
```

## Common Issues & Solutions

### "Missing view" or "View not found" errors
This is the most common issue. LibreNMS caches views aggressively.

**Solution:**
```bash
cd /opt/librenms
php artisan view:clear
php artisan cache:clear
```

### Plugin page loads but routes are missing
If `/plugin/WeathermapNG` responds but editor, API, health, or ready routes are missing, Laravel did not discover the Composer package.

**Check:**
```bash
cd /opt/librenms
php artisan route:list | grep -iE 'weathermap|wmng'
```

**Solution:**
```bash
cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
```

### LibreNMS validate.php reports extra wmng_* tables
WeathermapNG creates `wmng_*` tables for maps, nodes, links, templates, and versions. If LibreNMS `validate.php` reports these as extra tables and offers to drop them, answer `n`.

### "Interface PageHook not found" error
LibreNMS v2 doesn't support PageHook. This plugin uses MenuEntryHook and SettingsHook only.

**Solution:** Make sure you have the latest code with `git pull`

### Bootstrap/UI issues
LibreNMS uses Bootstrap 4, not Bootstrap 5.

**Solution:** The latest code has been updated for Bootstrap 4 compatibility.

### Parse errors in Blade templates
Usually caused by incorrect @json() directive usage.

**Solution:** Update to latest code which fixes these issues.

## Directory Structure

The plugin MUST be in this exact location:
```
/opt/librenms/html/plugins/WeathermapNG/
```

The directory name MUST be `WeathermapNG` (case-sensitive).

## LibreNMS v2 Plugin Architecture

This plugin follows the LibreNMS v2 architecture:
- Service provider in `src/WeathermapNGProvider.php`
- Hooks implement interfaces (MenuEntryHook, SettingsHook)
- Views use namespace `WeathermapNG::`
- Routes under `/plugin/WeathermapNG`
- Uses Composer package discovery rather than the legacy plugin manifest

## Updating

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev
cd /opt/librenms
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan config:clear
php artisan view:clear
```

## Verifying Installation

1. Check the menu for "Network Maps" entry
2. Visit `/plugin/WeathermapNG`
3. Run `php artisan route:list | grep -iE 'weathermap|wmng'`
4. Try creating a test map

## Background Poller (Optional)

For automatic data updates, add to cron:
```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

## Demo Mode

Demo mode generates simulated traffic data for links without real port associations. This is useful for:
- Testing the plugin without real LibreNMS devices
- Development and UI testing
- Demonstrations and screenshots

### Enable Demo Mode

**Option 1: Environment Variable**
```bash
# Add to LibreNMS .env file
WEATHERMAPNG_DEMO_MODE=true

# Or for Docker
docker exec -u librenms <container> bash -c 'echo "WEATHERMAPNG_DEMO_MODE=true" >> /opt/librenms/.env'
```

**Option 2: Config Override**

Create or edit `/opt/librenms/html/plugins/WeathermapNG/config/config.php`:
```php
<?php
return [
    'demo_mode' => true,
    // ... other settings
];
```

In demo mode:
- Links without port_id_a/port_id_b get randomized traffic (10-85% utilization)
- Flow animations work with simulated data
- Node status remains "unknown" unless connected to real devices

### Create Demo Map

To quickly create a sample network topology for testing:

```bash
php database/seed-demo.php
```

This creates a "demo-network" map with 8 nodes and 8 links representing a typical network topology.
