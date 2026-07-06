# WeathermapNG Deployment Guide

This guide covers production deployment and maintenance for WeathermapNG `v1.7.0+`.

For a first install, start with [INSTALL.md](INSTALL.md). This document focuses on production checks, Docker notes, monitoring, backup, and recovery.

## Requirements

- LibreNMS latest stable release
- PHP 8.2 or newer
- Composer
- MySQL or MariaDB using the existing LibreNMS database
- PHP extensions required by LibreNMS plus `gd`, `json`, and `mbstring`
- Read access to LibreNMS RRD files
- Write access to WeathermapNG output directories

WeathermapNG is a LibreNMS v2 Composer-discovered plugin. It does not use the legacy manifest or root-level route registration model.

## Production Install Flow

The supported install flow is:

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
sudo -u librenms -H bash -lc 'cd /opt/librenms/html/plugins/WeathermapNG && ./quick-install.sh'
```

The installer performs the important steps:

- Installs Composer dependencies
- Registers the plugin as a Composer path package from the LibreNMS root
- Runs package discovery
- Creates or updates WeathermapNG database tables
- Clears Laravel caches
- Creates required output directories
- Enables the LibreNMS plugin
- Verifies WeathermapNG routes are visible

## Manual Production Flow

Use this when you need more control than `quick-install.sh` gives you:

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG
composer install --no-dev --optimize-autoloader

cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover

cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php

cd /opt/librenms
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
./lnms plugin:enable WeathermapNG
php artisan route:list | grep -iE 'weathermap|wmng'
```

## Docker Deployment

Mount the plugin into the LibreNMS container at the expected plugin path:

```yaml
services:
  librenms:
    volumes:
      - /path/to/weathermapNG:/opt/librenms/html/plugins/WeathermapNG:rw
```

Then run setup as the `librenms` user inside the container:

```bash
docker exec -u librenms <container> composer install -d /opt/librenms/html/plugins/WeathermapNG --no-dev --optimize-autoloader
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && composer config repositories.weathermapng "{\"type\":\"path\",\"url\":\"html/plugins/WeathermapNG\",\"options\":{\"symlink\":true}}"'
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && FORCE=1 composer require "librenms/weathermapng:*" --with-dependencies --no-interaction'
docker exec -u librenms <container> php /opt/librenms/artisan package:discover
docker exec -u librenms <container> php /opt/librenms/html/plugins/WeathermapNG/database/setup.php
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && php artisan optimize:clear && php artisan route:clear && php artisan view:clear && php artisan config:clear && php artisan cache:clear'
docker exec -u librenms <container> /opt/librenms/lnms plugin:enable WeathermapNG
docker exec -u librenms <container> bash -lc 'cd /opt/librenms && php artisan route:list | grep -iE "weathermap|wmng"'
```

Always run these commands as the same user LibreNMS uses. Avoid running setup as root unless you immediately repair ownership.

## Upgrade Flow

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev --optimize-autoloader
php database/setup.php

cd /opt/librenms
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:list | grep -iE 'weathermap|wmng'
```

Read [CHANGELOG.md](CHANGELOG.md) before upgrading across minor versions. Do not drop `wmng_*` tables unless you intend to remove WeathermapNG data.

## Health And Readiness

Public probe endpoints:

```bash
curl -f https://librenms.example.com/plugin/WeathermapNG/health
curl -f https://librenms.example.com/plugin/WeathermapNG/ready
curl -f https://librenms.example.com/plugin/WeathermapNG/live
```

Authenticated admin/detail endpoints:

- `/plugin/WeathermapNG/health/stats`
- `/plugin/WeathermapNG/health/detailed`
- `/plugin/WeathermapNG/metrics`

The detail endpoints can expose operational information, so they are intentionally behind LibreNMS authentication.

## Verification Checklist

After install or upgrade:

1. Run `php artisan route:list | grep -iE 'weathermap|wmng'` from `/opt/librenms`.
2. Confirm the LibreNMS menu shows WeathermapNG or Network Maps.
3. Visit `/plugin/WeathermapNG`.
4. Create or open a map.
5. Open the editor.
6. Open the embed view.
7. Check `/plugin/WeathermapNG/ready`.
8. Run LibreNMS `validate.php`; ignore `wmng_*` extra-table warnings unless you are uninstalling.
9. If `validate.php` reports `utf8mb4_bin` collation on `wmng_map_templates.config`, `wmng_nodes.meta`, `wmng_maps.options`, or `wmng_links.style`, treat that as expected JSON-column behavior.

If an older deployment shows duplicate `WeathermapNG` rows in the LibreNMS `plugins` table, rerun `quick-install.sh` as the `librenms` user. The installer keeps one active row and removes stale duplicates.

## Background Poller

WeathermapNG can render live data from LibreNMS RRD files on demand. The optional poller script remains available for environments that want scheduled background work:

```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1
```

Use the poller only if it fits your deployment model. The web views and live endpoints should still be validated separately.

## Backup And Recovery

Back up the WeathermapNG tables with the rest of the LibreNMS database:

```bash
mysqldump -u librenms -p librenms \
  wmng_maps wmng_nodes wmng_links wmng_map_templates wmng_map_versions \
  > weathermapng.sql
```

Back up generated output if you rely on exported images or thumbnails:

```bash
tar -czf weathermapng-output.tgz /opt/librenms/html/plugins/WeathermapNG/output
```

Recovery is the reverse:

```bash
mysql -u librenms -p librenms < weathermapng.sql
tar -xzf weathermapng-output.tgz -C /
cd /opt/librenms
php artisan optimize:clear
php artisan package:discover
```

## Troubleshooting

### Routes Missing

If `/plugin/WeathermapNG` loads partially or routes such as editor/API/health are missing, Composer package discovery did not see the plugin:

```bash
cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan route:list | grep -iE 'weathermap|wmng'
```

### Permission Problems

```bash
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
find /opt/librenms/html/plugins/WeathermapNG/bin -type f -name '*.php' -exec chmod +x {} \;
```

### Database Setup Problems

```bash
cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php
```

`database/setup.php` is the supported setup path for plugin tables. Do not use Laravel's application migration commands for plugin table setup unless a future release explicitly documents that as the supported install path.

### RRD Or Traffic Problems

- Verify the link has valid LibreNMS port associations.
- Check that the LibreNMS user can read the RRD directory.
- Use demo mode to separate UI problems from data-source problems.
- Confirm the map renders before debugging flow animation or labels.

## Security Notes

- Keep WeathermapNG under LibreNMS authentication for editor, map management, metrics, and detailed health data.
- Public health endpoints should remain minimal.
- Keep file ownership aligned with the LibreNMS runtime user.
- Do not expose backup files, `.env`, logs, or SQL dumps under the web root.

### Authorization Model

As of v1.7.0, WeathermapNG enforces a two-tier authorization model on top of LibreNMS `web` + `auth` middleware:

- **Read endpoints** are open to any authenticated LibreNMS user. This includes viewing maps and the editor, embed, JSON and image export, the live data endpoint, the SSE stream, device/port lookups, template listings, and the `health/detailed`, `health/stats`, and `metrics` endpoints.
- **Mutation endpoints** require an admin user — `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`. This covers creating, updating, and deleting maps, nodes, and links; saving a map; importing a map; running auto-discovery; creating, updating, and deleting templates; creating a map from a template; and running the install controller.

The three public probe endpoints — `/health`, `/ready`, and `/live` — are intentionally unauthenticated so external health checks can reach them.

This model replaces the older per-map policy approach. The `MapPolicy` and `NodePolicy` classes were removed in v1.7.0; authorization is now enforced at the controller level using LibreNMS's global admin check, and there is no per-map ownership configuration to maintain.

## Compatibility

| WeathermapNG | LibreNMS | PHP | Database |
|--------------|----------|-----|----------|
| 1.7.0+ | Latest stable recommended | 8.2+ | LibreNMS MySQL/MariaDB database |

PostgreSQL is not currently documented as a supported production target for this plugin.
