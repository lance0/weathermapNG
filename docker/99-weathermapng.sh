#!/usr/bin/with-contenv bash
# Run after LibreNMS has generated its config and initialized its database.
set -Eeuo pipefail
exec bash "${LIBRENMS_PATH:-/opt/librenms}/html/plugins/WeathermapNG/quick-install.sh"
