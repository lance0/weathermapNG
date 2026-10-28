# WeathermapNG Deployment Guide

Operational checks for WeathermapNG. Installation and recovery commands live in
[INSTALL.md](INSTALL.md).

For a first install, start with [INSTALL.md](INSTALL.md). This document focuses on production checks, Docker notes, monitoring, backup, and recovery.

For stable native/Docker installation, plugin upgrades, persistent configuration,
backup/recovery, and targeted diagnostics, use the canonical
[installation guide](INSTALL.md). It owns the command sequences and lifecycle
requirements, including duplicate plugin-row normalization.

## Health And Readiness

Public probe endpoints:

```bash
curl -f https://librenms.example.com/plugin/WeathermapNG/health
curl -f https://librenms.example.com/plugin/WeathermapNG/ready
curl -f https://librenms.example.com/plugin/WeathermapNG/live
```

Authenticated admin/detail endpoints:

- `/plugin/WeathermapNG/health/stats`
- `/plugin/WeathermapNG/health/detailed`
- `/plugin/WeathermapNG/metrics`

The detail endpoints require an authenticated LibreNMS administrator.

## Verification Checklist

After install or upgrade:

1. Run `php artisan route:list | grep -iE 'weathermap|wmng'` from `/opt/librenms`.
2. Confirm the LibreNMS menu shows WeathermapNG or Network Maps.
3. Visit `/plugin/WeathermapNG`.
4. Create or open a map.
5. Open the editor.
6. Open the embed view.
7. Check `/plugin/WeathermapNG/ready`.
8. Run LibreNMS `validate.php`; ignore `wmng_*` extra-table warnings unless you are uninstalling.
9. If `validate.php` reports `utf8mb4_bin` collation on `wmng_map_templates.config`, `wmng_nodes.meta`, `wmng_maps.options`, or `wmng_links.style`, treat that as expected JSON-column behavior.

For duplicate plugin rows, follow the [targeted recovery procedure](INSTALL.md#diagnostics-and-targeted-recovery), which uses the same installer and preserves the retained row settings.

## Background Poller

WeathermapNG can render live data from LibreNMS RRD files on demand. The optional poller script remains available for environments that want scheduled background work:

```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1
```

Use the poller only if it fits your deployment model. The web views and live endpoints should still be validated separately.

## Backup, recovery, and installation problems

Follow [backup and recovery](INSTALL.md#backup-and-recovery) and
[targeted diagnostics](INSTALL.md#diagnostics-and-targeted-recovery). The legacy
`bin/backup.php` command is retired; full database/config backups include templates,
saved versions, and plugin settings.

### RRD Or Traffic Problems

- Verify the link has valid LibreNMS port associations.
- Check that the LibreNMS user can read the RRD directory.
- Use demo mode to separate UI problems from data-source problems.
- As of v1.13.0, flow particles, labels, and badges on curved links follow the visible curve via a densely sampled bezier path; if they appear misaligned, see the Diagnostics section below.


### Diagnostics

When investigating rendering complaints such as "dots are off the line" or misaligned particles, append a query param to any map or embed URL:

- `?debugDots=1` — draws a magenta cross at each particle position and shows a transform readout (scale, ox, oy, dpr) in the top-left corner.
- `?debugDots=2` — shows a full on-screen panel with the entire render-pipeline state: map data, live attach, transport, RAF, transforms, canvas geometry, CSS position, and browser zoom.

Both modes are off by default and have zero production impact when the parameter is absent. The v1.13.1 patch fixed overlay canvas alignment (canvases pinned `top:0/left:0` absolute; overlay composes at the static frame's transform snapshot), so if dots still float after upgrading, use `?debugDots=2` to capture the remaining state mismatch.

## Security Notes

- Keep WeathermapNG under LibreNMS authentication for editor, map management, metrics, and detailed health data.
- Public health endpoints should remain minimal.
- Keep file ownership aligned with the LibreNMS runtime user.
- Do not expose backup files, `.env`, logs, or SQL dumps under the web root.

### Authorization Model

As of v1.7.0, WeathermapNG enforces a two-tier authorization model on top of LibreNMS `web` + `auth` middleware:

- **Ordinary read endpoints** accept authenticated LibreNMS users: maps/editor,
  embed, JSON and image export, live data, SSE, device/port lookups, and templates.
- **Admin operational endpoints** (`health/detailed`, `health/stats`, `metrics`,
  and `diagnostics`) require an authenticated administrator.
- **Explicit embed tokens** authorize only `/embed/{map}`,
  `/api/maps/{map}/json`, `/api/maps/{map}/live`, and `/api/maps/{map}/sse` under
  `/plugin/WeathermapNG`. They do not authorize the editor, admin endpoints, or
  mutations.
- **Mutation endpoints** require an admin user — `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`. This covers creating, updating, and deleting maps, nodes, and links; saving a map; importing a map; running auto-discovery; creating, updating, and deleting templates; creating a map from a template; and running the install controller.

The three public probe endpoints — `/health`, `/ready`, and `/live` — are intentionally unauthenticated so external health checks can reach them.

This model replaces the older per-map policy approach. The `MapPolicy` and `NodePolicy` classes were removed in v1.7.0; authorization is now enforced at the controller level using LibreNMS's global admin check, and there is no per-map ownership configuration to maintain.

## Compatibility

| WeathermapNG | LibreNMS | PHP | Database |
|--------------|----------|-----|----------|
| 1.7.0+ | Latest stable recommended | 8.2+ | LibreNMS MySQL/MariaDB database |

PostgreSQL is not currently documented as a supported production target for this plugin.
