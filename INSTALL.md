# Detailed Installation Guide

## Prerequisites

Before installing WeathermapNG, ensure your environment meets these requirements:

- **LibreNMS**: latest stable release, fully installed and working
- **PHP**: 8.2 or newer (same version LibreNMS uses)
- **Composer**: installed and available on `$PATH`
- **Database**: MySQL or MariaDB using the existing LibreNMS database
- **PHP extensions**: everything LibreNMS requires, plus `gd`, `json`, and `mbstring`
- **RRD access**: the LibreNMS runtime user must be able to read LibreNMS RRD files (WeathermapNG reads traffic data directly from RRDs)
- **File ownership**: plugin files must be owned by the `librenms` user

WeathermapNG is a LibreNMS v2 Composer-discovered plugin. It does not use the legacy manifest or root-level route registration model.

## Automated Install (Recommended)

For most users, use the automated installer:

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
sudo -u librenms -H bash -lc 'cd /opt/librenms/html/plugins/WeathermapNG && ./quick-install.sh'
```

Run the installer as the `librenms` user, not root. Running as root can leave root-owned Composer files in the plugin directory and cause later installs to fail with permission errors such as `file_put_contents(./composer.lock): Failed to open stream: Permission denied`.

On native installs, `quick-install.sh` refuses to run as root and exits with a clear message. In Docker (where the entrypoint usually runs as root), the installer automatically drops privileges to the `librenms` user before any `composer require`, `artisan`, or `lnms` call that targets the LibreNMS install. This is the v1.7.0+ behavior — you no longer need to pre-switch users inside the container.

The installer performs:

- Installs Composer dependencies
- Registers the plugin as a Composer path package from the LibreNMS root
- Runs package discovery
- Creates or updates WeathermapNG database tables
- Clears Laravel caches
- Creates required output directories
- Enables the LibreNMS plugin
- Verifies WeathermapNG routes are visible

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

### Development Environment

The repository ships a `docker-compose.dev.yml` that brings up a full LibreNMS stack (MariaDB, Redis, LibreNMS, and a sidecar dispatcher) with the plugin bind-mounted for live editing and demo mode enabled by default:

```bash
docker compose -f docker-compose.dev.yml up -d
docker compose -f docker-compose.dev.yml logs -f librenms
```

When the logs show `nginx entered RUNNING state`, visit http://localhost:8000. This stack is intended for development and testing, not production.

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

## Demo Mode

Demo mode generates simulated traffic data so you can exercise the UI without real LibreNMS devices or port associations. It is useful for:

- Testing the plugin on a fresh install before any devices are added
- Development and UI work
- Demonstrations and screenshots

### How v1.7.0+ Demo Traffic Works

Demo traffic is **deterministic**, not random. Each link and node derives its utilization from a sine wave seeded by its own database id, so:

- The same link always shows the same shape of traffic at the same moment
- Traffic is smooth and jitter-free (no per-request `rand()` jumps)
- Different links and nodes show different loads (because each id seeds a different phase and base)
- Values still drift over time (a ~30-second modulation period) for a "live" feel
- In/out bandwidth differ per link, like real asymmetric traffic

This means demo mode is reproducible: refreshing a map or replaying a scenario gives the same values for the same timestamps, which makes it usable for testing and screenshots.

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
- Links without `port_id_a`/`port_id_b` get deterministic simulated traffic (10–85% utilization band, sine-modulated)
- Flow animations work with the simulated data
- Node status remains "unknown" unless connected to real devices

### Create a Demo Map

The `database/seed-demo.php` script creates a ready-made sample topology so you can see demo mode immediately without building a map by hand:

```bash
php database/seed-demo.php
```

This creates a `demo-network` map with 8 nodes and 8 links representing a typical network topology. Run it from the plugin directory (or anywhere with LibreNMS on the default path — it auto-detects `/opt/librenms` and `$LIBRENMS_PATH`). The `docker-compose.dev.yml` development stack has demo mode enabled by default, so seeded maps animate as soon as you load them.

## Background Poller (Optional)

WeathermapNG renders live data from LibreNMS RRD files on demand from the web views. The optional poller script runs scheduled background work for environments that want pre-rendered or polled data. It lives at `bin/map-poller.php` (there is no root-level `map-poller.php` — that file was removed).

Add it to cron:

```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

Or, with logging:

```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1
```

Use the poller only if it fits your deployment model. The web views and live endpoints work without it.

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

### LibreNMS validate.php reports utf8mb4_bin collation on JSON columns
MySQL and MariaDB store JSON-backed columns with binary JSON comparison semantics, so LibreNMS may report `utf8mb4_bin` collation warnings for these columns:

- `wmng_map_templates.config`
- `wmng_nodes.meta`
- `wmng_maps.options`
- `wmng_links.style`

These columns are expected for WeathermapNG. Do not alter them unless a future WeathermapNG release includes a migration for that change.

### Duplicate WeathermapNG rows in the plugins table
Both `quick-install.sh` and `database/setup.php` normalize LibreNMS plugin registration. If an older install left a duplicate `WeathermapNG` row in the LibreNMS `plugins` table (e.g. a legacy `version=1` row alongside a `version=2` row), rerun `quick-install.sh` or `php database/setup.php` as the `librenms` user. The normalizer keeps one active `version=2` row, promotes any legacy `version=1` row to `version=2`, and removes stale duplicates.

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

The directory name MUST be `WeathermapNG` (case-sensitive). On case-insensitive filesystems a differently-cased clone can appear to work locally and then fail when deployed to a case-sensitive Linux server, so keep the exact casing `WeathermapNG`.

## LibreNMS v2 Plugin Architecture

This plugin follows the LibreNMS v2 architecture:
- Service provider in `src/WeathermapNGProvider.php`
- Hooks implement interfaces (MenuEntryHook, SettingsHook)
- Views use namespace `WeathermapNG::`
- Routes under `/plugin/WeathermapNG`
- Uses Composer package discovery rather than the legacy plugin manifest

## Authorization Model (v1.7.0+)

All WeathermapNG routes sit under LibreNMS `web` + `auth` middleware except the three public probe endpoints (`/health`, `/ready`, `/live`), which are intentionally unauthenticated for use by external health checks.

Within the authenticated surface, two tiers apply:

- **Read endpoints** — open to any authenticated LibreNMS user: viewing maps, the editor view, embed, JSON/export, live data, SSE stream, device/port lookups, template listings, and health/metrics detail.
- **Mutation endpoints** — require an admin user (`hasGlobalAdmin()`, `isAdmin()`, `level >= 10`, or `hasRole('admin')`): creating, updating, or deleting maps, nodes, and links; saving a map; importing a map; auto-discovery; creating, updating, or deleting templates; creating a map from a template; and running the install controller.

This replaces the older per-map policy model. The `MapPolicy` and `NodePolicy` classes were removed in v1.7.0; authorization is now enforced at the controller level using LibreNMS's global admin check. There is no per-map ownership configuration to maintain.

## Updating

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev
php database/setup.php
cd /opt/librenms
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan config:clear
php artisan view:clear
```

### Upgrade safety

**Database tables.** The `database/setup.php` migration creates any missing `wmng_*` tables and adds any missing columns to existing ones (e.g. `ALTER TABLE wmng_maps ADD COLUMN title`). It never drops tables, columns, or map data — existing maps, nodes, links, templates, and versions are preserved across upgrades. The only destructive write is to the LibreNMS `plugins` table, where stale duplicate `WeathermapNG` rows are removed by the normalizer (see below).

**Config files.** WeathermapNG stores all configuration in the LibreNMS `plugins` table row (plugin name, version, status). There are no separate config files to migrate. If you previously edited a legacy config file from an older plugin version, those settings are not carried forward — reconfigure via Settings → WeathermapNG.

**Output directories.** The `resources/output/` directory (if present from an older install) is unused by v1.7.x and can be safely removed. WeathermapNG generates PNG/SVG output on-demand via the render controller; no scheduled weathermap process is required.

**Duplicate plugin rows.** If you upgraded from a v1.x install, you may have a legacy `version=1` row alongside a `version=2` row in the LibreNMS `plugins` table. Both `quick-install.sh` and `database/setup.php` normalize this automatically as of v1.7.2. See [Duplicate WeathermapNG rows](#duplicate-weathermapng-rows-in-the-plugins-table) below.

**Composer registration.** If `composer require` fails with a class-not-found error, run `php artisan package:discover` and `php artisan optimize:clear` to re-register the plugin's service provider and clear cached route/config/view bindings.

**Route discovery.** If the "Network Maps" menu entry is missing after upgrade, run `php artisan route:list | grep -iE 'weathermap|wmng'` to verify routes are registered. If no routes appear, ensure the plugin is enabled (`./lnms plugin:enable WeathermapNG`) and `package:discover` has been run.

**Rollback.** To roll back to a previous version, `git checkout <tag>` in the plugin directory and re-run `composer install --no-dev && php artisan package:discover && php artisan optimize:clear`. Database tables from the newer version remain but are harmless — no down migrations are needed.

## Diagnostics Page

Administrators can open **Network Maps → Diagnostics** (or visit `/plugin/WeathermapNG/diagnostics`) to see a consolidated operational status panel:

- Overall health status and plugin version
- Map/node/link counts and estimated DB size
- Per-check results for database, filesystem, dependencies, configuration, and performance
- Registered-route status
- Writable-path checks

Use the diagnostics page as the first stop when something feels broken. If a route is shown as **Missing**, run `php artisan package:discover` and `php artisan route:clear`. If the database check fails, re-run `database/setup.php`. If a path is not writable, check that the plugin directory and `output/maps/` are owned by the LibreNMS runtime user.

## Verifying Installation

1. Check the menu for "Network Maps" entry
2. Visit `/plugin/WeathermapNG`
3. As an admin, visit **Network Maps → Diagnostics** and confirm all checks are healthy
4. Run `php artisan route:list | grep -iE 'weathermap|wmng'`
5. Try creating a test map
