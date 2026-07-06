# Release Readiness Checklist

Pre-release validation flow and manual QA for tagging a WeathermapNG release.
Applies to all versions. SemVer guidelines and version-source rules at the bottom.

## 1. Pre-release Validation Flow

Run these in order before tagging. Stop and fix if any step fails.

1. **Syntax check all changed PHP files**
   ```bash
   for f in $(git diff --name-only --diff-filter=ACMR HEAD~1 HEAD -- '*.php'); do
     php -l "$f"
   done
   ```
   Container equivalent (LibreNMS image):
   ```bash
   docker run --rm --entrypoint php -v "$PWD":/opt/librenms/html/plugins/WeathermapNG \
     -w /opt/librenms/html/plugins/WeathermapNG librenms/librenms:latest \
     -l <file>
   ```

2. **Validate composer.json**
   ```bash
   composer validate
   ```

3. **Run the test suite**
   ```bash
   vendor/bin/phpunit --no-coverage
   ```
   Container equivalent:
   ```bash
   docker run --rm --entrypoint php -v "$PWD":/opt/librenms/html/plugins/WeathermapNG \
     -w /opt/librenms/html/plugins/WeathermapNG librenms/librenms:latest \
     vendor/bin/phpunit --no-coverage
   ```

4. **Verify CI is green** on the release branch for all workflows in
   `.github/workflows/`:
   - `installation-tests.yml`
   - `ci.yml`
   - `release.yml`

5. **Verify version sources match** — `VERSION` and `composer.json` `"version"`
   must be identical, and `CHANGELOG.md` must have a matching section.

6. **Update CHANGELOG.md** — replace the `## [Unreleased]` heading with the new
   `## [<version>] - <YYYY-MM-DD>` section, or add a new dated section above
   `## [Unreleased]`.

7. **Tag and push the release**
   ```bash
   git tag v<version>
   git push origin v<version>
   ```

8. **Create the GitHub release** — use the matching `CHANGELOG.md` section as
   the release notes. Title it `v<version>`.

## 2. Manual QA Checklist

Run each path against a real LibreNMS instance before tagging. Any failure blocks
the release.

- **Editor**: create a new map; add nodes and links; drag nodes; save; undo/redo;
  zoom and pan.
- **Embed**: open an embedded map; confirm it renders; verify live updates via SSE;
  verify embed controls are present and functional.
- **Settings**: open the settings page; confirm it is admin-only (non-admin gets
  denied); confirm existing settings load and save.
- **Install**: on a clean LibreNMS install, run `quick-install.sh`; confirm
  WeathermapNG routes appear under the LibreNMS plugin menu.
- **Upgrade**: from the previous release, pull latest, run `composer install` and
  `php setup.php`; confirm routes appear and existing maps still load.

## 3. SemVer Guidelines

WeathermapNG follows [Semantic Versioning](https://semver.org/) (`MAJOR.MINOR.PATCH`).

- **Patch** (`1.6.x`): bug fixes and performance improvements only. No new
  user-facing features, no behavior changes.
- **Minor** (`1.7.x`): new user-facing features or behavior changes that are
  backward-compatible (e.g. an authorization model change, new editor actions, new
  config options). Bump when users get something new without breaking their setup.
- **Major** (`2.0.0`): breaking changes to the plugin API, database schema, or
  plugin architecture. Requires a migration path documented in `CHANGELOG.md`.

Bump the `MAJOR.MINOR.PATCH` together; trailing components reset on a higher bump
(`1.7.0` -> `1.7.1` patch, or `1.8.0` minor, or `2.0.0` major).

## 4. Version Sources

Three files must agree on every release. A mismatch fails the release:

- `VERSION` — the canonical version string, one line (e.g. `1.7.0`).
- `composer.json` — the `"version"` field must equal `VERSION` exactly.
- `CHANGELOG.md` — must contain a `## [<version>] - <YYYY-MM-DD>` section whose
  `<version>` matches `VERSION`.

Update all three in the same commit that tags the release.
