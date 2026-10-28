#!/bin/bash
# Update a tracked branch, or reinstall an explicitly selected tag/archive.
set -Eeuo pipefail
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd -P)"
export LIBRENMS_PATH="${LIBRENMS_PATH:-/opt/librenms}"
if [ "$(id -u)" -eq 0 ]; then
    if [ -f /.dockerenv ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
        id librenms >/dev/null
        printf -v update_command 'exec env LIBRENMS_PATH=%q bash %q' "$LIBRENMS_PATH" "$PLUGIN_DIR/deploy.sh"
        exec su -l librenms -s /bin/bash -c "$update_command"
    fi
    echo 'Error: Run deploy.sh as the LibreNMS user, not root.' >&2
    exit 1
fi
step='checking source checkout'
trap 'result=$?; printf "Error: %s failed; existing installation was not reinstalled.\nResolve the checkout/upstream error, then retry as the LibreNMS user: cd %q && LIBRENMS_PATH=%q ./deploy.sh\n" "$step" "$PLUGIN_DIR" "$LIBRENMS_PATH" >&2; exit "$result"' ERR
step='acquiring installer locks'
# shellcheck source=scripts/install-common.sh
source "$PLUGIN_DIR/scripts/install-common.sh"
acquire_install_locks

cd "$PLUGIN_DIR"
if [ -e .git ]; then
    step='checking unstaged tracked changes'
    git diff --quiet
    step='checking staged tracked changes'
    git diff --cached --quiet
    if git symbolic-ref -q HEAD >/dev/null; then
        step='checking branch upstream'
        git rev-parse --verify '@{upstream}' >/dev/null
        step='fast-forwarding tracked branch'
        git pull --ff-only
    else
        echo 'Detached checkout: keeping the explicitly selected release; no Git update performed.'
    fi
else
    echo 'Archive installation: keeping the selected source; no Git update performed.'
fi
exec bash "$PLUGIN_DIR/quick-install.sh"
