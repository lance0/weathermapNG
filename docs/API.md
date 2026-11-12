# WeathermapNG API and route reference

Every HTTP route the plugin registers, the auth tier that guards it, and the
request/response shapes used by the editor, embed viewer, and CLI.

The tables below match `routes/web.php` (47 routes). If routes are missing,
rerun package discovery from the LibreNMS root and check the list:

```bash
cd /opt/librenms
php artisan route:list | grep -i weathermapng
```

<a id="authentication--authorization"></a>

## Auth tiers

| Tier | Routes | Enforced by |
|------|--------|-------------|
| Public | `health`, `ready`, `live` | No middleware. Payloads carry no version, path, or configuration detail. |
| Session | Index/editor/view pages, export, template reads, favorite toggle | LibreNMS `web` + `auth`; any logged-in user. |
| Session or embed token | `embed`, `json`, `live`, `sse` | `EmbedTokenAuth`: `Authorization: Bearer` or `?token=`, otherwise the full `web` + `auth` stack. |
| Admin | Installer, 22 of the 23 write routes, lookups, versions, detailed health, diagnostics | `AdminCheck::requireAdmin()` → `abort(403)`. |

`requireAdmin()` accepts `hasGlobalAdmin()`, `isAdmin()`, a LibreNMS
`level >= 10`, or `hasRole('admin')`.

There is no per-map ownership: any logged-in user can read any map. All write
routes require admin except the per-user [favorite toggle](#favorites).

## Route index

| Group | Routes | Auth tier |
|-------|--------|-----------|
| [Pages](#pages) | 3 | Session |
| [Installer](#installer) | 2 | Admin |
| [Map data](#map-data) | 4 | Session or embed token |
| [Map management](#map-management) | 5 | Admin |
| [Nodes](#nodes) | 4 | Admin |
| [Links](#links) | 4 | Admin |
| [Favorites](#favorites) | 1 | Session |
| [Lookups](#lookups) | 2 | Admin |
| [Templates](#templates) | 6 | Session reads, admin writes |
| [Versions](#versions) | 7 | Admin |
| [Export and import](#export-and-import) | 2 | Session export, admin import |
| [Health and metrics routes](#health-and-metrics-routes) | 7 | 3 public, 4 admin |

Webhooks are outbound only; see [Webhooks](#webhooks).

## Pages

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG` | Session | Map index. Redirects to the installer when the `wmng_` tables are missing. |
| `GET` | `/plugin/WeathermapNG/editor/{map?}` | Session | Editor for one map, or a new map when `{map}` is omitted. |
| `GET` | `/plugin/WeathermapNG/view/{map}` | Session | Redirects to the embed view. `404` when the map does not exist. |

## Installer

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/install` | Admin | Requirements/status page. |
| `POST` | `/plugin/WeathermapNG/install` | Admin | Run plugin activation. |

Both actions call `requireAdmin()`, so the installer is not a public first-run
page. `POST` response:

```json
{
  "success": true,
  "message": "WeathermapNG installed successfully!",
  "redirect": "https://librenms/plugin/WeathermapNG"
}
```

## Map data

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/embed/{map}` | Session or embed token | HTML viewer; see [EMBED.md](EMBED.md). |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/json` | Session or embed token | Serialized map model. |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/live` | Session or embed token | Current traffic, status, metrics, and alerts. |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/sse` | Session or embed token | Server-Sent Events stream of the live payload. |

The `sse` endpoint accepts two query parameters:

| Parameter | Range | Default | Meaning |
|-----------|-------|---------|---------|
| `interval` | 1–60 | 5 | Seconds between frames. |
| `max` | 5–600 | 300 | Seconds before the stream closes. |

Frames are `data: <json>` lines with the live payload; a `: heartbeat` comment
keeps idle proxies open.

### Live payload

```json
{
  "ts": 1738284000,
  "links": {
    "1": {
      "in_bps": 52428800,
      "out_bps": 104857600,
      "pct": 10.49,
      "err": null
    }
  },
  "nodes": {
    "1": {
      "status": "up",
      "metrics": { "cpu": 12.5, "mem": 41.2 },
      "traffic": {
        "in_bps": 52428800,
        "out_bps": 104857600,
        "sum_bps": 157286400,
        "source": "ports"
      }
    }
  },
  "alerts": {
    "nodes": { "1": { "count": 2, "severity": "critical" } },
    "links": {}
  }
}
```

`links` and `nodes` are keyed by node/link ID. `pct` is `null` when the link has
no bandwidth or no port data; `metrics` values are `null` when the node has no
device or metrics are disabled in config. `traffic.source` is `ports`, `device`,
`demo`, or `none` (no traffic read). `alerts.nodes`
holds an entry for every node backed by a device, with `count` and `severity`
(`warning`, `critical`, or `severe`; `count: 0` when clear).
`alerts.links` lists only links with port alerts, keyed by link ID, with
`count`, `severity`, and the link's `in_bps`, `out_bps`, and `pct`. For
backward compatibility these entries also carry `alert_count` and
`alert_severity` with the same values; new code should use `count`/`severity`.

### Map JSON payload

`GET /api/maps/{map}/json` and `export?format=json` both return the model
produced by `Map::toJsonModel()`:

```json
{
  "_format": "weathermapng-map-v1",
  "id": 1,
  "name": "production_map",
  "title": "Production Network Map",
  "parent_map_id": null,
  "breadcrumb": [
    { "id": 1, "name": "production_map", "title": "Production Network Map" }
  ],
  "width": 1200,
  "height": 800,
  "background": "#ffffff",
  "options": {},
  "nodes": [
    {
      "id": 1,
      "label": "Core Router",
      "x": 400,
      "y": 300,
      "device_id": 42,
      "meta": {},
      "sub_map_id": null,
      "device_name": "core-router-01",
      "status": "up"
    }
  ],
  "links": [
    {
      "id": 1,
      "src": 1,
      "dst": 2,
      "port_id_a": 101,
      "port_id_b": 102,
      "bandwidth_bps": 1000000000,
      "style": {},
      "source_port_name": "Gi0/1",
      "destination_port_name": "Gi0/2",
      "bandwidth_formatted": "1 Gbps"
    }
  ]
}
```

Nested-map fields:

| Field | Type | Meaning |
|-------|------|---------|
| `parent_map_id` | int or null | Parent in the drill-down hierarchy. `null` for a root map. |
| `breadcrumb` | array | Root-to-current chain, each `{id, name, title}`. |
| `sub_map_id` (per node) | int or null | Target map for node click-through in the embed view. Stored in the node's `meta` JSON. |

#### Per-node style fields

Four optional keys in a node's `meta` override the default rendering in the
editor, the embed view, and the SVG export:

| Key | Values | Effect |
|-----|--------|--------|
| `meta.color` | `#RRGGBB` | Node fill, replacing the status/device color. |
| `meta.label_color` | `#RRGGBB` | Label text color. |
| `meta.shape` | `circle`, `square`, `diamond`, `triangle` | Shape, replacing the device-type shape derived from the label. |
| `meta.size` | 6–40 | World-unit radius. The embed and SVG renderers scale their 10-unit shapes by `size / 10`; the editor uses the value as the circle radius (its default is 12). |

`POST /api/maps/{map}/save` and import normalize those keys: hex colors are
lowercased, the four shape names are accepted, `size` must be numeric 6–40, and
invalid values are dropped rather than defaulted. Every other `meta` key
(`sub_map_id`, `interface_id`, `tooltip`) is preserved. The editor's node
sidebar edits the same fields and offers `Apply style to all selected` for a
multi-node selection.

## Map management

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/plugin/WeathermapNG/map` | Admin | Create a map. |
| `PUT` | `/plugin/WeathermapNG/map/{map}` | Admin | Update map metadata. |
| `DELETE` | `/plugin/WeathermapNG/map/{map}` | Admin | Delete a map and its nodes/links. |
| `POST` | `/plugin/WeathermapNG/map/{map}/autodiscover` | Admin | Add devices and links from LibreNMS. |
| `POST` | `/plugin/WeathermapNG/api/maps/{map}/save` | Admin | Save full editor state. |

Create request (`name` is required, `alpha_dash`, and unique in `wmng_maps`):

```json
{
  "name": "production_map",
  "title": "Production Network Map",
  "width": 1200,
  "height": 800
}
```

Response for a request that sends `Accept: application/json` (or XHR); a plain
browser form post redirects to the editor instead:

```json
{
  "success": true,
  "map": {
    "id": 1,
    "name": "production_map",
    "title": "Production Network Map"
  },
  "redirect": "https://librenms/plugin/WeathermapNG/editor/1"
}
```

`PUT /map/{map}` accepts `title`, `width`, `height`, and `background` and
returns `{"success": true, "map": { ... }}`. Dimensions are 100–4096;
`background` is `#RRGGBB`.

Auto-discovery accepts `min_degree` (integer, default 0) and `os` (comma-
separated OS substrings) and returns a summary message:

```json
{
  "success": true,
  "message": "Auto-discovery completed: 14 nodes, 9 links added"
}
```

Full-map save requires both arrays (empty arrays are intentional clears):

```json
{
  "name": "production_map",
  "title": "Production Network Map",
  "options": {
    "width": 1200,
    "height": 800,
    "background": "#ffffff"
  },
  "nodes": [
    {
      "id": 1,
      "label": "Core Router",
      "x": 400,
      "y": 300,
      "device_id": 42,
      "meta": { "shape": "diamond" }
    }
  ],
  "links": [
    {
      "id": 1,
      "src_node_id": 1,
      "dst_node_id": 2,
      "port_id_a": 101,
      "port_id_b": 102,
      "bandwidth_bps": 1000000000,
      "style": { "via_style": "angled" }
    }
  ]
}
```

Limits: 2000 nodes, 2000 links, 200 via points per link, 50 tags. A successful
save returns `{"success": true, "message": "Map saved successfully"}`; invalid
values are rejected with `422` before the map changes.

## Nodes

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/plugin/WeathermapNG/map/{map}/nodes` | Admin | Bulk store nodes. |
| `POST` | `/plugin/WeathermapNG/map/{map}/node` | Admin | Create one node. |
| `PATCH` | `/plugin/WeathermapNG/map/{map}/node/{node}` | Admin | Update one node. |
| `DELETE` | `/plugin/WeathermapNG/map/{map}/node/{node}` | Admin | Delete one node and its links. |

Create one node:

```json
{
  "label": "Core Router",
  "x": 400,
  "y": 300,
  "device_id": 42,
  "meta": { "color": "#28a745" }
}
```

The response includes the stored node:
`{"success": true, "node": { ... }}`. `PATCH` accepts any of the same fields,
each optional. Bulk store takes `{"nodes": [ ... ]}` with `label`, `x`, `y`
required per entry, replaces the map's whole node set, and returns
`{"success": true}`. An empty array on a map that already has nodes returns
`422`.

## Links

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/plugin/WeathermapNG/map/{map}/links` | Admin | Bulk store links. |
| `POST` | `/plugin/WeathermapNG/map/{map}/link` | Admin | Create one link. |
| `PATCH` | `/plugin/WeathermapNG/map/{map}/link/{link}` | Admin | Update one link. |
| `DELETE` | `/plugin/WeathermapNG/map/{map}/link/{link}` | Admin | Delete one link. |

Create one link:

```json
{
  "src_node_id": 1,
  "dst_node_id": 2,
  "port_id_a": 101,
  "port_id_b": 102,
  "bandwidth_bps": 1000000000,
  "style": {
    "via_style": "angled",
    "via_points": [{ "x": 500, "y": 240 }]
  }
}
```

`src_node_id` and `dst_node_id` must exist in `wmng_nodes`. Bulk store takes
`{"links": [ ... ]}`, requires the same source/destination keys, and replaces
the map's whole link set. An empty array for a map that already has links fails
rather than clearing them.

## Favorites

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/plugin/WeathermapNG/map/{map}/favorite` | Session | Toggle the current user's favorite. |

This is the only write route open to non-admins. It inserts or deletes only the
caller's row in `wmng_map_favorites`, so two users favorite the same map
independently. The `(user_id, map_id)` unique key makes a repeated or
concurrent request safe.

```json
{
  "success": true,
  "favorited": true,
  "count": 3
}
```

`favorited` is the state after the toggle; `count` is the number of users who
favorited the map. Failures:

| Status | Body | Cause |
|--------|------|-------|
| `401` | `{"success": false, "message": "Authentication required"}` | No session user. |
| `503` | `{"success": false, "message": "Favorites are unavailable ..."}` | Table not created yet; run `database/setup.php`. |
| `500` | `{"success": false, "message": "..."}` | Write failed; details are logged only. |

Rows are removed when the map is deleted. The index page reads star state and
counts for the whole page in two batched queries.

## Lookups

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/devices` | Admin | Device list or autocomplete. |
| `GET` | `/plugin/WeathermapNG/api/device/{id}/ports` | Admin | Ports for one device. |

Both accept `?q=` (device hostname or `sysName` for the first, port name or
`ifIndex` for the second; case-insensitive substring). Device autocomplete
returns nothing for queries shorter than two characters. Port responses are
`{"device_id": 42, "ports": [ ... ]}`.

## Templates

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/templates` | Session | List templates. |
| `GET` | `/plugin/WeathermapNG/templates/{id}` | Session | Show one template. |
| `POST` | `/plugin/WeathermapNG/templates` | Admin | Create a template. |
| `PUT` | `/plugin/WeathermapNG/templates/{id}` | Admin | Update title/description. |
| `DELETE` | `/plugin/WeathermapNG/templates/{id}` | Admin | Delete a template. |
| `POST` | `/plugin/WeathermapNG/templates/{id}/create-map` | Admin | Create a map from a template. |

Create accepts `name` (required), `title`, `description`, `width`, `height`,
`icon`, `category`, and `config`, and returns `201` with the stored template.
`create-map` requires the template config to hold `default_nodes` and
`default_links` arrays, and requires `{"name": "<new map>"}`; it returns `201`
with the new map. Built-in templates return `403` on update and delete.

## Versions

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions` | Admin | Newest 20 versions plus retention flags. |
| `POST` | `/plugin/WeathermapNG/api/maps/{map}/versions` | Admin | Save a named version (`name`, optional `description`). |
| `GET` | `/plugin/WeathermapNG/api/versions/{versionId}` | Admin | Version metadata and snapshot. |
| `POST` | `/plugin/WeathermapNG/api/versions/{versionId}/restore` | Admin | Restore the map. |
| `GET` | `/plugin/WeathermapNG/api/versions/{versionId}/compare/{compareId}` | Admin | Flat diff between two versions. |
| `DELETE` | `/plugin/WeathermapNG/api/versions/{versionId}` | Admin | Delete one version. |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions/export` | Admin | Every version with full snapshots. |

Restore returns `422` with `success: false` when a snapshot cannot be safely
applied; the map and history are unchanged. A successful restore returns the
fresh map plus the restored version and adds a pre-restore checkpoint. Compare
returns `nodes_added`, `nodes_removed`, `nodes_modified`, `links_added`,
`links_removed`, and `links_modified`, with both versions. List responses use
`display_limit` for the newest-20 window and keep `max_versions` as an alias.
Comparison of versions from different maps returns `422`.

## Export and import

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/export` | Session | Export as JSON (default) or legacy `.conf`. |
| `POST` | `/plugin/WeathermapNG/api/import` | Admin | Import a `.json` or `.conf` file. |

Export sets an attachment filename based on the map name:

- `?format=json` returns the [map JSON payload](#map-json-payload) with
  `Content-Disposition: attachment; filename="<name>.json"`.
- `?format=conf` returns legacy PHP Weathermap text as `text/plain;
  charset=UTF-8` with a `.conf` filename.

The same export is available from the CLI:

```bash
php artisan weathermapng:export 1 --format=conf
```

Import is a multipart upload:

| Field | Required | Meaning |
|-------|----------|---------|
| `file` | yes | `.json` or `.conf` file, 10 MB maximum. |
| `name` | yes | Unique map name (`alpha_dash`, max 255). |
| `title` | no | Display title; falls back to the file's title, then to `name`. |

The validator accepts `json`, `txt`, and `conf` up to 10 MB. Dispatch is by
file extension: `.conf` uses the legacy parser, everything else is parsed as
JSON and must contain `nodes` and `links` arrays. Success:

```json
{
  "success": true,
  "map_id": 2,
  "message": "Map imported successfully"
}
```

Imported options are narrowed to `width`, `height`, `background`, `tags`,
`default_node_style`, and `default_link_style`; other keys are dropped.
Dimensions are clamped to 100–4096 (defaults 800×600) and an unparseable
`background` becomes `#ffffff`. A non-object entry in `nodes` or `links`
rejects the import with `400`.

## Health and metrics routes

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `GET` | `/plugin/WeathermapNG/health` | Public | `{status, timestamp}`; `503` when the database is unreachable. |
| `GET` | `/plugin/WeathermapNG/ready` | Public | `{ready, timestamp}`; `503` with `error` when not ready. |
| `GET` | `/plugin/WeathermapNG/live` | Public | `{alive, timestamp}`; always `200`. |
| `GET` | `/plugin/WeathermapNG/health/detailed` | Admin | Full checks, version, timing. |
| `GET` | `/plugin/WeathermapNG/health/stats` | Admin | Map/node/link counts, DB size, cache info. |
| `GET` | `/plugin/WeathermapNG/metrics` | Admin | Prometheus text (`text/plain; version=0.0.4`). |
| `GET` | `/plugin/WeathermapNG/diagnostics` | Admin | HTML installation report. |

The three public probes expose no version, filesystem path, dependency, or
configuration detail. `/metrics` exports `weathermapng_maps_total`,
`weathermapng_nodes_total`, `weathermapng_links_total`,
`weathermapng_memory_usage_bytes`, and `weathermapng_memory_peak_bytes`.

## Webhooks

WeathermapNG POSTs a JSON body to each configured URL on map lifecycle events:
`map.created`, `map.updated` (including editor saves), `map.deleted`, and
`map.version_saved`.

```json
{
  "event": "map.updated",
  "map_id": 12,
  "map_name": "campus-core",
  "user": "admin",
  "timestamp": "2026-10-20T18:55:00Z"
}
```

`user` is the acting LibreNMS username, or `null` for CLI-triggered changes.

Configure URLs on the settings page (one per line) or in config:

```php
// WEATHERMAPNG_WEBHOOK_URLS (newline-separated) or webhooks.urls (array)
'webhooks' => [
    'urls' => ['https://hooks.slack.com/services/...'],
    'secret' => env('WEATHERMAPNG_WEBHOOK_SECRET'),
],
```

When `WEATHERMAPNG_WEBHOOK_SECRET` is set, each delivery carries
`X-WeathermapNG-Signature: t=<unix_ts>,v1=<hex>`, where `v1` is
`HMAC-SHA256("<unix_ts>.<raw_body>", secret)`:

```php
$parts = [];
foreach (explode(',', $_SERVER['HTTP_X_WEATHERMAPNG_SIGNATURE'] ?? '') as $pair) {
    [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
    $parts[$k] = $v;
}

$expected = hash_hmac('sha256', $parts['t'] . '.' . $rawBody, $secret);
if (abs(time() - (int) $parts['t']) > 300 || !hash_equals($expected, $parts['v1'])) {
    http_response_code(401);
    exit;
}
```

Delivery is best-effort: 5-second timeout per URL, failures are logged, and
delivery never blocks the map mutation that triggered it.

## Error shape

Validation failures return `422` in Laravel's standard form:

```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

Controller failures return a success flag:

```json
{
  "success": false,
  "message": "Map not found"
}
```

Import content failures use `{"error": "<reason>"}` with `400`; a persistence
failure uses `500`; upload validation uses the `422` shape above. A failed
admin check is a `403`; an invalid embed token is a `401` with
`{"success": false, "message": "Invalid or missing embed token."}`.
