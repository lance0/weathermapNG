# WeathermapNG task runner — `just <recipe>`
# Install just first: brew install just   (or cargo install just)
#
# These recipes are thin facades over real entrypoints (quick-install.sh,
# deploy.sh, verify.php, verify-deployment.php, tests/docker-test.sh,
# tests/install-test.sh) so there's a single spelling per workflow with no
# duplicated logic to drift. CI (.github/workflows/) invokes these same
# recipes — local and CI run identical commands.

set shell := ["bash", "-euo", "pipefail", "-c"]

PHP := "php"

VENDOR_BIN := "vendor/bin"

# Path filters that skip CI for docs-only changes.
DOC_PATHS := "**/*.md"

default:
    @just --list

# ===== Development =====

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

# Run the container integration suites against a running librenms-dev stack
test-integration:
    #!/usr/bin/env bash
    set -euo pipefail
    for t in tests/integration_savemaprequest_test.php tests/integration_node_label_invariant_test.php tests/integration_version_test.php tests/integration_embed_auth_test.php tests/integration_install_report_test.php; do
        echo "=== $t ==="
        docker exec -u librenms librenms-dev bash -c "cd /opt/librenms/html/plugins/WeathermapNG && php $t"
    done

# Syntax-check all PHP sources
lint:
    #!/usr/bin/env bash
    set -euo pipefail
    find src config database routes bin -name '*.php' -print0 \
      | xargs -0 -n1 {{PHP}} -l > /dev/null
    echo "All PHP files lint clean."

# Validate all workflow files with actionlint (if installed)
lint-workflows:
    #!/usr/bin/env bash
    set -euo pipefail
    if ! command -v actionlint >/dev/null 2>&1; then
        echo "actionlint not installed — skipping (brew install actionlint)"
        exit 0
    fi
    actionlint

# Validate composer.json and version-source alignment (VERSION, composer.json, CHANGELOG)
check-metadata:
    #!/usr/bin/env bash
    set -euo pipefail
    if command -v composer >/dev/null 2>&1; then
        composer validate --no-check-publish
    else
        echo "composer not on PATH — skipping composer validate (CI runs it)"
    fi
    {{PHP}} <<'PHP'
    <?php
    $version = trim(file_get_contents('VERSION'));
    $composer = json_decode(file_get_contents('composer.json'), true);
    $changelog = file_get_contents('CHANGELOG.md');

    if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
        fwrite(STDERR, "VERSION is not SemVer: {$version}\n");
        exit(1);
    }
    if (($composer['version'] ?? null) !== $version) {
        fwrite(STDERR, "composer.json version does not match VERSION\n");
        exit(1);
    }
    if (! str_contains($changelog, "## [{$version}]")) {
        fwrite(STDERR, "CHANGELOG.md is missing ## [{$version}]\n");
        exit(1);
    }
    echo "Version metadata aligned: {$version}\n";
    PHP

# Check that required docs exist and install guidance stays aligned
check-docs:
    #!/usr/bin/env bash
    set -euo pipefail
    for doc in README.md CHANGELOG.md ROADMAP.md RELEASE.md CONTRIBUTING.md \
               docs/README.md docs/INSTALL.md docs/API.md docs/DEPLOYMENT.md \
               docs/PERFORMANCE.md docs/VERSIONING.md docs/EMBED.md; do
        test -f "$doc" || { echo "MISSING: $doc"; exit 1; }
    done
    for file in quick-install.sh README.md docs/INSTALL.md; do
        grep -qF 'composer config --global repositories.weathermapng' "$file"
        grep -qF 'plugin:add librenms/weathermapng' "$file"
        grep -qF 'verify-deployment.php' "$file"
    done
    echo "Docs checks pass."

# Check no hardcoded secrets in PHP source
check-security:
    #!/usr/bin/env bash
    set -euo pipefail
    if grep -rE "(password|secret|api_key)\s*=\s*['\"][^'\"$][^'\"]{8,}['\"]" \
       --include="*.php" --exclude-dir=vendor --exclude-dir=tests . 2>/dev/null | \
       grep -v "example\|placeholder\|your_\|test_\|dummy\|config\|env"; then
        echo "Warning: Potential hardcoded secrets found"
        exit 1
    fi
    echo "No hardcoded secrets detected"

# lint + metadata + docs + security + test — the "PR-ready" gate (what CI runs)
check: lint check-metadata check-docs check-security test

# ===== Release =====

# Release preflight: all checks plus version metadata for the target version.
# Usage: just release-check v1.15.0
release-check version:
    #!/usr/bin/env bash
    set -euo pipefail
    echo "{{version}}" | sed 's/^v//' > /tmp/wmng-release-version-check
    just check
    v=$(sed 's/^v//' <<< '{{version}}')
    if [ "$(cat VERSION)" != "$v" ]; then
        echo "ERROR: VERSION ($(cat VERSION)) does not match release target {{version}}"
        echo "Run: just release-bump {{version}}"
        exit 1
    fi
    rm -f /tmp/wmng-release-version-check
    echo "Release preflight passed for {{version}}"

# Bump all version sources to the given version (VERSION, composer.json,
# README badge, ROADMAP status). Does NOT write the CHANGELOG entry —
# add the '## [<version>] - <date>' section yourself.
# Usage: just release-bump v1.15.0
release-bump version:
    #!/usr/bin/env bash
    set -euo pipefail
    v=$(sed 's/^v//' <<< '{{version}}')
    old=$(cat VERSION)

    echo "$v" > VERSION

    python3 - "$old" "$v" <<'PY'
    import sys, re
    old, new = sys.argv[1], sys.argv[2]
    c = open('composer.json').read()
    c = c.replace(f'"version": "{old}"', f'"version": "{new}"')
    open('composer.json', 'w').write(c)
    r = open('README.md').read()
    r = r.replace(f'version-{old}-0078D4', f'version-{new}-0078D4')
    r = r.replace(f'Version {old}', f'Version {new}')
    open('README.md', 'w').write(r)
    rm = open('ROADMAP.md').read()
    rm = re.sub(r'Last reviewed v[\d.]+ \(\d{4}-\d{2}-\d{2}\)', f'Last reviewed v{new} ({__import__("datetime").date.today()})', rm, count=1)
    rm = re.sub(r'## Current Status: v[\d.]+', f'## Current Status: v{new}', rm, count=1)
    open('ROADMAP.md', 'w').write(rm)
    print(f'bumped {old} -> {new}')
    PY

    if ! grep -q "## \[$v\]" CHANGELOG.md; then
        echo "WARNING: CHANGELOG.md has no '## [$v]' section yet — add it before tagging."
    fi
    just check-metadata || true
    echo "Bumped to $v. Review the diff, add the CHANGELOG entry, commit, then: just release-tag $v"

# Tag and push a release. Requires a clean tree and a matching CHANGELOG
# section. CI validates the rest and publishes the GitHub release.
# Usage: just release-tag v1.15.0
release-tag version:
    #!/usr/bin/env bash
    set -euo pipefail
    v=$(sed 's/^v//' <<< '{{version}}')
    tag="v$v"

    if [ -n "$(git status --porcelain)" ]; then
        echo "ERROR: working tree is not clean — commit or stash first."
        exit 1
    fi
    if [ "$(cat VERSION)" != "$v" ]; then
        echo "ERROR: VERSION ($(cat VERSION)) does not match $tag. Run: just release-bump {{version}}"
        exit 1
    fi
    if ! grep -q "## \[$v\]" CHANGELOG.md; then
        echo "ERROR: CHANGELOG.md is missing '## [$v]' — the release workflow requires it."
        exit 1
    fi
    if git rev-parse -q --verify "refs/tags/$tag" >/dev/null; then
        echo "ERROR: tag $tag already exists."
        exit 1
    fi

    just check

    git tag -a "$tag" -m "$tag"
    git push origin HEAD "$tag"
    echo "Tagged $tag — the Release workflow will validate and publish the GitHub release."

# ===== Install workflows =====

# Full plugin install into the target LibreNMS (core entrypoint)
install LIBRENMS_PATH="/opt/librenms":
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" ./quick-install.sh

# Update an existing installation
update LIBRENMS_PATH="/opt/librenms":
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" ./deploy.sh

# Post-install deployment check against a LibreNMS install root
validate-install LIBRENMS_PATH="/opt/librenms" *args:
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" {{PHP}} verify-deployment.php {{args}}

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

# Sync the working tree into the running librenms-dev container's plugin
# mount (vendor/ excluded — reinstall deps in the container if needed).
dev-sync:
    #!/usr/bin/env bash
    set -euo pipefail
    for f in $(git ls-files 'src/**' 'config/**' 'routes/**' 'resources/**' 'database/**'); do
        docker cp "$f" "librenms-dev:/opt/librenms/html/plugins/WeathermapNG/$f"
    done
    echo "Synced plugin sources to librenms-dev."

# Run the full docker install test suite
test-install:
    bash tests/docker-test.sh

# Run the host-path install test suite
test-install-local INSTALL_DIR="/opt/librenms":
    bash tests/install-test.sh {{INSTALL_DIR}}

# Render index/editor/embed screenshots. OUTDIR override is an env var.
visual URL="http://localhost:18080":
    bash tests/screenshot-check.sh "{{URL}}" "${OUTDIR:-output/screenshots}"

# Inspect a selected LibreNMS installation (pass --json for a support report)
verify-deployment LIBRENMS_PATH="/opt/librenms" *args:
    LIBRENMS_PATH="{{LIBRENMS_PATH}}" {{PHP}} verify-deployment.php {{args}}
