#!/bin/bash
# Controlled installer overlap, with no real Composer, Git, PHP, or database writes.
set -euo pipefail
SOURCE=${INSTALL_SOURCE:-$(cd "$(dirname "$0")/.." && pwd -P)}
WORK=$(mktemp -d)
HOLDER=
cleanup() {
    if [ -n "$HOLDER" ]; then kill -- "-$HOLDER" 2>/dev/null || true; wait "$HOLDER" 2>/dev/null || true; fi
    rm -rf "$WORK"
}
trap cleanup EXIT
fail() { echo "FAIL: $*" >&2; cat "$WORK"/*.out >&2; exit 1; }
mkdir -p "$WORK/bin"
for tool in id php composer git; do
    cat > "$WORK/bin/$tool" <<'MOCK'
#!/bin/bash
set -euo pipefail
case "${0##*/}" in
    id) echo 1000; exit;;
    php) exit;;
esac
printf '%s %s\n' "${0##*/}" "$*" >> "$INSTALL_LOG"
if [[ "${0##*/}:$1" == composer:install || "${0##*/}:$1" == git:pull ]]; then
    if [ "${BARRIER:-}" = yes ]; then
        printf 'entered\n' > "$READY"
        read -r release < "$RELEASE"
        [ "$release" != fail ] || exit 31
    fi
fi
if [ "${0##*/}" = composer ] && [ "$1" = install ]; then umask > "$INSTALL_LOG.umask"; fi
MOCK
    chmod +x "$WORK/bin/$tool"
done
export PATH="$WORK/bin:$PATH"
make_fixture() {
    local name=$1
    mkdir -p "$WORK/$name/plugin/database" "$WORK/$name/root/vendor" "$WORK/$name/root/bootstrap" "$WORK/$name/home"
    cp "$SOURCE/quick-install.sh" "$SOURCE/deploy.sh" "$WORK/$name/plugin/"
    if [ -d "$SOURCE/scripts" ]; then cp -r "$SOURCE/scripts" "$WORK/$name/plugin/"; fi
    touch "$WORK/$name/plugin/composer.json" "$WORK/$name/plugin/database/setup.php" "$WORK/$name/plugin/verify-deployment.php"
    touch "$WORK/$name/root/composer.json" "$WORK/$name/root/vendor/autoload.php" "$WORK/$name/root/bootstrap/app.php" "$WORK/$name/root/artisan"
    cat > "$WORK/$name/root/lnms" <<'MOCK'
#!/bin/bash
printf 'lnms %s\n' "$*" >> "$INSTALL_LOG"
MOCK
    chmod +x "$WORK/$name/root/lnms"
}
make_fixture a
make_fixture b
export READY="$WORK/ready" RELEASE="$WORK/release"
mkfifo "$READY" "$RELEASE"
# Read/write opens keep the controller from hanging if a faulty installer exits early.
exec {ready_fd}<>"$READY"
exec {release_fd}<>"$RELEASE"
start_holder() {
    local script=$1 plugin=$2 root=$3 home=$4
    : > "$WORK/holder.log"
    env BARRIER=yes INSTALL_LOG="$WORK/holder.log" LIBRENMS_PATH="$root" COMPOSER_HOME="$home" \
        setsid bash "$plugin/$script" > "$WORK/holder.out" 2>&1 &
    HOLDER=$!
    read -r -t 10 -u "$ready_fd" || fail 'holder never reached mutation barrier'
}
release_holder() {
    printf 'continue\n' >&"$release_fd"
    wait "$HOLDER" || fail 'holder failed'
    HOLDER=
}
contender() {
    local script=$1 plugin=$2 root=$3 home=$4 status=0
    : > "$WORK/contender.log"
    env BARRIER=no INSTALL_LOG="$WORK/contender.log" LIBRENMS_PATH="$root" COMPOSER_HOME="$home" \
        timeout 10 bash "$plugin/$script" > "$WORK/contender.out" 2>&1 || status=$?
    CONTENDER_STATUS=$status
}
expect_blocked() {
    if [ "$CONTENDER_STATUS" -eq 0 ] || [ "$CONTENDER_STATUS" -eq 124 ]; then
        fail "contender must fail promptly (exit $CONTENDER_STATUS)"
    fi
    grep -q 'Cannot lock' "$WORK/contender.out" || fail 'missing contention explanation'
    [ ! -s "$WORK/contender.log" ] || fail 'contender reached a mutation command'
}
for pair in quick-install.sh:quick-install.sh deploy.sh:quick-install.sh deploy.sh:deploy.sh quick-install.sh:deploy.sh; do
    mkdir -p "$WORK/a/plugin/.git"
    start_holder "${pair%:*}" "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
    contender "${pair#*:}" "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
    expect_blocked
    # A deploy holder also stops at Composer after exec; release both barriers.
    if [ "${pair%:*}" = deploy.sh ]; then
        printf 'continue\n' >&"$release_fd"
        read -r -t 10 -u "$ready_fd" || fail 'deploy handoff failed to reach Composer'
    fi
    release_holder
    grep -q 'Installation complete' "$WORK/holder.out" || fail 'holder did not complete'
    echo "PASS: $pair serialized through successful handoff"
done
rmdir "$WORK/a/plugin/.git"
for shared in root checkout home; do
    start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
    plugin="$WORK/b/plugin" root="$WORK/b/root" home="$WORK/b/home"
    case "$shared" in root) root="$WORK/a/root";; checkout) plugin="$WORK/a/plugin";; home) home="$WORK/a/home";; esac
    contender quick-install.sh "$plugin" "$root" "$home"
    expect_blocked
    release_holder
    echo "PASS: shared $shared serializes otherwise disjoint installations"
done
start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
contender quick-install.sh "$WORK/b/plugin" "$WORK/b/root" "$WORK/b/home"
[ "$CONTENDER_STATUS" -eq 0 ] || fail 'fully disjoint installation blocked'
release_holder
echo 'PASS: fully disjoint installations proceed concurrently'
ln -s "$WORK/a/plugin" "$WORK/plugin-alias"
ln -s "$WORK/a/root" "$WORK/root-alias"
ln -s "$WORK/a/home" "$WORK/home-alias"
for shared in root checkout home; do
    start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
    plugin="$WORK/b/plugin" root="$WORK/b/root" home="$WORK/b/home"
    case "$shared" in root) root="$WORK/root-alias";; checkout) plugin="$WORK/plugin-alias";; home) home="$WORK/home-alias";; esac
    contender quick-install.sh "$plugin" "$root" "$home"
    expect_blocked
    release_holder
    echo "PASS: $shared symlink alias shares its real resource lock"
done
for forged in true 99999 0; do
    export WEATHERMAPNG_ROOT_LOCK_FD=$forged WEATHERMAPNG_CHECKOUT_LOCK_FD=$forged WEATHERMAPNG_COMPOSER_LOCK_FD=$forged
    start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
    contender quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
    expect_blocked
    release_holder
    echo "PASS: forged/stale descriptor $forged cannot bypass locks"
done
unset WEATHERMAPNG_ROOT_LOCK_FD WEATHERMAPNG_CHECKOUT_LOCK_FD WEATHERMAPNG_COMPOSER_LOCK_FD
# Forked children inherit real held descriptors, but must acquire their own locks.
cat > "$WORK/inherited.sh" <<'CHILD'
#!/bin/bash
set -euo pipefail
source "$1/scripts/install-common.sh"
WORK=$2
PLUGIN_DIR="$WORK/a/plugin"
export LIBRENMS_PATH="$WORK/a/root" COMPOSER_HOME="$WORK/a/home"
acquire_install_locks
children=()
for child in 1 2; do
    LIBRENMS_PATH=$4 COMPOSER_HOME=$5 bash "$3/quick-install.sh" > "$WORK/child-$child.out" 2>&1 &
    children+=("$!")
done
for child in "${children[@]}"; do
    status=0
    wait "$child" || status=$?
    printf '%s\n' "$status" >> "$WORK/inherited.status"
done
CHILD
for shared in all checkout home; do
    plugin="$WORK/b/plugin" root="$WORK/b/root" home="$WORK/b/home"
    case "$shared" in
        all) plugin="$WORK/a/plugin"; root="$WORK/a/root"; home="$WORK/a/home";;
        checkout) plugin="$WORK/a/plugin";;
        home) home="$WORK/a/home";;
    esac
    : > "$WORK/inherited.status"
    : > "$WORK/contender.log"
    INSTALL_LOG="$WORK/contender.log" timeout 10 bash "$WORK/inherited.sh" "$SOURCE" "$WORK" "$plugin" "$root" "$home" || fail 'inherited descriptor probe timed out'
    if grep -qx 0 "$WORK/inherited.status"; then fail "child reused parent-held $shared descriptor"; fi
    for child in 1 2; do grep -q 'Cannot lock' "$WORK/child-$child.out" || fail 'child missed contention diagnostic'; done
    [ ! -s "$WORK/contender.log" ] || fail 'child reached mutation with parent-held descriptors'
    echo "PASS: forked siblings contend on parent-held $shared resources"
done
# Matching owner metadata and inode still require a real successful flock.
start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
: > "$WORK/contender.log"
CONTENDER_STATUS=0
# Variables expand in the child shell.
# shellcheck disable=SC2016
INSTALL_LOG="$WORK/contender.log" LIBRENMS_PATH="$WORK/a/root" COMPOSER_HOME="$WORK/a/home" \
    timeout 10 bash -c 'exec {fd}>>"$LIBRENMS_PATH/storage/cache/.weathermapng-install.lock"; export WEATHERMAPNG_ROOT_LOCK_FD=$fd WEATHERMAPNG_LOCK_OWNER_PID=$BASHPID; exec bash "$1/quick-install.sh"' bash "$WORK/a/plugin" > "$WORK/contender.out" 2>&1 || CONTENDER_STATUS=$?
expect_blocked
release_holder
echo 'PASS: matching owner and unlocked inherited descriptor still acquires a real lock'
start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
printf 'fail\n' >&"$release_fd"
if wait "$HOLDER"; then fail 'injected failure succeeded'; fi
HOLDER=
contender quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
[ "$CONTENDER_STATUS" -eq 0 ] || fail 'failed installer retained locks'
echo 'PASS: failure releases locks and permits retry'
# A killed installer cannot leave a stale lock once its command exits too.
start_holder quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
kill -KILL "$HOLDER"
printf 'continue\n' >&"$release_fd"
wait "$HOLDER" 2>/dev/null || true
HOLDER=
# Synchronize with the surviving command finishing via its inherited lock.
flock "$WORK/a/root/storage/cache/.weathermapng-install.lock" true
contender quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/home"
[ "$CONTENDER_STATUS" -eq 0 ] || fail 'terminated installer retained locks'
echo 'PASS: termination releases locks after the in-flight command exits'
(umask 0027; contender quick-install.sh "$WORK/a/plugin" "$WORK/a/root" "$WORK/a/plugin"; [ "$CONTENDER_STATUS" -eq 0 ]) || fail 'coincident checkout/home locks deadlocked'
[ "$(cat "$WORK/contender.log.umask")" = 0027 ] || fail 'lock creation changed caller umask'
[ "$(stat -c %a "$WORK/a/plugin/.weathermapng-install.lock")" = 600 ] || fail 'lock is not private'
echo 'PASS: coincident lock inodes are deduplicated and caller umask is retained'
# Hide flock while retaining only commands needed before lock acquisition.
mkdir "$WORK/no-flock"
for tool in dirname id composer php; do ln -s "$(command -v "$tool")" "$WORK/no-flock/$tool"; done
if PATH="$WORK/no-flock" COMPOSER_HOME="$WORK/a/home" LIBRENMS_PATH="$WORK/a/root" /bin/bash "$WORK/a/plugin/deploy.sh" > "$WORK/missing.out" 2>&1; then fail 'missing flock accepted'; fi
grep -q 'flock is required' "$WORK/missing.out" || fail 'missing flock lacked actionable diagnostic'
echo 'PASS: missing flock fails before mutation'

# Home discovery must not invoke Composer, and must retain its Linux precedence.
mkdir -p "$WORK/user" "$WORK/xdg"
resolve_home() {
    # Variables expand in the child shell.
    # shellcheck disable=SC2016
    env -u COMPOSER_HOME HOME="$WORK/user" XDG_CONFIG_HOME="$WORK/xdg" \
        bash -c 'set -euo pipefail; source "$1/scripts/install-common.sh"; PLUGIN_DIR=$2; LIBRENMS_PATH=$3; acquire_install_locks; test -z "${COMPOSER_HOME+x}"; dirname "$(readlink "/proc/self/fd/$WEATHERMAPNG_COMPOSER_LOCK_FD")"' \
        bash "$SOURCE" "$WORK/a/plugin" "$WORK/a/root"
}
[ "$(resolve_home)" = "$WORK/xdg/composer" ] || fail 'fresh home did not select XDG'
rm -rf "$WORK/xdg/composer"
mkdir "$WORK/user/.composer"
[ "$(resolve_home)" = "$WORK/user/.composer" ] || fail 'existing legacy home was not retained'
mkdir "$WORK/xdg/composer"
[ "$(resolve_home)" = "$WORK/xdg/composer" ] || fail 'existing XDG home did not take precedence'
relative_home=$(cd "$WORK"; COMPOSER_HOME=relative bash -c 'set -euo pipefail; source "$1/scripts/install-common.sh"; PLUGIN_DIR=$2; LIBRENMS_PATH=$3; acquire_install_locks; printf "%s\n" "$COMPOSER_HOME"' bash "$SOURCE" "$WORK/a/plugin" "$WORK/a/root")
[ "$relative_home" = "$WORK/relative" ] || fail 'relative COMPOSER_HOME was not normalized'
if [ -e "$WORK/xdg/composer/config.json" ] || [ -e "$WORK/xdg/composer/auth.json" ]; then fail 'home discovery wrote configuration'; fi
echo 'PASS: Composer home resolution preserves XDG/legacy precedence and normalizes relative paths'
