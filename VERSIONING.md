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

## Pre-release Checklist

Before tagging a release:

```bash
composer validate --no-check-publish
composer test
```

Also verify:

- `VERSION` matches `composer.json`.
- `CHANGELOG.md` has an entry for the release.
- Install CI and route smoke tests pass.
- `quick-install.sh` still registers the package and discovers routes.
- Public health/readiness endpoints remain minimal.
- Authenticated detail/metrics endpoints remain protected.

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

## Best Practices

- Save a named version before large layout changes.
- Use clear names such as `before-core-rack-rework` or `wan-cleanup-2026-05`.
- Add descriptions for changes that affect production dashboards.
- Export map versions before risky maintenance.
- Keep database backups that include `wmng_map_versions`.
