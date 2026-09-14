# WeathermapNG task runner — `just <recipe>`
# Install just first: brew install just   (or cargo install just)
#
# These recipes are thin facades over real entrypoints (quick-install.sh,
# deploy.sh, verify.php, verify-deployment.php, tests/docker-test.sh,
# tests/install-test.sh, RELEASE.md's tag flow) so there's a single
# spelling per workflow with no duplicated logic to drift.

PHP := "php"

VENDOR_BIN := "vendor/bin"

default:
    @just --list

# Install PHP and Composer dependencies (idempotent)
setup:
    #!/usr/bin/env bash
    set -euo pipefail
    if ! command -v composer >/dev/null 2>&1; then
        echo "Installing composer to ~/.local/bin ..."
        mkdir -p ~/.local/bin
        curl -sS https://getcomposer.org/installer | {{PHP}} -- --install-dir=$HOME/.local/bin --filename=composer
    fi
    composer install --quiet
    echo "Dependencies installed."

# Run the full PHPUnit suite
test:
    {{VENDOR_BIN}}/phpunit --no-coverage

# Run one test class: just test-one LegacyConfServiceTest
test-one name:
    {{VENDOR_BIN}}/phpunit tests/{{name}}.php

# Syntax-check all PHP sources
lint:
    #!/usr/bin/env bash
    set -euo pipefail
    find src config database routes bin -name '*.php' -print0 \
      | xargs -0 -n1 {{PHP}} -l > /dev/null
    echo "All PHP files lint clean."

# lint + test — the "PR-ready" gate
check: lint test

# ===== Install workflows =====

# Full plugin install into the target LibreNMS (core entrypoint)
install LIBRENMS_PATH="/opt/librenms":
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" ./quick-install.sh

# Update an existing installation
update LIBRENMS_PATH="/opt/librenms":
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" ./deploy.sh

# Post-install deployment check against a LibreNMS install root
validate-install LIBRENMS_PATH="/opt/librenms":
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" {{PHP}} verify-deployment.php

# Verify plugin structure and install readiness (stands alone)
verify:
    {{PHP}} verify.php

# ===== Docker dev stack =====

# Spin up the docker LibreNMS dev stack with the plugin mounted live
dev-up:
    docker compose -f docker-compose.dev.yml up -d
    @echo "Dev stack starting — visit http://localhost:8000 once nginx is running (first boot takes a few minutes)."

# Stop the docker dev stack (data volumes preserved)
dev-stop:
    docker compose -f docker-compose.dev.yml down

# Reset the docker dev stack including data volumes (destructive)
dev-reset:
    docker compose -f docker-compose.dev.yml down -v

# Run the full docker install test suite
test-install:
    bash tests/docker-test.sh

# Run the host-path install test suite
test-install-local INSTALL_DIR="/opt/librenms":
    bash tests/install-test.sh {{INSTALL_DIR}}


# Render index/editor/embed screenshots. OUTDIR override is an env var.
visual URL="http://localhost:18080":
    bash tests/screenshot-check.sh "{{URL}}" "${OUTDIR:-output/screenshots}"

# Smoke-check a deployed plugin over HTTP
verify-deployment URL="http://localhost:18080":
    {{PHP}} verify-deployment.php {{URL}}

# Release: version bump + changelog commit + tag + push (see RELEASE.md)
tag version:
    echo "$(sed 's/^v//' <<< '{{version}}')" > VERSION
    {{VENDOR_BIN}}/phpunit tests/VersionMetadataTest.php
    git add VERSION CHANGELOG.md
    git commit -m "v{{version}}: release"
    git tag -a "v{{version}}" -m "v{{version}}"
    git push origin main "v{{version}}"
