#!/bin/bash
# Install the selected checkout into the selected LibreNMS installation.
set -Eeuo pipefail
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd -P)"
export LIBRENMS_PATH="${LIBRENMS_PATH:-/opt/librenms}"

# Drop the whole process before Composer, Git, or database writes; login sets the runtime user's HOME.
if [ "$(id -u)" -eq 0 ]; then
    if [ -f /.dockerenv ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
        id librenms >/dev/null
        printf -v install_command 'exec env LIBRENMS_PATH=%q bash %q' "$LIBRENMS_PATH" "$PLUGIN_DIR/quick-install.sh"
        exec su -l librenms -s /bin/bash -c "$install_command"
    fi
    echo 'Error: Do not run quick-install.sh as root; run it as the LibreNMS user.' >&2
    exit 1
fi

step=preflight
trap 'result=$?; printf "Error: %s failed (exit %s).\nRetry as the LibreNMS user: cd %q && LIBRENMS_PATH=%q ./quick-install.sh\n" "$step" "$result" "$PLUGIN_DIR" "$LIBRENMS_PATH" >&2; exit "$result"' ERR
for file in composer.json vendor/autoload.php bootstrap/app.php artisan lnms; do
    if [ ! -r "$LIBRENMS_PATH/$file" ]; then
        echo "Error: Selected LibreNMS installation lacks readable $file. Set LIBRENMS_PATH to the intended installation." >&2
        exit 1
    fi
done
[ -w "$PLUGIN_DIR" ]
command -v composer >/dev/null
php -r 'if (version_compare(PHP_VERSION, "8.2.0", "<")) { fwrite(STDERR, "PHP 8.2+ required\n"); exit(1); } foreach (["gd", "json", "pdo", "mbstring"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); } } echo "PHP Version: " . PHP_VERSION . "\n";'

step='acquiring installer locks'
# shellcheck source=scripts/install-common.sh
source "$PLUGIN_DIR/scripts/install-common.sh"
acquire_install_locks

step='Composer install'
cd "$PLUGIN_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

step='persistent Composer repository registration'
repository=$(php -r 'echo json_encode(["type"=>"path", "url"=>$argv[1], "options"=>["symlink"=>true]], JSON_THROW_ON_ERROR);' "$PLUGIN_DIR")
composer config --global repositories.weathermapng "$repository"
cd "$LIBRENMS_PATH"
# The daily updater resets root composer.json; the scoped global lookup survives that reset.
composer config --unset repositories.weathermapng
step='LibreNMS plugin registration'
./lnms plugin:add librenms/weathermapng '*' --no-interaction
step='package discovery'
php artisan package:discover --no-interaction

step='database setup'
cd "$PLUGIN_DIR"
php database/setup.php
step='plugin enablement'
cd "$LIBRENMS_PATH"
./lnms plugin:enable WeathermapNG --no-interaction
step='cache clearing'
for cache in optimize:clear route:clear view:clear config:clear cache:clear; do
    php artisan "$cache" --no-interaction
done

step='output directory setup'
mkdir -p "$PLUGIN_DIR/output/maps" "$PLUGIN_DIR/output/cache" "$PLUGIN_DIR/output/thumbnails"
for output in maps cache thumbnails; do
    [ -w "$PLUGIN_DIR/output/$output" ]
done
step='installation verification'
php "$PLUGIN_DIR/verify-deployment.php"
echo 'Installation complete: registration, database, permissions, and CLI runtime routes verified.'
echo 'Open the plugin while logged in to verify browser access; CLI checks do not verify web PHP/opcode cache freshness.'
