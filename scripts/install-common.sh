#!/bin/bash
# Shared by deploy.sh and quick-install.sh after dropping root privileges.

resolve_composer_home() {
    local composer_home xdg_home
    # Composer config can write files even when asked only to read home.
    composer_home=${COMPOSER_HOME:-}
    if [ -z "$composer_home" ]; then
        : "${HOME:?HOME or COMPOSER_HOME must be set}"
        composer_home="$HOME/.composer"
        if [ -d /etc/xdg ] || [ -n "$(compgen -e XDG_)" ]; then
            xdg_home="${XDG_CONFIG_HOME:-$HOME/.config}/composer"
            if [ -d "$xdg_home" ] || [ ! -d "$composer_home" ]; then composer_home=$xdg_home; fi
        fi
    fi
    printf '%s\n' "$composer_home"
}

require_install_writable() {
    local path=$1 kind=$2 parent
    if [ -e "$path" ] || [ -L "$path" ]; then
        if [ "$kind" = directory ]; then
            if [ -d "$path" ] && [ -w "$path" ] && [ -x "$path" ]; then return; fi
        elif [ -f "$path" ] && [ -r "$path" ] && [ -w "$path" ]; then
            return
        fi
    else
        parent=$(dirname "$path")
        while [ ! -e "$parent" ] && [ ! -L "$parent" ]; do parent=$(dirname "$parent"); done
        if [ -d "$parent" ] && [ -w "$parent" ] && [ -x "$parent" ]; then return; fi
    fi
    printf 'Error: Installer needs a writable %s at %s; check its type and permissions, or its existing parent if missing.\n' "$kind" "$path" >&2
    exit 1
}

preflight_install_runtime() {
    local file directory composer_home executable
    for file in composer.json vendor/autoload.php bootstrap/app.php artisan lnms; do
        if [ ! -f "$LIBRENMS_PATH/$file" ] || [ ! -r "$LIBRENMS_PATH/$file" ]; then
            echo "Error: Selected LibreNMS installation lacks readable $file. Set LIBRENMS_PATH to the intended installation." >&2
            exit 1
        fi
    done
    if [ ! -f "$PLUGIN_DIR/composer.json" ] || [ ! -r "$PLUGIN_DIR/composer.json" ]; then
        echo "Error: Plugin checkout lacks readable composer.json: $PLUGIN_DIR/composer.json" >&2
        exit 1
    fi
    if [ ! -x "$LIBRENMS_PATH/lnms" ]; then
        echo "Error: Selected LibreNMS lnms must be executable: $LIBRENMS_PATH/lnms" >&2
        exit 1
    fi
    for executable in composer php; do
        if ! command -v "$executable" >/dev/null; then
            echo "Error: Required installer command is unavailable: $executable. Install it for the LibreNMS user and retry." >&2
            exit 1
        fi
    done
    php -r 'if (version_compare(PHP_VERSION, "8.2.0", "<")) { fwrite(STDERR, "PHP 8.2+ required\n"); exit(1); } foreach (["gd", "json", "pdo", "mbstring"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); } } echo "PHP Version: " . PHP_VERSION . "\n";'
    composer_home=$(resolve_composer_home)
    for directory in "$PLUGIN_DIR" "$LIBRENMS_PATH" "$composer_home" \
        "$PLUGIN_DIR/vendor" "$PLUGIN_DIR/vendor/composer" "$LIBRENMS_PATH/vendor" "$LIBRENMS_PATH/vendor/composer" \
        "$LIBRENMS_PATH/storage/cache" "$PLUGIN_DIR/output" "$PLUGIN_DIR/output/maps" "$PLUGIN_DIR/output/cache" "$PLUGIN_DIR/output/thumbnails"; do
        require_install_writable "$directory" directory
    done
    for file in "$PLUGIN_DIR/composer.lock" "$LIBRENMS_PATH/composer.json" "$LIBRENMS_PATH/composer.lock"; do
        require_install_writable "$file" file
    done
    for file in "$composer_home/config.json" "$composer_home/auth.json"; do
        if [ -e "$file" ] || [ -L "$file" ]; then
            if [ ! -f "$file" ] || [ ! -r "$file" ]; then
                echo "Error: Composer configuration is not readable: $file" >&2
                exit 1
            fi
        fi
    done
}

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
    local composer_home saved_umask
    local -a install_lock_fds=()
    # Only exec preserves ownership; forked children must contend on fresh descriptors.
    if [ "${WEATHERMAPNG_LOCK_OWNER_PID:-}" != "$BASHPID" ]; then
        unset WEATHERMAPNG_ROOT_LOCK_FD WEATHERMAPNG_CHECKOUT_LOCK_FD WEATHERMAPNG_COMPOSER_LOCK_FD
    fi
    if ! command -v flock >/dev/null; then
        echo 'Error: flock is required to serialize installers; install util-linux and retry.' >&2
        exit 1
    fi
    LIBRENMS_PATH=$(cd "$LIBRENMS_PATH" && pwd -P)
    composer_home=$(resolve_composer_home)
    saved_umask=$(umask)
    umask 077
    if ! mkdir -p "$LIBRENMS_PATH/storage/cache" "$composer_home"; then
        umask "$saved_umask"
        echo 'Error: Cannot create installer lock directories; check LibreNMS and Composer home permissions.' >&2
        exit 1
    fi
    umask "$saved_umask"
    composer_home=$(cd "$composer_home" && pwd -P)
    INSTALL_COMPOSER_HOME=$composer_home
    # An explicit relative home must stay the same across installer directory changes.
    if [ -n "${COMPOSER_HOME:-}" ]; then export COMPOSER_HOME=$composer_home; fi
    install_lock WEATHERMAPNG_ROOT_LOCK_FD 'LibreNMS installation' "$LIBRENMS_PATH/storage/cache/.weathermapng-install.lock"
    install_lock WEATHERMAPNG_CHECKOUT_LOCK_FD 'plugin checkout' "$PLUGIN_DIR/.weathermapng-install.lock"
    install_lock WEATHERMAPNG_COMPOSER_LOCK_FD 'Composer home' "$composer_home/.weathermapng-install.lock"
    export WEATHERMAPNG_LOCK_OWNER_PID=$BASHPID
}

prepare_install_repository() {
    php -r '
    try {
        $file = $argv[3];
        if (strpbrk($argv[3], "*?{}[]") !== false) {
            throw new RuntimeException("selected checkout path contains glob characters; move it to a plain path before retrying");
        }
        chdir($argv[2]);
        $globalExists = false;
        foreach ([$argv[1] . "/config.json", $argv[2] . "/composer.json"] as $index => $file) {
            if ($index === 0 && !file_exists($file) && !is_link($file)) {
                continue;
            }
            if (!is_file($file) || !is_readable($file) || ($contents = @file_get_contents($file)) === false) {
                throw new RuntimeException("configuration is not readable");
            }
            $config = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            if (!$config instanceof stdClass) {
                throw new RuntimeException("configuration must be a JSON object");
            }
            $repositories = property_exists($config, "repositories") ? $config->repositories : new stdClass;
            if (!is_object($repositories) && !is_array($repositories)) {
                throw new RuntimeException("repositories must be an object or array");
            }
            $managed = [];
            foreach ((array) $repositories as $name => $entry) {
                if ($name === "weathermapng") {
                    $managed[] = $entry;
                } elseif (is_object($entry) && ($entry->name ?? null) === "weathermapng") {
                    $managed[] = $entry;
                } elseif (is_object($entry) && property_exists($entry, "weathermapng")) {
                    $managed[] = $entry->weathermapng;
                }
            }
            if (count($managed) > 1) {
                throw new RuntimeException("multiple managed entries exist");
            }
            if (!$managed) {
                continue;
            }
            $repository = $managed[0];
            if (!is_object($repository) || ($repository->type ?? null) !== "path"
                || !is_string($repository->url ?? null)
                || (isset($repository->name) && $repository->name !== "weathermapng")) {
                throw new RuntimeException("managed entry is not a path repository for the selected checkout");
            }
            $path = $repository->url;
            if (strpbrk($path, "*?{}[]") !== false) {
                throw new RuntimeException("managed path contains glob characters; set it to a plain checkout path explicitly");
            }
            if (str_starts_with($path, "~/")) {
                $home = getenv("HOME") ?: (function_exists("posix_getpwuid") ? (posix_getpwuid(posix_getuid())["dir"] ?? false) : false);
                if ($home === false) throw new RuntimeException("cannot resolve home directory in managed path");
                $path = $home . substr($path, 1);
            } elseif (preg_match("~^\\$([A-Za-z0-9_]+)(.*)$~", $path, $match)
                || preg_match("~^%([A-Za-z0-9_]+)%(.*)$~", $path, $match)) {
                $value = getenv($match[1]);
                if ($value === false) throw new RuntimeException("managed path environment variable is unset");
                $path = $value . $match[2];
            }
            if (realpath($path) !== $argv[3]) {
                throw new RuntimeException("managed entry is not a path repository for the selected checkout");
            }
            if ($index === 0) {
                $globalExists = true;
            }
        }
        if (!$globalExists) {
            $file = $argv[1] . "/config.json";
            if ((file_exists($file) || is_link($file)) && !is_writable($file)) {
                throw new RuntimeException("global configuration is not writable for repository registration");
            }
            echo json_encode(["type" => "path", "url" => $argv[3], "options" => ["symlink" => true]], JSON_THROW_ON_ERROR);
        }
    } catch (Throwable $error) {
        fwrite(STDERR, "Error: Refusing to change repositories.weathermapng in " . $file . ": " . $error->getMessage()
            . ". Inspect that entry and use its existing checkout or deliberately correct the configuration before retrying.\n");
        exit(1);
    }
    ' "$INSTALL_COMPOSER_HOME" "$LIBRENMS_PATH" "$PLUGIN_DIR"
}
