# Install, upgrade, and recover WeathermapNG

This is the canonical guide for **v1.15.1**. Use the tagged release for a stable
installation; following `main` is an explicit development choice. WeathermapNG is
a LibreNMS v2 Composer-discovered plugin, served at `/plugin/WeathermapNG`.

Requirements: an initialized LibreNMS MySQL/MariaDB installation, PHP 8.2+ with
`gd`, `json`, `pdo`, and `mbstring`, Composer, readable RRD files, and a checkout
writable by the LibreNMS runtime user. Also meet your LibreNMS release's PHP
minimum: the tested LibreNMS 26.10.2 release supports PHP 8.4 and 8.5. Examples use `/opt/librenms` and user
`librenms`; substitute your actual installation root and runtime user.

## Native stable installation

Open a shell as the LibreNMS user, with that user's normal home:

```bash
sudo -u librenms -H bash
export LIBRENMS_PATH=/opt/librenms
cd "$LIBRENMS_PATH/html/plugins"
git clone --branch v1.15.1 --depth 1 https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG
./quick-install.sh
php verify-deployment.php --json
exit
```

The parent plugin directory must already permit the runtime user to create the
checkout. For a downloaded release archive, extract its `WeathermapNG/` directory
there and assign that directory to the runtime user before running the same
installer. Native root execution is refused. In Docker, root execution drops the
whole installer to `librenms` before dependency or database writes.

`LIBRENMS_PATH` selects the installation; an invalid explicit root fails instead
of falling back to another database. The installer runs required dependencies,
registration, discovery, schema setup, enablement, cache clearing, output setup,
and the final report. Any required failure exits nonzero with its step and retry
command. From the checkout, as the same runtime user, `just install /opt/librenms`
wraps this script; recipe arguments are positional.

The supported registration contract is
`composer config --global repositories.weathermapng` with the absolute checkout
path, followed by `lnms plugin:add librenms/weathermapng` from the LibreNMS root.
The installer manages this one per-user repository key and the obsolete local
key, preserving unrelated repositories. LibreNMS records the requirement in
`composer.plugins.json`. Keep installation and LibreNMS updates under the same
runtime user/home so the repository remains discoverable. Do not replace this
flow with a root-only `composer require` or manual database inserts.

After success, log in to LibreNMS and open `/plugin/WeathermapNG`, then open an
existing map in the editor and embed viewer. CLI success cannot verify PHP-FPM
opcode freshness or authenticated browser access.

## Docker stable installation

From a host checkout of the selected release:

```bash
git clone --branch v1.15.1 --depth 1 https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG
# Match ownership to PUID/PGID in the Compose file (defaults: 1000:1000).
sudo chown -R 1000:1000 .
docker compose -f docker-compose.simple.yml up -d db redis librenms
docker compose -f docker-compose.simple.yml logs -f librenms
```

Customize the supplied database credentials, exposed port, and image version for
your environment before starting. The simple/dev files run from the checkout
root. `docker-compose.example.yml` instead belongs in the **parent** directory and
mounts `./WeathermapNG` plus its hook. It preserves its existing MariaDB 10.5 image,
`librenms_password` example credentials, and `librenms_db`/`librenms_rrd`/
`librenms_logs` volumes, with `/data` persisted separately. Preserve the original
Compose project/volume mapping when upgrading an existing example deployment;
switching to simple/dev is not an interchangeable invocation.
`docker-compose.dev.yml` supplies test-only environment values.

The app mounts the checkout at `/opt/librenms/html/plugins/WeathermapNG` and the
executable `docker/99-weathermapng.sh` at
`/etc/cont-init.d/99-weathermapng.sh:ro`. Keep the official image entrypoint and
`S6_BEHAVIOUR_IF_STAGE2_FAILS=2`. The late hook runs the common installer after
upstream configuration/schema initialization; a required failure stops startup
before nginx/PHP-FPM serve the plugin. Mount source and hook only in the app;
dispatchers must not concurrently install into the checkout.

Preserve the database volume, `/data` volume, and host checkout across app
recreation. `/data` contains LibreNMS persistent configuration, logs, and RRD data.
Do not persist the whole vendor tree or root Composer files. Registration is
rebuilt by the hook each time. For the simple Compose file, inspect the result:

```bash
docker exec -u librenms librenms-weathermap php /opt/librenms/html/plugins/WeathermapNG/verify-deployment.php --json
```

Never use `docker compose down -v` when retaining data. The image's default file
sessions live in its ephemeral filesystem; users may need to log in again after
recreation. Accounts, maps, templates, saved versions, and settings remain in the
database. Recheck both editor and embed access after logging in.

## Configuration and precedence

Package defaults live in `WeathermapNG/config/config.php`. Environment variables
supply values referenced by that file. An optional published override lives at
**`/opt/librenms/config/weathermapng.php`**, in the LibreNMS root, not in the plugin
checkout. After installation, open a runtime-user shell to publish it if needed:

```bash
sudo -u librenms -H bash
cd /opt/librenms
php artisan vendor:publish --provider='LibreNMS\Plugins\WeathermapNG\WeathermapNGProvider'
php artisan config:clear
exit
```

The provider merges package defaults with the application configuration, then
overlays known keys from the active plugin's `plugins.settings` JSON. Scalars
replace existing values; array values merge one level. Saved settings therefore
win over the corresponding file/environment values. Unknown saved keys are
ignored. Use the admin settings form for supported settings and keep secrets out
of support reports. After environment/file changes, clear application config
caches as the runtime user; refresh web workers through your normal PHP-FPM
restart procedure, or recreate the Docker app.

In the official Docker image, store custom variables in **`/data/.env`**, preserving
its `APP_KEY` and `NODE_ID`. Startup generates `/opt/librenms/.env` and then appends
`/data/.env`. For example, add `WEATHERMAPNG_EMBED_TOKEN=<your-token>` and, when
wanted, `WEATHERMAPNG_DEMO_MODE=true` to the persistent file. Restrict access to
it and recreate the app. A token supplied only as a Docker environment variable
may appear in CLI configuration while PHP-FPM returns 403 because its environment
is cleared. Verify the real HTTP endpoint after recreation. A token is optional
for normal LibreNMS session access; see [embedding](EMBED.md) for external viewers.

## Updating

Back up first as described below. A LibreNMS update and a plugin upgrade are
separate operations:

- LibreNMS's native daily updater resets root Composer files and restores plugin
  requirements from `composer.plugins.json`, using the runtime user's global path
  repository. It does not select a newer plugin release.
- A plugin upgrade selects new source, then runs the common installer. Schema
  setup preserves existing data and normalizes duplicate plugin rows, retaining
  the selected active v2 row and its settings.

For a native Git checkout pinned to a tag, select the next reviewed release as
`librenms`, then install that selected source:

```bash
sudo -u librenms -H bash
export LIBRENMS_PATH=/opt/librenms
cd "$LIBRENMS_PATH/html/plugins/WeathermapNG"
git diff --quiet
git diff --cached --quiet
git fetch origin tag v1.15.1
git checkout --detach v1.15.1
./deploy.sh
exit
```

Detached tags and archive checkouts retain their selected source. A tracked
branch with an upstream uses `git pull --ff-only`; dirty tracked files, a missing
upstream, or a failed pull stop before installation. Keep operator configuration
in the root published config or persistent environment, outside tracked source.
For an archive upgrade, replace source with the selected release while retaining
operator-owned data/output, then run `quick-install.sh`; do not extract unrelated
or older source over a running deployment.

For Docker, select the desired tag in the host checkout, then recreate only the
app with the same Compose project, volumes, source/hook mounts, and image:

```bash
docker compose -f docker-compose.simple.yml up -d --no-deps --force-recreate librenms
```

The hook installs the selected source. Check the report, log in again if needed,
and verify editor/embed access. To follow `main` deliberately, clone with
`--branch main` instead of the release tag; use `deploy.sh` for native updates or
fast-forward the host checkout and recreate the Docker app. `main` can contain
unreleased changes.

## Diagnostics and targeted recovery

Run the read-only report as the runtime user from the plugin checkout:

```bash
LIBRENMS_PATH=/opt/librenms php verify-deployment.php --json
```

The admin diagnostics page `/plugin/WeathermapNG/diagnostics` provides the same
redacted report. It checks the selected root's requirement/registry, actual loaded
provider and runtime routes, plugin enablement, database/table access, required
extensions, and read/write permissions. Unavailable counts are `null`; an empty
table is zero. It does not repair files, expose configuration values, or establish
web worker freshness/authenticated HTTP access. From the checkout under the runtime user,
`just verify-deployment /opt/librenms --json` wraps the checker.

| Report layer / symptom | Targeted action |
|---|---|
| Wrong root or bootstrap unavailable | Set `LIBRENMS_PATH` to the intended initialized LibreNMS root; verify the runtime user can read it. |
| Root requirement, registry, provider, routes, or enabled row missing | Rerun `quick-install.sh` under the same runtime user/home. Resolve its first failing step; do not substitute a source-file route check. |
| Database unavailable or required schema missing | Restore connectivity/permissions on the selected LibreNMS connection, then rerun the installer. Do not point setup at a guessed second database. |
| Output not writable | Correct ownership/permissions on `output/maps`, `output/cache`, and `output/thumbnails` for the runtime user, then rerun the installer. |
| CLI healthy, browser stale or denied | Check the actual HTTP response, login state, root/persistent environment and FPM configuration; refresh web workers. Docker file sessions can require login again. |
| Real traffic missing | Check port associations and runtime-user access to the configured LibreNMS RRD directory; compare with demo mode. |

LibreNMS `validate.php` may report plugin-owned extra tables. Its `utf8mb4_bin`
collation warnings for JSON columns `wmng_map_templates.config`, `wmng_nodes.meta`,
`wmng_maps.options`, and `wmng_links.style` are expected MySQL/MariaDB JSON behavior.
Do not change JSON column collations merely to silence those warnings.

## Backup and recovery

**`bin/backup.php` is retired. Every invocation exits nonzero without bootstrapping
or writing.** Its old JSON format omitted templates, saved versions, and plugin
settings, and its restore was unsafe. Existing files remain untouched; individual
map JSON exports are useful for sharing, but are not a complete recovery backup.

Back up a consistent **full LibreNMS database**, including the five `wmng_*`
tables and the `plugins` row/settings, together with matching application source
and configuration. Pause user writes and scheduled/dispatcher work while taking
the set; use your database's consistent-backup procedure for nontransactional
tables. Record the plugin tag, LibreNMS version, PHP version, database version,
Docker image ID/digest when applicable, and checksums. Keep backups private and
outside the web root, with separate durable storage.

Run this native example as a backup administrator with read access to the files,
write access to `/var/backups`, and database dump privileges. Substitute the database
name and protected client credentials file; never put a password on the command line:

```bash
(
  set -euo pipefail
  umask 077
  backup_dir="$(mktemp -d /var/backups/weathermapng.XXXXXXXX)"
  mariadb-dump --defaults-extra-file=/secure/librenms-backup.cnf \
    --single-transaction --routines --events --triggers librenms > "$backup_dir/librenms.sql"
  config_paths=(.env config composer.plugins.json composer.json composer.lock)
  if [ -f /opt/librenms/config.php ]; then config_paths+=(config.php); fi
  tar -czf "$backup_dir/runtime-config.tgz" -C /opt/librenms "${config_paths[@]}"
  composer_home="$(sudo -u librenms -H composer config --global home)"
  tar -czf "$backup_dir/composer-user.tgz" -C "$composer_home" config.json
  if [ -f "$composer_home/auth.json" ]; then
    cp "$composer_home/auth.json" "$backup_dir/composer-auth.json"
  fi
  printf 'Backup complete: %s\n' "$backup_dir"
)
```

The credentials account needs database dump privileges, including routines/events
if used. Archive the selected plugin release/source, any local source changes,
user Composer repository/auth configuration, and the published root config.
For Docker also back up `/data/.env` and the relevant `/data` contents (including
RRDs), your Compose files, hook, host checkout, and volume backups. Include output
exports/thumbnails if you rely on those generated files. Do not archive the entire
vendor tree as the dependency-restoration strategy. Test extraction into an empty
protected directory and test database restoration before relying on a backup.

For recovery, keep the running database intact while validating a separate target:

1. Provision an empty database with an explicitly chosen name and restricted
   credentials. Restore the **full single-database dump** into that target. A dump
   made without `--databases`/`--all-databases` avoids generated `USE` redirection;
   inspect it for explicit database references before restoring. Check
   `SELECT DATABASE()` through the configured application connection before any
   setup or destructive operation.
2. Restore the matching saved LibreNMS/plugin versions and configuration into an
   isolated matching-version environment. Extract configuration archives into a
   protected staging directory first; review destinations and permissions before
   applying them. Preserve `APP_KEY`, repository configuration, registry, secrets,
   and Docker persistent settings. Do not overwrite a running app blindly.
3. Point the isolated application at the restored database, restore runtime-user
   ownership, and run the matching release's `quick-install.sh`. In Docker, start
   the matching image with the restored data/source/hook mounts and fail-stop
   setting. Keep workers from accessing the original database during validation.
4. Run the report, compare all five plugin tables and plugin settings/IDs against
   the backup, and verify maps, links, custom templates, saved versions, effective
   settings, and authenticated editor/embed access. Switch traffic only after
   those checks pass; retain the original backup and previous environment.

For step 1, a backup administrator can use this explicit-target example after
provisioning an empty `librenms_restore_v115` database. The protected restore
client file must grant access only to that target, not the running source database.
Use the saved single-database dump, inspect it for `USE`/database-qualified object
references, and stop if its destinations are not the intended target:

```bash
(
  set -euo pipefail
  backup_dir=/var/backups/weathermapng.REPLACE_WITH_SAVED_DIRECTORY
  restore_db=librenms_restore_v115
  selected_db="$(mariadb --defaults-extra-file=/secure/librenms-restore.cnf \
    --database="$restore_db" --batch --skip-column-names --execute='SELECT DATABASE()')"
  [ "$selected_db" = "$restore_db" ] || exit 1
  table_count="$(mariadb --defaults-extra-file=/secure/librenms-restore.cnf \
    --database="$restore_db" --batch --skip-column-names \
    --execute='SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')"
  [ "$table_count" = 0 ] || exit 1
  mariadb --defaults-extra-file=/secure/librenms-restore.cnf \
    --database="$restore_db" < "$backup_dir/librenms.sql"
)
```

This imports only into the provisioned recovery target. Then verify the restored
application's `SELECT DATABASE()` before running its matching installer or workers.

Rolling source back alone does not reverse database changes. Recovery uses the
saved matching database/config/source set; it is not an automatic downgrade or
OS recovery mechanism.

The v1.15.0 container installation path was qualified against LibreNMS 26.10.1/PHP 8.4.21 in the
disposable official-image stack (image ID and repository digest
`sha256:661ad568d441edd157ced5c7a58a559b3c2bc34c3824b6e492f8bc13b8c70e9f`).
Tests exercise actual `daily.sh post-pull`
dependency restoration, populated app recreation, fail-stop registration failure,
and full single-database restore into a second owned database plus a private
configuration-archive roundtrip. These are matching-version container and recovery controls.

Native smoke on 2026-10-28 used full Debian 13.7/PHP 8.4 and Ubuntu 26.04.1/PHP 8.5
KVM guests with native MariaDB, nginx and PHP-FPM. Populated v1.14.1 installs were
upgraded to v1.15.0, then LibreNMS's unmodified release-channel `daily.sh` advanced
26.9.1.1 to 26.10.2 and ran again. The patch was separately checked on those hosts.
Ubuntu package maintenance upgraded 30 packages and installed eight, including a
new kernel. Debian was already current; an explicitly constructed baseline using
official PHP 8.4.24 packages was verified before a normal security upgrade of 14
packages to 8.4.27. Both guests rebooted and retained accounts, map data, settings,
registration and authenticated editor/embed access. Cached/uncached middleware,
Chromium editing, streaming, legacy dimensions and new version capture were tested.

This covers those source and package paths. Major distribution migrations,
existing production hosts and general OS disaster recovery were not exercised.
