# WeathermapNG task runner — `just <recipe>`
# Install just first: brew install just   (or cargo install just)
#
# These recipes are thin facades over real entrypoints (RELEASE.md's
# validation flow, tests/docker-test.sh, verify.php, and tests/screenshot-
# check.sh) so there's a single spelling per workflow with no duplicated
# logic to drift.
#
# Common flows:
#   just check                     # lint + full test suite
#   just visual                    # screenshot capture pass (requires local LibreNMS)
#   just verify-install            # install controller script standalone check

PHP := "php"

VENDOR_BIN := "vendor/bin"

default:
    @just --list

# Install PHP and Composer dependencies (idempotent) — the only recipe
# that contains setup logic of its own, since nothing else covers it.
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

# Full installation workflow against docker (wraps the existing suite)
test-install:
    bash tests/docker-test.sh

# Host path installation test (wraps tests/install-test.sh)
test-install-host-librenms INSTALL_DIR="/opt/librenms":
    bash tests/install-test.sh {{INSTALL_DIR}}

# Render index/editor/embed screenshots at several viewports
# Requires a Chromium-family browser on PATH and a reachable LibreNMS at URL.
visual URL="http://localhost:18080" OUTDIR="output/screenshots":
    bash tests/screenshot-check.sh {{URL}} {{OUTDIR}}

# Verify plugin structure and install readiness (stands alone)
verify:
    {{PHP}} verify.php

# Smoke-check the deployed install (standalone)
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
