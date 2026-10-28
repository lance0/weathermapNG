# Release Readiness Checklist

Pre-release validation flow and manual QA for tagging a WeathermapNG release.
Applies to all versions. SemVer guidelines and version-source rules at the bottom.

## 1. Pre-release Validation Flow

The `just` recipes automate most of this. Run them in order; stop and fix if
anything fails.

1. **Run the full pre-flight gate** (lint, metadata, docs, security, tests):
   ```bash
   just check
   ```

2. **Bump version sources** (VERSION, composer.json, README badge, ROADMAP
   status):
   ```bash
   just release-bump v<version>
   ```

3. **Update the tagged install examples** in `docs/INSTALL.md` for the release version, then **add the CHANGELOG.md entry** — `just release-bump` warns if a
   `## [<version>]` section is missing; write it (dated, above the previous
   release section) before continuing.

4. **Commit the version bump** and verify CI is green on the release branch
   for all workflows in `.github/workflows/`:
   - `ci.yml`
   - `installation-tests.yml`
   - `embed-auth.yml` (called by CI and the release workflow)
   - `release.yml` (validates on tag)

5. **Run the manual QA checklist** (section 2 below).

6. **Tag and push the release** — runs the full check gate, then tags:
   ```bash
   just release-tag v<version>
   ```

7. **The Release workflow publishes the GitHub release** — it runs the full
   test suite, validates that the tag matches `VERSION`/`composer.json`/
   `CHANGELOG.md`, extracts the matching `CHANGELOG.md` section as release
   notes (title `v<version>`), and attaches the `.zip`/`.tar.gz` archives.
   No fallback: a missing CHANGELOG section or a red suite blocks the release.

## 2. Manual QA Checklist

Run each path against a real LibreNMS instance before tagging. Any failure blocks
the release.

- **Editor**: create a new map; add nodes and links; drag nodes; save; undo/redo;
  zoom and pan.
- **Embed**: open an embedded map; confirm it renders; verify live updates via SSE;
  verify embed controls are present and functional.
  CI's `embed-auth.yml` also checks real session cookies with token auth configured
  and unconfigured, token-only access, route binding, and Chromium viewer navigation.
  Run the HTTP checks locally with `just test-integration` against `librenms-dev`.
- **Settings**: open the settings page; confirm it is admin-only (non-admin gets
  denied); confirm existing settings load and save.
- **Install**: on a clean LibreNMS install, run `quick-install.sh`; confirm
  WeathermapNG routes appear under the LibreNMS plugin menu.
- **Upgrade**: select the candidate release from the previous release and follow
  [the canonical upgrade guide](docs/INSTALL.md#updating); confirm existing maps,
  templates, saved versions, settings, editor and embed access survive. The lifecycle
  and full scratch database/config recovery controls run in `embed-auth.yml`.

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
