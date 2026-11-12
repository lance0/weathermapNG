# Deployment checks for WeathermapNG

Production checks for an installed WeathermapNG: probes, verification,
debugging, and authorization. Installation, upgrades, and recovery commands live
in [INSTALL.md](INSTALL.md).

## Probes

| Endpoint | Auth | Purpose |
|---|---|---|
| `/plugin/WeathermapNG/health` | Public | Database reachability as a boolean; `503` when unhealthy. |
| `/plugin/WeathermapNG/ready` | Public | Database connectivity plus a present output directory; `503` when not ready. |
| `/plugin/WeathermapNG/live` | Public | Process liveness only; no dependency checks. |
| `/plugin/WeathermapNG/health/stats` | Admin | Map, node, and link counts, database size, cache information. |
| `/plugin/WeathermapNG/health/detailed` | Admin | Full redacted checks array. |
| `/plugin/WeathermapNG/metrics` | Admin | Prometheus-style metrics. |
| `/plugin/WeathermapNG/diagnostics` | Admin | The `verify-deployment.php` report as an HTML page. |

Probe example:

```bash
curl -f https://librenms.example.com/plugin/WeathermapNG/health
curl -f https://librenms.example.com/plugin/WeathermapNG/ready
curl -f https://librenms.example.com/plugin/WeathermapNG/live
```

The three probes are intentionally unauthenticated so external health checks can
reach them. They expose no version, path, filesystem, or configuration details.
Details and response shapes are in the [API reference](API.md#health-and-metrics-routes).

## Verification checklist

Run after install or upgrade:

1. From `/opt/librenms`, run `php artisan route:list | grep -iE 'weathermap|wmng'`
   and confirm the plugin routes are listed.
2. Confirm the LibreNMS menu shows WeathermapNG or Network Maps.
3. `curl -f` the `/plugin/WeathermapNG/ready` URL; it returns HTTP 200.
4. Open `/plugin/WeathermapNG`; the index loads existing maps or the empty-state
   actions.
5. Create or open a map and confirm the editor renders its nodes and links.
6. Open the embed view for the same map and confirm traffic overlays render, or
   that demo mode does.
7. As the runtime user in the plugin checkout, run
   `LIBRENMS_PATH=/opt/librenms php verify-deployment.php --json` and confirm
   every reported check is `ok`.
8. Run LibreNMS `validate.php`; `wmng_*` extra-table warnings are expected unless
   you are uninstalling.
9. Treat `utf8mb4_bin` collation warnings on `wmng_map_templates.config`,
   `wmng_nodes.meta`, `wmng_maps.options`, and `wmng_links.style` as expected
   MySQL/MariaDB JSON-column behavior. Do not change those collations.
10. For duplicate plugin rows, follow the
    [targeted recovery procedure](INSTALL.md#diagnostics-and-targeted-recovery),
    which uses the same installer and preserves the retained row's settings.

## Background poller

The web views and live endpoints read RRD data on demand, so the poller is
optional. Use it only when you want map data precomputed on a schedule:

```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1
```

Do not add the poller to fix missing traffic or a failing probe. Diagnose those
with the checklist above and the sections below; validate the web views and live
endpoints separately from any background job.

## RRD or traffic problems

- Verify the link has valid LibreNMS port associations.
- Check that the LibreNMS user can read the RRD directory.
- Use demo mode to separate UI problems from data-source problems.
- Flow particles, labels, and badges on curved links follow the visible curve
  through a densely sampled bezier path. If they appear misaligned, use the
  diagnostics below.

### Diagnostics

When investigating rendering complaints such as "dots are off the line" or
misaligned particles, append a query parameter to any map or embed URL:

- `?debugDots=1` draws a magenta cross at each particle position and shows a
  transform readout (scale, ox, oy, dpr) in the top-left corner.
- `?debugDots=2` shows a full on-screen panel with the entire render-pipeline
  state: map data, live attach, transport, RAF, transforms, canvas geometry, CSS
  position, and browser zoom.

Both modes are off by default and have no production effect when the parameter is
absent. The overlay canvas is pinned at `top:0/left:0` and composes at the static
frame's transform snapshot. If dots still float, use `?debugDots=2` to capture
the remaining state mismatch.

## Backup and recovery

Follow [backup and recovery](INSTALL.md#backup-and-recovery) and
[targeted diagnostics](INSTALL.md#diagnostics-and-targeted-recovery). The legacy
`bin/backup.php` command is retired; full database/config backups include
templates, saved versions, and plugin settings.

## Security notes

- Keep WeathermapNG under LibreNMS authentication for the editor, map management,
  metrics, and detailed health data.
- Keep public health endpoints minimal.
- Keep file ownership aligned with the LibreNMS runtime user.
- Do not expose backup files, `.env`, logs, or SQL dumps under the web root.

### Authorization model

Every route runs behind LibreNMS `web` + `auth` middleware or the embed-token
middleware, which falls back to the full session stack. Only the three public
probes skip authentication. The admin gate is the shared `AdminCheck` trait:
`hasGlobalAdmin()`, `isAdmin()`, a LibreNMS `level >= 10`, or `hasRole('admin')`;
anything else aborts with `403`.

| Tier | Who | Covers |
|---|---|---|
| Public probe | Unauthenticated | `/health`, `/ready`, `/live`. |
| Authenticated read | Any LibreNMS user | Map index, editor, view, embed, JSON, live data, SSE, export, and template list/show. |
| Per-user write | Any authenticated user | Favorite toggle on `/map/{map}/favorite`. |
| Admin read | Administrator | Device and port lookups, map version endpoints, `health/detailed`, `health/stats`, `metrics`, and `diagnostics`. |
| Admin write | Administrator | Map, node, and link create/update/delete, full-map save, import, auto-discovery, template mutations, creating a map from a template, and the install runner. |
| Embed token | Bearer token or `?token=` | `/embed/{map}`, `/api/maps/{map}/json`, `/api/maps/{map}/live`, and `/api/maps/{map}/sse`. Tokens authorize nothing else. |

There is no per-map ownership configuration; authorization is enforced in the
controllers. The route-by-route list, including the token setup, is in the
[API reference](API.md).

## Compatibility

| WeathermapNG | LibreNMS | PHP | Database |
|---|---|---|---|
| 1.17.0 | 26.10.x tested; latest stable recommended | 8.2+ (8.4 and 8.5 tested) | LibreNMS MySQL/MariaDB |

PostgreSQL is not documented as a supported production target for this plugin.
