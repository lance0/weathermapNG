# Installation Guide

## Quick Install (2 minutes)

```bash
# 1. Clone the plugin
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG

# 2. Install dependencies
cd WeathermapNG
composer install --no-dev

# 3. Clear caches (IMPORTANT!)
cd /opt/librenms
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. Set permissions
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

Visit: `https://your-server/plugin/WeathermapNG`

## Common Issues & Solutions

### "Missing view" or "View not found" errors
This is the most common issue. LibreNMS caches views aggressively.

**Solution:**
```bash
cd /opt/librenms
php artisan view:clear
php artisan cache:clear
```

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
- Uses composer autoloading (no plugin.json)

## Updating

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev
cd /opt/librenms
php artisan view:clear
```

## Verifying Installation

1. Check the menu for "Network Maps" entry
2. Visit `/plugin/WeathermapNG`
3. Try creating a test map

## Background Poller (Optional)

For automatic data updates, add to cron:
```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```