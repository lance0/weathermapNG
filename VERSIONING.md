# WeathermapNG Versioning

WeathermapNG has two different versioning concerns:

1. **Plugin release versioning**: the package version users install.
2. **Map version history**: saved snapshots of individual maps.

This document describes both and separates current behavior from planned work.

## Plugin Release Versioning

WeathermapNG follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html) for releases:

- `PATCH`: bug fixes, install fixes, documentation corrections, UI polish that does not change user workflows.
- `MINOR`: new user-facing functionality, new endpoints, new editor workflows, or new supported install/runtime behavior.
- `MAJOR`: breaking changes, removed public behavior, incompatible install changes, or unsupported data migrations.

## Release Metadata

The release version is stored in:

- `VERSION`
- `composer.json`
- `CHANGELOG.md`
- Git tag, such as `v1.6.4`

CI and release validation should keep these aligned. A release tag must match the project version.

## Authorization Model In v1.7.0

v1.7.0 standardized authorization on a simple admin-only model: maps have no per-user ownership and there is no `user_id` column on `wmng_maps`. Any authenticated LibreNMS user can read maps, templates, lookups, and health detail; all `POST`/`PUT`/`PATCH`/`DELETE` operations require an admin (`hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`), enforced by the shared `AdminCheck` trait in each controller. Public health/readiness/liveness probes remain unauthenticated. Earlier per-map policy scaffolding (`MapPolicy`, `NodePolicy`) has been removed. See [API.md](API.md) for the per-route breakdown.

## Pre-release Checklist

The full pre-release validation flow — syntax checks, `composer validate`, the test suite, CI verification, version-source alignment, tagging, and manual QA — lives in [RELEASE.md](RELEASE.md). Follow that checklist before tagging any release. The quick checks below are a fast pre-flight, not a substitute for the full release readiness flow:

```bash
composer validate --no-check-publish
vendor/bin/phpunit --no-coverage
```

Also verify:

- `VERSION` matches `composer.json`.
- `CHANGELOG.md` has an entry for the release.
- Install CI and route smoke tests pass.
- `quick-install.sh` still registers the package and discovers routes.
- Public health/readiness endpoints remain minimal.
- Authenticated detail/metrics endpoints remain protected.
- Read endpoints stay open to authenticated users; mutation endpoints stay admin-only.

## Current Map Version History Foundation

Map versioning stores map snapshots in the `wmng_map_versions` table. It is intended for rollback and auditability while editing maps.

Implemented foundation:

- Data model and table for named map versions with optional descriptions.
- Service/controller code for saving, restoring, listing, comparing, deleting, and exporting versions.
- Editor UI hooks for version actions.
- Auto-save support from the editor flow.
- Retain snapshots of nodes, links, and map settings.

Before documenting version-history endpoints as public API, verify they are registered in `routes/web.php` and covered by route tests.

## Map Version Data

Each saved version includes:

- Map ID
- Version name
- Optional description
- Serialized map snapshot
- Creator/user reference when available
- Creation timestamp

The table is created by `database/setup.php` on fresh installs and upgrades.

## Current API Shape

Routes are registered from `routes/web.php` through Laravel package discovery. That file is the authoritative route list.

The codebase includes `MapVersionController`, `MapVersionService`, model code, and editor UI hooks for version history. If version-history routes are added or restored, update this document and [API.md](API.md) in the same change.

## Planned Map Versioning Work

The following items are planned, not guaranteed current behavior:

- Visual diff between two map versions.
- Side-by-side comparison UI.
- More configurable retention policies.
- Conflict detection for future collaborative editing.
- Storage backends beyond the current database-backed snapshot model.
- Export formats beyond JSON.

These items belong on the roadmap until implemented and tested.

## Best Practices (for when version-history routes are restored)

The version-history controller and service are implemented but its routes are not currently registered in `routes/web.php`, so these actions are not available in the UI today. The guidance below applies once those routes are restored:

- Save a named version before large layout changes.
- Use clear names such as `before-core-rack-rework` or `wan-cleanup-2026-05`.
- Add descriptions for changes that affect production dashboards.
- Export map versions before risky maintenance.
- Keep database backups that include `wmng_map_versions`.
