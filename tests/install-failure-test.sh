#!/bin/bash
# Exercise installer commands in an isolated checkout; no LibreNMS/database is touched.
set -euo pipefail
SOURCE="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PLUGIN="$WORK/plugin source"
export LIBRENMS_PATH="$WORK/selected root's app"
FAKE_BIN="$WORK/bin"
export INSTALL_LOG="$WORK/commands.log" REAL_PHP
REAL_PHP="$(command -v php)"
mkdir -p "$PLUGIN/database" "$LIBRENMS_PATH/vendor" "$LIBRENMS_PATH/bootstrap" "$FAKE_BIN"
cp "$SOURCE/quick-install.sh" "$SOURCE/deploy.sh" "$PLUGIN/"
touch "$PLUGIN/composer.json" "$PLUGIN/database/setup.php" "$PLUGIN/verify-deployment.php"
touch "$LIBRENMS_PATH/composer.json" "$LIBRENMS_PATH/artisan" "$LIBRENMS_PATH/vendor/autoload.php" "$LIBRENMS_PATH/bootstrap/app.php"
cat > "$FAKE_BIN/id" <<'EOF'
#!/bin/bash
if [ "${1:-}" = -u ]; then echo 1000; else /usr/bin/id "$@"; fi
EOF
cat > "$FAKE_BIN/composer" <<'EOF'
#!/bin/bash
printf 'composer %s\n' "$*" >> "$INSTALL_LOG"
case "${FAIL_STAGE:-}:$*" in
    dependencies:install*|repository:'config --global'*|migration:'config --unset'*) exit 21;;
esac
EOF
cat > "$LIBRENMS_PATH/lnms" <<'EOF'
#!/bin/bash
printf 'lnms %s\n' "$*" >> "$INSTALL_LOG"
case "${FAIL_STAGE:-}:${1:-}" in registration:plugin:add|enable:plugin:enable) exit 22;; esac
EOF
cat > "$FAKE_BIN/php" <<'EOF'
#!/bin/bash
printf 'php %s\n' "$*" >> "$INSTALL_LOG"
if [ "${1:-}" = -r ]; then exec "$REAL_PHP" "$@"; fi
case "${FAIL_STAGE:-}:${1:-}:${2:-}" in
    schema:database/setup.php:*|discovery:artisan:package:discover) exit 23;;
esac
if [ "${1:-}" = artisan ] && [ "${FAIL_STAGE:-}" = "${2:-}" ]; then exit 24; fi
if [ "${FAIL_STAGE:-}" = report ] && [[ "${1:-}" == */verify-deployment.php ]]; then exit 25; fi
EOF
cat > "$FAKE_BIN/git" <<'EOF'
#!/bin/bash
printf 'git %s\n' "$*" >> "$INSTALL_LOG"
case "${1:-}" in
    diff)
        if [ "${FAIL_STAGE:-}" = dirty ] && [ "${2:-}" != --cached ]; then exit 1; fi
        if [ "${FAIL_STAGE:-}" = staged ] && [ "${2:-}" = --cached ]; then exit 1; fi;;
    symbolic-ref) [ "${GIT_MODE:-}" != detached ];;
    rev-parse) [ "${FAIL_STAGE:-}" != upstream ];;
    pull) [ "${FAIL_STAGE:-}" != pull ];;
    *) exit 98;;
esac
EOF
chmod +x "$FAKE_BIN"/* "$LIBRENMS_PATH/lnms"
export PATH="$FAKE_BIN:$PATH"
for stage in dependencies repository migration registration discovery schema enable optimize:clear route:clear view:clear config:clear cache:clear report; do
    : > "$INSTALL_LOG"
    if FAIL_STAGE="$stage" bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then
        echo "FAIL: required failure $stage was ignored" >&2; exit 1
    fi
    if grep -q 'Installation complete' "$WORK/output"; then echo "FAIL: false success for $stage" >&2; exit 1; fi
    grep -q 'Error:' "$WORK/output"
    echo "PASS: $stage fails explicitly"
done
: > "$INSTALL_LOG"
bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1
grep -q 'Installation complete' "$WORK/output"
grep -q 'composer config --global repositories.weathermapng' "$INSTALL_LOG"
grep -q 'composer config --unset repositories.weathermapng' "$INSTALL_LOG"
grep -q 'lnms plugin:add librenms/weathermapng \* --no-interaction' "$INSTALL_LOG"
grep -q 'verify-deployment.php' "$INSTALL_LOG"
echo 'PASS: selected archive installs through the shared verified path'
mkdir "$PLUGIN/.git"
for stage in dirty staged upstream pull; do
    : > "$INSTALL_LOG"
    if FAIL_STAGE="$stage" bash "$PLUGIN/deploy.sh" > "$WORK/output" 2>&1; then echo "FAIL: Git $stage was ignored" >&2; exit 1; fi
    if grep -q '^composer ' "$INSTALL_LOG"; then echo 'FAIL: install ran after failed Git check' >&2; exit 1; fi
    echo "PASS: Git $stage stops before installation"
done
: > "$INSTALL_LOG"
GIT_MODE=detached bash "$PLUGIN/deploy.sh" > "$WORK/output" 2>&1
if grep -q 'git pull' "$INSTALL_LOG"; then echo 'FAIL: detached source was advanced' >&2; exit 1; fi
grep -q 'Installation complete' "$WORK/output"
echo 'PASS: detached release is retained and verified'
rmdir "$PLUGIN/.git"
: > "$INSTALL_LOG"
bash "$PLUGIN/deploy.sh" > "$WORK/output" 2>&1
grep -q 'Archive installation' "$WORK/output"
grep -q 'Installation complete' "$WORK/output"
echo 'PASS: archive upgrade uses the shared installer'
: > "$INSTALL_LOG"
if LIBRENMS_PATH="$WORK/missing" bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then echo 'FAIL: missing selected root accepted' >&2; exit 1; fi
[ ! -s "$INSTALL_LOG" ]
echo 'PASS: missing selected root fails before dependency or database work'
