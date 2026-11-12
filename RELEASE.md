# Release process

This page is the pre-release validation and manual QA flow for tagging a
WeathermapNG release; versioning rules live in
[docs/VERSIONING.md](docs/VERSIONING.md) and release history in
[CHANGELOG.md](CHANGELOG.md).

## What blocks a release

- `just check` or `just test-js` fails.
- `VERSION`, `composer.json`, and `CHANGELOG.md` disagree, or the changelog has
  no `## [<version>]` section.
- `just release-tag` finds a dirty working tree or an existing tag.
- Any required CI workflow is red, including Embed Authentication and
  Installation Tests.
- Any manual QA item fails.
- `just check-docs` fails: a required document is missing or install guidance
  lost its required literals.

## 1. Pre-release validation flow

Run these in order. Stop and fix anything that fails.

1. **Run the full gate** (lint, metadata, docs, security, PHPUnit) plus the pure
   Node suites, including the documentation link/anchor check:

   ```bash
   just check
   just test-js
   ```

2. **Bump version sources** (`VERSION`, `composer.json`, README badge, ROADMAP
   status). It warns if the changelog section is missing:

   ```bash
   just release-bump v<version>
   ```

3. **Update the tagged install examples** in [docs/INSTALL.md](docs/INSTALL.md)
   for this release, then **add the changelog entry** under
   `## [<version>] - <YYYY-MM-DD>` above the previous release section.

4. **Run the release preflight** — repeats `just check` and confirms `VERSION`
   matches the release target:

   ```bash
   just release-check v<version>
   ```

5. **Commit and push the version bump**, then wait for every workflow in
   `.github/workflows/` to pass:
   - `ci.yml`
   - `installation-tests.yml`
   - `embed-auth.yml` (called by CI and the release workflow)
   - `release.yml` (validates on tag)

6. **Run the manual QA checklist** in section 2 against a real LibreNMS
   instance.

7. **Tag and push** — checks for a clean tree, matching `VERSION`, a changelog
   section, and an unused tag, then runs `just check` before tagging:

   ```bash
   just release-tag v<version>
   ```

8. **The Release workflow publishes the GitHub release.** It runs the full
   suite and Embed Authentication, validates that the tag matches
   `VERSION`/`composer.json`/`CHANGELOG.md`, extracts the matching changelog
   section as release notes (title `v<version>`), and attaches the `.zip` and
   `.tar.gz` archives. There is no fallback: a missing changelog section or a red
   suite blocks publication.

## 2. Manual QA checklist

Run each path against a real LibreNMS instance before tagging. Any failure
blocks the release.

| Area | Checks |
|------|--------|
| Editor | Create a map; add nodes and links; drag nodes; save; undo/redo; zoom and pan. |
| Embed | Open an embedded map; confirm it renders; verify live updates over SSE; verify embed controls and kiosk mode. |
| Settings | Open the settings page; confirm non-admins are denied; confirm existing settings load and save. |
| Install | On a clean LibreNMS install, run `quick-install.sh`; confirm WeathermapNG routes appear under the plugin menu and `/plugin/WeathermapNG` loads. |
| Upgrade | From the previous release, follow [the canonical upgrade guide](docs/INSTALL.md#updating); confirm existing maps, templates, saved versions, settings, editor and embed access survive. |

CI covers part of this checklist. `embed-auth.yml` checks real session cookies
with token auth configured and unconfigured, token-only access, route binding,
Chromium viewer navigation, favorites, version restore, and the install/upgrade
lifecycle. Run the HTTP integration suites locally with `just test-integration`
against the `librenms-dev` stack from `just dev-up`.

## 3. SemVer guidelines

WeathermapNG follows [Semantic Versioning](https://semver.org/)
(`MAJOR.MINOR.PATCH`). Full rules are in [docs/VERSIONING.md](docs/VERSIONING.md).

| Bump | Use |
|------|-----|
| Patch (`1.6.x`) | Bug fixes and performance improvements only. No new user-facing features or behavior changes. |
| Minor (`1.7.x`) | New user-facing features or backward-compatible behavior changes, such as an authorization change, new editor actions, or new config options. |
| Major (`2.0.0`) | Breaking changes to the plugin API, database schema, or plugin architecture. Requires a migration path documented in [CHANGELOG.md](CHANGELOG.md). |

Bump `MAJOR.MINOR.PATCH` together; trailing components reset on a higher bump
(`1.7.0` to `1.7.1` patch, `1.8.0` minor, `2.0.0` major).

## 4. Version sources

These must agree on every release. A mismatch fails `just check-metadata`, the
release preflight, or the Release workflow:

| Source | Requirement |
|--------|-------------|
| `VERSION` | Canonical version string, one line (for example `1.17.0`). |
| `composer.json` | The `"version"` field equals `VERSION` exactly. |
| `CHANGELOG.md` | Contains `## [<version>] - <YYYY-MM-DD>` matching `VERSION`. |
| Git tag | `v<version>` matches `VERSION`; the Release workflow checks this. |

Update all sources in the same commit that tags the release. `just check-metadata`
validates the three files; `just check-docs` validates that required documents
exist and install guidance stays aligned.
