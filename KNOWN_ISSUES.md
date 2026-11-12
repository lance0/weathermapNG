# Known Issues

Documented limitations and by-design behavior. Work that is planned rather than
broken is tracked in [ROADMAP.md](ROADMAP.md).

## Platform and data limits

### LibreNMS database only

WeathermapNG uses LibreNMS's MySQL/MariaDB connection. PostgreSQL is not a
supported target ([deployment notes](docs/DEPLOYMENT.md#compatibility)).

### RRD access is required for real traffic

Traffic comes from LibreNMS RRD files and port associations. Without readable
RRD files and valid `port_id` links, maps render without live values; demo mode
can separate a UI problem from a data-source problem
([deployment troubleshooting](docs/DEPLOYMENT.md#rrd-or-traffic-problems)).

### LibreNMS `validate.php` flags plugin tables

Extra `wmng_*` tables and `utf8mb4_bin` JSON columns are expected. Do not change
the JSON column collations to silence the warning
([installation note](docs/INSTALL.md#diagnostics-and-targeted-recovery)).

### Large maps use Canvas 2D

Rendering targets typical LibreNMS topologies (hundreds of nodes). Above that,
the embed degrades rather than switching engines: viewport culling, a
1500-particle cap, and hidden secondary labels below 0.6 zoom
([performance notes](docs/PERFORMANCE.md), [embed behavior](docs/EMBED.md#degradation--performance)).

## Feature limits

### No per-map access control

Any authenticated LibreNMS user can read every map; only administrators can
change content. Favorites are per-user. Per-map visibility would require a
schema and policy change ([authorization model](docs/API.md#authentication--authorization)).

### Restores only cover recorded fields

Map versions snapshot the fields the versioning model records. Older snapshots
can lack settings that newer versions record; the compare view marks missing
historical fields instead of inventing values
([versioning](docs/VERSIONING.md)).

### Legacy `.conf` import is not exhaustive

`LegacyConfService` reads the PHP Weathermap-style construct set that the plugin
renders and round-trips. Unsupported directives are ignored rather than guessed.

### `bin/backup.php` is retired

It exits non-zero without writing. Use a full LibreNMS database and
configuration backup; single-map JSON exports are not a recovery backup
([backup guidance](docs/INSTALL.md#backup-and-recovery)).

## Browser and CI coverage

- Browser tests run in Chromium. Firefox and Safari are not part of CI, so
  keyboard, color-input, and rendering differences there may go unnoticed.
- Screenshot baselines are generated with the pinned Chromium used in CI.
  Regenerate them with `just visual-baseline <url>` when the runner environment
  changes.
- `main` can contain unreleased changes. Install a tagged release for production.

## Operational edge cases

- **No flow particles above 1500 links.** The budget reports zero per-link
  particles rather than exceeding the cap; labels still render.
- **Product of a closed tab.** Live SSE views reconnect automatically; a
  backgrounded tab pauses updates until it becomes visible.
- **Docker file sessions.** The official image keeps sessions in its ephemeral
  filesystem; users may need to log in again after the app container is recreated
  ([Docker notes](docs/INSTALL.md#docker-stable-installation)).
- **Install locks are host-local.** Concurrent installs are serialized on the
  selected root/Composer home with `flock`; this does not coordinate separate
  hosts or network filesystems ([installation](docs/INSTALL.md)).
