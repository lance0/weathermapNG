# WeathermapNG Versioning

How plugin releases are versioned, how saved map versions are stored and
restored, and where the two meet.

| Task | Section |
|------|---------|
| Bump or tag a release | [Version sources](#version-sources) · [Release coupling](#release-coupling) |
| Inspect a saved snapshot | [Map version data model](#map-version-data-model) |
| Call the history API | [API shape](#api-shape) |
| Restore a map safely | [Safe restore](#safe-restore) |
| Compare two versions | [Visual comparison](#visual-comparison) |
| Check what is not built | [Not implemented](#not-implemented) |

## Version sources

Plugin releases follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

| Bump | Use |
|------|-----|
| Patch | Bug fixes, install fixes, documentation corrections, UI polish that does not change workflows. |
| Minor | New user-facing functionality, endpoints, editor workflows, or supported install behavior. |
| Major | Breaking changes, removed public behavior, incompatible install changes, or unsupported data migrations. |

These sources must agree on every release:

| Source | Requirement |
|--------|-------------|
| `VERSION` | Canonical version string, one line, for example `1.16.0`. |
| `composer.json` | The `"version"` field equals `VERSION` exactly. |
| `CHANGELOG.md` | Contains `## [<version>] - <YYYY-MM-DD>` for the release. |
| `README.md` | Version badge and tagged install example match the release. |
| `ROADMAP.md` | Header states the current release; the status heading matches it. |
| Git tag | `v<version>` matches `VERSION`. |

`just release-bump v<version>` writes `VERSION`, `composer.json`, the README
version badge, the ROADMAP last-reviewed line, and the `## Current Status`
heading. It does not write the changelog entry or update the README install
example. `just check-metadata` validates `VERSION`, `composer.json`, and the
presence of the changelog section; the remaining entries are reviewed by hand.

## Map version data model

Map history is stored per map in `wmng_map_versions`. It exists for rollback and
auditability while editing, not for whole-install backup.

| Column | Content |
|--------|---------|
| `id` | Version id, used by restore and compare routes. |
| `map_id` | Owning map; deletes with the map. |
| `name` | Version name, required, up to 100 characters. |
| `description` | Optional text, up to 1000 characters. |
| `config_snapshot` | JSON snapshot of the map, nodes, and links. |
| `created_by` | LibreNMS user id when available. |
| `created_at` | Creation timestamp; versions are not updated after creation. |

The table is created by `database/setup.php` on fresh installs and upgrades, and
by the matching migration.

A snapshot contains:

- `map`: id, name, title, width, height, background, description,
  `parent_map_id`, map `options`, and raw width/height.
- `nodes`: keyed by id, with label, coordinates, `device_id`, and `meta`.
- `links`: keyed by id, with endpoints, port ids, `bandwidth_bps`, and style.

Rows remain until deleted. There is no automatic pruning, retention policy, or
scheduled cleanup. The version list shows the newest 20 entries; the history
response reports that as `display_limit` and keeps `max_versions` as a
compatibility alias. Stored history is not limited to 20 versions.

## API shape

The version routes are registered in `routes/web.php` and are admin-only,
including list, show, compare, and export. The editor's Version History modal
uses the same routes.

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions` | List versions for a map |
| `POST` | `/plugin/WeathermapNG/api/maps/{map}/versions` | Create a named version |
| `GET` | `/plugin/WeathermapNG/api/versions/{versionId}` | Show version details and snapshot |
| `POST` | `/plugin/WeathermapNG/api/versions/{versionId}/restore` | Restore the map to a version |
| `GET` | `/plugin/WeathermapNG/api/versions/{versionId}/compare/{compareId}` | Compare two versions |
| `DELETE` | `/plugin/WeathermapNG/api/versions/{versionId}` | Delete one version |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions/export` | Export all versions as JSON |

Creating a version requires `name` and accepts an optional `description`. The
compare response returns flat `nodes_added`, `nodes_removed`, `nodes_modified`,
`links_added`, `links_removed`, and `links_modified` lists plus both snapshots.
[API.md](API.md) is the authoritative route and response reference, including
the version routes and authentication tiers for every other route.

## Safe restore

Restoring validates the snapshot and its references before changing the map.
One transaction then:

1. Saves a **Before restore #<id>** checkpoint.
2. Replaces the map's nodes and links with the selected snapshot.
3. Saves a **Restored version #<id>** entry.

Existing saved versions are unchanged; restore the checkpoint to recover the
state the operation replaced. Invalid snapshots return `422` with a redacted
message, and database failures roll back the map and the new history.

New snapshots include complete options, description, parent map, node metadata,
and legacy canvas dimensions. Older snapshots restore only the fields they
recorded; omitted settings are not guessed. Coordinate restores with active
editors: concurrent editing is not a shared, conflict-aware session, and editor
saves do not take the restore lock.

The JSON export includes every saved version for the selected map regardless of
the list's 20-entry display limit. It retains complete snapshots and available
creator names. It is a map-history export, not a full backup; follow
[INSTALL.md](INSTALL.md#backup-and-recovery) for database and configuration
backups.

## Visual comparison

In Version History, choose **Compare** on one version, select another, and run
the comparison. The left view is before; the right view is after. Added,
removed, and modified nodes and links have text markers and colors. Both views
share a scale so position changes remain visible. The table shows before/after
map options, node fields, and link configuration.

Older snapshots did not capture every field; those fields are marked
unavailable. A recorded `null` remains distinct from an unset or unrecorded
value. Matching uses saved node and link ids: editor saves can replace ids, so
some edits appear as a removal plus an addition.

The preview draws straight links; saved bends and styling appear in the details.
Each view displays up to 100 nodes and 150 links, with up to 200 detail rows and
explicit omitted/invalid counts. These are display limits; the response still
contains the complete snapshots. Comparison does not change saved data.

## Release coupling

The full pre-release flow, manual QA, tagging, and workflow checks live in
[RELEASE.md](../RELEASE.md).

### Pre-release checklist

Run the fast checks before the tagged release flow:

```bash
composer validate --no-check-publish
vendor/bin/phpunit --no-coverage
just check-metadata
```

Then confirm:

- `VERSION` matches `composer.json` and the changelog has the release section.
- Install CI, route smoke tests, and the visual comparison pass.
- `quick-install.sh` still registers the package and discovers routes.
- Public probes stay minimal and authenticated detail endpoints stay protected.

This is a pre-flight, not a substitute for the full release flow. Run
`just release-bump <version>`, add the changelog entry, run
`just release-check <version>`, then `just release-tag <version>` after manual
QA.

`release-tag` requires a clean working tree and a matching changelog section.
The Release workflow validates that the tag matches `VERSION`, `composer.json`,
and `CHANGELOG.md`, extracts the matching changelog section as release notes,
and attaches the archives. A mismatch blocks the release; there is no fallback.

## Not implemented

These are not scheduled:

- Configurable retention or automatic snapshot cleanup.
- Conflict detection or edit locking for concurrent map editing. Multi-user
  editing is parked in [ROADMAP.md](../ROADMAP.md#long-term).
- Storage backends other than the database table.
- Version export formats other than JSON.

## Practices

- Save a named version before large layout changes.
- Use clear names such as `before-core-rack-rework` or `wan-cleanup-2026-05`.
- Add descriptions for changes that affect production dashboards.
- Export map versions before risky maintenance.
- Keep database backups that include `wmng_map_versions`.
