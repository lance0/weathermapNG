# WeathermapNG task runner — `just <recipe>`
# Install: brew install just   (or cargo install just)
#
# Common flows:
#   just test                      # full PHPUnit suite
#   just lint                      # php -l on all src + config + database
#   just check                     # lint + tests

PHP := "php"

VENDOR_BIN := "vendor/bin"

# default recipe: what a fresh contributor actually needs
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

# lint + test
check: lint test

# One static screenshot pass: renders index/editor/embed at 480/768/1920 px
# Requires: a running LibreNMS instance at {URL}. Falls back to docker:
#   just screenshots URL=http://localhost:18080
screenshots URL="http://localhost:18080" OUTDIR="output/screenshots":
    #!/usr/bin/env bash
    set -euo pipefail
    command -v chromium >/dev/null 2>&1 || command -v google-chrome >/dev/null 2>&1 \
        || { echo "chromium/chrome not on PATH — see tests/screenshot-check.sh for alternatives" >&2; exit 2; }
    mkdir -p {{OUTDIR}}
    bash tests/screenshot-check.sh {{URL}} {{OUTDIR}}
    echo "Wrote screenshots to {{OUTDIR}}/"

# Semantic-release: validate + tag + push (see RELEASE.md)
tag version:
    #!/usr/bin/env bash
    set -euo pipefail
    echo "$(sed 's/^v//' <<< '{{version}}')" > VERSION
    {{VENDOR_BIN}}/phpunit tests/VersionMetadataTest.php
    git add VERSION CHANGELOG.md
    git commit -m "v{{version}}: release"
    git tag -a "v{{version}}" -m "v{{version}}"
    git push origin main "v{{version}}"
