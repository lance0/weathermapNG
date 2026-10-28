#!/bin/bash
# Shared by deploy.sh and quick-install.sh after dropping root privileges.

install_lock() {
    local variable=$1 resource=$2 file=$3 fd=${!1:-} previous saved_umask
    # Reuse only inherited descriptors for this inode, never a claimed "held" flag.
    if ! [[ "$fd" =~ ^[0-9]+$ && -f "/proc/self/fd/$fd" && "/proc/self/fd/$fd" -ef "$file" ]]; then
        fd=
        for previous in "${install_lock_fds[@]}"; do
            if [[ "/proc/self/fd/$previous" -ef "$file" ]]; then fd=$previous; break; fi
        done
        if [ -z "$fd" ]; then
            saved_umask=$(umask)
            umask 077
            if ! exec {fd}>>"$file"; then
                umask "$saved_umask"
                printf 'Error: Cannot open installer lock for %s: %s\n' "$resource" "$file" >&2
                exit 1
            fi
            umask "$saved_umask"
        fi
    fi
    if ! flock -n "$fd"; then
        printf 'Error: Cannot lock %s (%s); another installer may be running. Wait for it to finish, then retry.\n' "$resource" "$file" >&2
        exit 1
    fi
    install_lock_fds+=("$fd")
    export "$variable=$fd"
    # Never unlink lock files: contenders must continue to open the same inode.
}

acquire_install_locks() {
    local composer_home xdg_home saved_umask
    local -a install_lock_fds=()
    if ! command -v flock >/dev/null; then
        echo 'Error: flock is required to serialize installers; install util-linux and retry.' >&2
        exit 1
    fi
    LIBRENMS_PATH=$(cd "$LIBRENMS_PATH" && pwd -P)
    # Composer's config command can write config/auth files even when reading home.
    composer_home=${COMPOSER_HOME:-}
    if [ -z "$composer_home" ]; then
        : "${HOME:?HOME or COMPOSER_HOME must be set}"
        composer_home="$HOME/.composer"
        if [ -d /etc/xdg ] || [ -n "$(compgen -e XDG_)" ]; then
            xdg_home="${XDG_CONFIG_HOME:-$HOME/.config}/composer"
            if [ -d "$xdg_home" ] || [ ! -d "$composer_home" ]; then composer_home=$xdg_home; fi
        fi
    fi
    saved_umask=$(umask)
    umask 077
    if ! mkdir -p "$LIBRENMS_PATH/storage/cache" "$composer_home"; then
        umask "$saved_umask"
        echo 'Error: Cannot create installer lock directories; check LibreNMS and Composer home permissions.' >&2
        exit 1
    fi
    umask "$saved_umask"
    composer_home=$(cd "$composer_home" && pwd -P)
    # An explicit relative home must stay the same across installer directory changes.
    if [ -n "${COMPOSER_HOME:-}" ]; then export COMPOSER_HOME=$composer_home; fi
    install_lock WEATHERMAPNG_ROOT_LOCK_FD 'LibreNMS installation' "$LIBRENMS_PATH/storage/cache/.weathermapng-install.lock"
    install_lock WEATHERMAPNG_CHECKOUT_LOCK_FD 'plugin checkout' "$PLUGIN_DIR/.weathermapng-install.lock"
    install_lock WEATHERMAPNG_COMPOSER_LOCK_FD 'Composer home' "$composer_home/.weathermapng-install.lock"
}
