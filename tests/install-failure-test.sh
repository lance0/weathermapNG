#!/bin/bash
# Exercise installer commands in an isolated checkout; no LibreNMS/database is touched.
set -euo pipefail
SOURCE=${INSTALL_SOURCE:-$(cd "$(dirname "$0")/.." && pwd)}
WORK="$(mktemp -d)"
trap 'chmod -R u+rwX "$WORK"; rm -rf "$WORK"' EXIT
PLUGIN="$WORK/plugin source"
export COMPOSER_HOME="$WORK/composer-home"
export LIBRENMS_PATH="$WORK/selected root's app"
FAKE_BIN="$WORK/bin"
export INSTALL_LOG="$WORK/commands.log" REAL_PHP
REAL_PHP="$(command -v php)"
mkdir -p "$PLUGIN/database" "$LIBRENMS_PATH/vendor" "$LIBRENMS_PATH/bootstrap" "$FAKE_BIN"
cp "$SOURCE/quick-install.sh" "$SOURCE/deploy.sh" "$PLUGIN/"
cp -r "$SOURCE/scripts" "$PLUGIN/"
touch "$PLUGIN/composer.json" "$PLUGIN/database/setup.php" "$PLUGIN/verify-deployment.php"
printf '{}\n' > "$LIBRENMS_PATH/composer.json"
touch "$LIBRENMS_PATH/artisan" "$LIBRENMS_PATH/vendor/autoload.php" "$LIBRENMS_PATH/bootstrap/app.php"
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
if [ "${1:-}" = config ]; then
    "$REAL_PHP" -r '
        $global = $argv[1] === "--global";
        $file = $global ? getenv("COMPOSER_HOME") . "/config.json" : getenv("LIBRENMS_PATH") . "/composer.json";
        $config = is_file($file) ? json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR) : new stdClass;
        $config->repositories ??= new stdClass;
        if ($global) {
            $config->repositories->weathermapng = json_decode($argv[2], false, 512, JSON_THROW_ON_ERROR);
        } else {
            unset($config->repositories->weathermapng);
        }
        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    ' -- "$2" "${4:-}"
fi
EOF
cat > "$LIBRENMS_PATH/lnms" <<'EOF'
#!/bin/bash
printf 'lnms %s\n' "$*" >> "$INSTALL_LOG"
case "${FAIL_STAGE:-}:${1:-}" in registration:plugin:add|enable:plugin:enable) exit 22;; esac
EOF
cat > "$FAKE_BIN/php" <<'EOF'
#!/bin/bash
printf 'php %s\n' "$*" >> "$INSTALL_LOG"
if [ "${1:-}" = -r ]; then
    if [[ "$2" == *version_compare* ]]; then
        echo runtime-preflight >> "$INSTALL_LOG"
        case "${FAIL_STAGE:-}" in
            php-version) echo "PHP 8.2+ required" >&2; exit 41;;
            php-extension) echo "Missing PHP extension: gd" >&2; exit 42;;
        esac
    fi
    exec "$REAL_PHP" "$@"
fi
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
    rm -f "$COMPOSER_HOME/config.json"
    : > "$INSTALL_LOG"
    if FAIL_STAGE="$stage" bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then
        echo "FAIL: required failure $stage was ignored" >&2; exit 1
    else
        result=$?
    fi
    case "$stage" in
        dependencies|repository|migration) expected=21;;
        registration|enable) expected=22;;
        schema|discovery) expected=23;;
        report) expected=25;;
        *) expected=24;;
    esac
    [ "$result" -eq "$expected" ] || { echo "FAIL: $stage did not reach the injected failure (exit $result)" >&2; cat "$WORK/output" >&2; exit 1; }
    if grep -q 'Installation complete' "$WORK/output"; then echo "FAIL: false success for $stage" >&2; exit 1; fi
    grep -q 'Error:' "$WORK/output"
    echo "PASS: $stage fails explicitly"
done
rm -f "$COMPOSER_HOME/config.json"
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

# Exercise repository ownership with actual JSON reads/writes in the isolated mock.
repository_config() {
    "$REAL_PHP" -r '
        $config = ["config" => ["sort-packages" => true], "repositories" => ["unrelated" => ["type" => "vcs", "url" => "https://example.invalid/project"]]];
        if ($argv[2] !== "") {
            $config["repositories"]["weathermapng"] = ["type" => $argv[3], "url" => $argv[2], "options" => ["symlink" => false, "reference" => "none"]];
        }
        file_put_contents($argv[1], json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    ' "$1" "$2" "${3:-path}"
}
refuse_repository() {
    cp "$COMPOSER_HOME/config.json" "$WORK/global.before"
    cp "$LIBRENMS_PATH/composer.json" "$WORK/local.before"
    : > "$INSTALL_LOG"
    if bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then echo 'FAIL: conflicting repository accepted' >&2; exit 1; fi
    grep -q 'repositories.weathermapng' "$WORK/output"
    if grep -Eq '^(composer |lnms |php database/)' "$INSTALL_LOG"; then echo 'FAIL: repository refusal followed mutation' >&2; exit 1; fi
    cmp "$WORK/global.before" "$COMPOSER_HOME/config.json"
    cmp "$WORK/local.before" "$LIBRENMS_PATH/composer.json"
}
printf '{"http-basic":{"example.invalid":{"username":"fixture","password":"untouched"}}}\n' > "$COMPOSER_HOME/auth.json"
cp "$COMPOSER_HOME/auth.json" "$WORK/auth.before"
mkdir "$WORK/other-plugin"
repository_config "$COMPOSER_HOME/config.json" "$WORK/other-plugin"
repository_config "$LIBRENMS_PATH/composer.json" "$PLUGIN"
refuse_repository
echo 'PASS: conflicting global checkout is preserved before dependency mutation'
repository_config "$COMPOSER_HOME/config.json" "$PLUGIN" vcs
refuse_repository
echo 'PASS: non-path global managed entry is preserved and refused'
repository_config "$COMPOSER_HOME/config.json" "$PLUGIN"
repository_config "$LIBRENMS_PATH/composer.json" "$WORK/other-plugin"
refuse_repository
echo 'PASS: conflicting local managed entry is preserved and refused'
repository_config "$LIBRENMS_PATH/composer.json" "$PLUGIN" vcs
refuse_repository
echo 'PASS: non-path local managed entry is preserved and refused'
repository_config "$LIBRENMS_PATH/composer.json" ""
for invalid in '{broken' '[]' '{"repositories":null}' '{"repositories":{"weathermapng":false}}'; do
    printf '%s\n' "$invalid" > "$COMPOSER_HOME/config.json"
    refuse_repository
done
echo 'PASS: malformed configuration and invalid managed entries fail closed'
repository_config "$COMPOSER_HOME/config.json" "$PLUGIN"
cp "$COMPOSER_HOME/config.json" "$WORK/global.before"
chmod 000 "$COMPOSER_HOME/config.json"
: > "$INSTALL_LOG"
if bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then echo 'FAIL: unreadable global configuration accepted' >&2; exit 1; fi
chmod 600 "$COMPOSER_HOME/config.json"
grep -q 'config.json' "$WORK/output"
if grep -q '^composer ' "$INSTALL_LOG"; then echo 'FAIL: unreadable configuration reached Composer' >&2; exit 1; fi
cmp "$WORK/global.before" "$COMPOSER_HOME/config.json"
echo 'PASS: unreadable global configuration is preserved and refused'
ln -s "$PLUGIN" "$WORK/plugin-alias"
for url in "$PLUGIN" "$PLUGIN/." "$WORK/plugin-alias" '../plugin source'; do
    repository_config "$COMPOSER_HOME/config.json" "$url"
    cp "$COMPOSER_HOME/config.json" "$WORK/global.before"
    : > "$INSTALL_LOG"
    bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1
    cmp "$WORK/global.before" "$COMPOSER_HOME/config.json"
    if grep -q '^composer config --global' "$INSTALL_LOG"; then echo 'FAIL: compatible repository was rewritten' >&2; exit 1; fi
done
echo 'PASS: repeat installs preserve global URL spelling, options, and unrelated settings'
repository_config "$COMPOSER_HOME/config.json" "$WORK/plugin-alias"
repository_config "$LIBRENMS_PATH/composer.json" '../plugin source'
cp "$COMPOSER_HOME/config.json" "$WORK/global.before"
cp "$LIBRENMS_PATH/composer.json" "$WORK/local.before"
: > "$INSTALL_LOG"
if FAIL_STAGE=registration bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then echo 'FAIL: registration failure ignored' >&2; exit 1; else result=$?; fi
[ "$result" -eq 22 ]
cmp "$WORK/global.before" "$COMPOSER_HOME/config.json"
cmp "$WORK/local.before" "$LIBRENMS_PATH/composer.json"
if grep -q '^composer config --unset' "$INSTALL_LOG"; then echo 'FAIL: local lookup removed before successful registration' >&2; exit 1; fi
echo 'PASS: failed registration preserves existing global and local migration entries'
rm "$COMPOSER_HOME/config.json"
if FAIL_STAGE=registration bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1; then echo 'FAIL: fresh registration failure ignored' >&2; exit 1; else result=$?; fi
[ "$result" -eq 22 ]
cmp "$WORK/local.before" "$LIBRENMS_PATH/composer.json"
test -f "$COMPOSER_HOME/config.json"
echo 'PASS: failed fresh registration retains the local lookup and newly created global entry'
rm "$COMPOSER_HOME/config.json"
: > "$INSTALL_LOG"
bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1
"$REAL_PHP" -r '
    $global = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $local = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    if (($global["repositories"]["weathermapng"]["url"] ?? null) !== $argv[3]
        || isset($local["repositories"]["weathermapng"])
        || ($local["config"]["sort-packages"] ?? null) !== true
        || ($local["repositories"]["unrelated"]["url"] ?? null) !== "https://example.invalid/project") {
        exit(1);
    }
' "$COMPOSER_HOME/config.json" "$LIBRENMS_PATH/composer.json" "$PLUGIN"
echo 'PASS: successful local-only migration creates global lookup and preserves unrelated local settings'
repository_config "$COMPOSER_HOME/config.json" ""
bash "$PLUGIN/quick-install.sh" > "$WORK/output" 2>&1
"$REAL_PHP" -r '
    $config = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (($config["config"]["sort-packages"] ?? null) !== true
        || ($config["repositories"]["unrelated"]["url"] ?? null) !== "https://example.invalid/project") {
        exit(1);
    }
' "$COMPOSER_HOME/config.json"
cmp "$WORK/auth.before" "$COMPOSER_HOME/auth.json"
echo 'PASS: adding the managed entry preserves unrelated global repositories and authentication'

# A real local remote has a pending fast-forward throughout every failed preflight.
export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1
rm "$FAKE_BIN/git"
REAL_GIT=$(command -v git)
cp "$SOURCE/.gitignore" "$PLUGIN/.gitignore"
printf 'old source\n' > "$PLUGIN/source-marker"
"$REAL_GIT" init -q --initial-branch=main "$PLUGIN"
"$REAL_GIT" -C "$PLUGIN" add quick-install.sh deploy.sh scripts composer.json database verify-deployment.php .gitignore source-marker
"$REAL_GIT" -C "$PLUGIN" -c user.name=Fixture -c user.email=fixture@localhost -c commit.gpgSign=false commit -qm old
"$REAL_GIT" init -q --bare --initial-branch=main "$WORK/origin.git"
"$REAL_GIT" -C "$PLUGIN" remote add origin "$WORK/origin.git"
"$REAL_GIT" -C "$PLUGIN" push -qu origin main
"$REAL_GIT" clone -q --branch main "$WORK/origin.git" "$WORK/new-source"
printf 'new source\n' > "$WORK/new-source/source-marker"
"$REAL_GIT" -C "$WORK/new-source" add source-marker
"$REAL_GIT" -C "$WORK/new-source" -c user.name=Fixture -c user.email=fixture@localhost -c commit.gpgSign=false commit -qm new
"$REAL_GIT" -C "$WORK/new-source" push -q origin main
before=$("$REAL_GIT" -C "$PLUGIN" rev-parse HEAD)
after=$("$REAL_GIT" -C "$WORK/new-source" rev-parse HEAD)
assert_upgrade_refused() {
    local observed_head
    : > "$INSTALL_LOG"
    if PATH="${1:-$PATH}" /bin/bash "$PLUGIN/deploy.sh" > "$WORK/output" 2>&1; then
        echo 'FAIL: invalid upgrade preflight accepted' >&2; exit 1
    fi
    grep -q 'Error:' "$WORK/output"
    observed_head=$("$REAL_GIT" -C "$PLUGIN" rev-parse HEAD)
    [ "$observed_head" = "$before" ] || { echo "FAIL: failed preflight advanced HEAD from $before to $observed_head" >&2; exit 1; }
    [ "$(cat "$PLUGIN/source-marker")" = 'old source' ]
    "$REAL_GIT" -C "$PLUGIN" diff --quiet
    if grep -Eq '^(composer |lnms |php (database/|artisan))' "$INSTALL_LOG"; then echo 'FAIL: failed preflight reached mutation' >&2; exit 1; fi
}
for file in composer.json vendor/autoload.php bootstrap/app.php artisan lnms; do
    mv "$LIBRENMS_PATH/$file" "$WORK/saved-root-file"
    assert_upgrade_refused
    mv "$WORK/saved-root-file" "$LIBRENMS_PATH/$file"
done
echo 'PASS: missing root files leave real Git HEAD and source untouched'
chmod 644 "$LIBRENMS_PATH/lnms"
assert_upgrade_refused
grep -q 'must be executable' "$WORK/output"
chmod 755 "$LIBRENMS_PATH/lnms"
echo 'PASS: non-executable lnms leaves real Git HEAD and source untouched'
mkdir "$WORK/no-composer"
for tool in dirname id php; do ln -s "$(command -v "$tool")" "$WORK/no-composer/$tool"; done
assert_upgrade_refused "$WORK/no-composer"
grep -q 'unavailable: composer' "$WORK/output"
echo 'PASS: missing Composer leaves real Git HEAD and source untouched'
for stage in php-version php-extension; do
    FAIL_STAGE="$stage" assert_upgrade_refused
    grep -Eq 'PHP 8.2\+ required|Missing PHP extension: gd' "$WORK/output"
done
echo 'PASS: PHP version and extension rejection leave real Git HEAD and source untouched'
if [ "$(/usr/bin/id -u)" -eq 0 ]; then
    echo 'FAIL: permission regressions must run as an unprivileged user' >&2; exit 1
fi
mkdir -p "$PLUGIN/vendor/composer" "$LIBRENMS_PATH/vendor/composer" "$WORK/locked-parent"
for directory in "$PLUGIN" "$LIBRENMS_PATH" "$COMPOSER_HOME" "$PLUGIN/vendor/composer" "$LIBRENMS_PATH/vendor/composer" "$PLUGIN/output/cache"; do
    chmod 555 "$directory"
    assert_upgrade_refused
    grep -q 'writable directory' "$WORK/output"
    chmod 755 "$directory"
done
for file in "$LIBRENMS_PATH/composer.json" "$COMPOSER_HOME/config.json"; do
    chmod 444 "$file"
    assert_upgrade_refused
    grep -q 'writable file' "$WORK/output"
    chmod 644 "$file"
done
chmod 555 "$WORK/locked-parent"
COMPOSER_HOME="$WORK/locked-parent/new/nested" assert_upgrade_refused
test ! -e "$WORK/locked-parent/new"
chmod 755 "$WORK/locked-parent"
echo 'PASS: real unprivileged permission failures leave Git/source unchanged without mkdir probes'
repository_config "$COMPOSER_HOME/config.json" "$WORK/other-plugin"
assert_upgrade_refused
grep -q 'repositories.weathermapng' "$WORK/output"
repository_config "$COMPOSER_HOME/config.json" "$PLUGIN"
echo 'PASS: conflicting registration is refused under locks before the real Git fast-forward'
chmod 400 "$COMPOSER_HOME/auth.json"
: > "$INSTALL_LOG"
bash "$PLUGIN/deploy.sh" > "$WORK/output" 2>&1
[ "$("$REAL_GIT" -C "$PLUGIN" rev-parse HEAD)" = "$after" ]
[ "$(cat "$PLUGIN/source-marker")" = 'new source' ]
[ "$(grep -c '^runtime-preflight$' "$INSTALL_LOG")" -eq 2 ]
grep -q 'Installation complete' "$WORK/output"
cmp "$WORK/auth.before" "$COMPOSER_HOME/auth.json"
echo 'PASS: valid tracked upgrade fast-forwards, rechecks runtime, and accepts read-only credentials'
"$REAL_GIT" -C "$PLUGIN" checkout -q --detach "$before"
bash "$PLUGIN/deploy.sh" > "$WORK/output" 2>&1
[ "$("$REAL_GIT" -C "$PLUGIN" rev-parse HEAD)" = "$before" ]
grep -q 'Detached checkout' "$WORK/output"
grep -q 'Installation complete' "$WORK/output"
echo 'PASS: detached real Git reinstall retains the selected commit'
