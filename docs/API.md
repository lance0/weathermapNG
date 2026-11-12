# WeathermapNG API And Route Reference

Routes are registered through `routes/web.php` when LibreNMS discovers the Composer package provider. If routes are missing, run package discovery from the LibreNMS root and verify with:

```bash
cd /opt/librenms
php artisan route:list | grep -iE 'weathermap|wmng'
```

## Authentication & Authorization

All routes are registered inside LibreNMS `web` + `auth` middleware, so every request resolves against an authenticated LibreNMS session — except for three public probe routes. There is no per-map ownership: any authenticated user can read any map, and all write operations require an admin except the per-user favorite toggle. The admin gate is enforced in each controller via the shared `AdminCheck` trait, which accepts `hasGlobalAdmin()`, `isAdmin()`, a LibreNMS `level >= 10`, or `hasRole('admin')` and otherwise `abort(403)`.

### Public probe routes (no auth)

These sit outside the `auth` group and are intentionally minimal so they can back health checks and load balancers:

- `GET /plugin/WeathermapNG/health`
- `GET /plugin/WeathermapNG/ready`
- `GET /plugin/WeathermapNG/live`

### Read routes (open to all authenticated users)

`GET` map index/show/editor/view/embed/json/live/sse/export and `GET templates` index/show. None of these call `requireAdmin()`. Admin-only reads: device and port lookups (`GET /api/devices`, `GET /api/device/{id}/ports` — they expose device IPs and topology data), map version endpoints (list/show/compare/export), and `/health/detailed`, `/health/stats`, `/metrics`. Token-auth variant: `embed`, `json`, `live`, and `sse` are also reachable without a session using a Bearer token / `?token=` when `WEATHERMAPNG_EMBED_TOKEN` is set (see [docs/EMBED.md](EMBED.md#embed-api-token-auth)).

### Per-user favorites (any authenticated user)

`POST /plugin/WeathermapNG/map/{map}/favorite` is the only write route that is not admin-gated. It toggles a favorite for the session user, so it must stay inside the `auth` group but must not call `requireAdmin()`. It only reads and writes that user's own row in `wmng_map_favorites`.

### Admin-only routes (require `hasGlobalAdmin()`, `isAdmin()`, `level >= 10`, or `hasRole('admin')`)

Every `POST`, `PUT`, `PATCH`, and `DELETE` endpoint calls `requireAdmin()` at the top of the controller action and `abort(403)` for non-admins. This covers all map CRUD, node CRUD, link CRUD, template CRUD, full-map save, import, auto-discovery, and the install runner. The single exception is the per-user favorite toggle (`POST /map/{map}/favorite`), which any authenticated user may call. The `GET /plugin/WeathermapNG/install` UI is authenticated (it sits in the `auth` group); its `POST` counterpart is admin-only.

## Page Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/WeathermapNG` | Map index |
| `GET` | `/plugin/WeathermapNG/install` | Installer UI |
| `POST` | `/plugin/WeathermapNG/install` | Run installer |
| `GET` | `/plugin/WeathermapNG/editor/{map?}` | Editor for an existing or new map |
| `GET` | `/plugin/WeathermapNG/view/{map}` | Full map view |
| `GET` | `/plugin/WeathermapNG/embed/{map}` | Embed viewer |

## Map Data Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/json` | Serialized map model |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/live` | Current traffic/status payload |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/sse` | Server-Sent Events live stream |
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/export?format=json\|conf` | Export a map (JSON or legacy `.conf`) |
| `POST` | `/plugin/WeathermapNG/api/import` | Import a map (`.json` or `.conf`) |
| `POST` | `/plugin/WeathermapNG/api/maps/{map}/save` | Save full editor state |

Example live payload shape:

```json
{
  "ts": 1738284000,
  "links": {
    "1": {
      "in": 52428800,
      "out": 104857600,
      "in_perc": 33,
      "out_perc": 67
    }
  },
  "nodes": {
    "1": {
      "status": "up",
      "alerts": {
        "count": 0,
        "severity": "ok"
      }
    }
  }
}
```

## Map JSON Payload (GET /api/maps/{map}/json)

The `json` endpoint and `export?format=json` both return the serialized map model produced by `Map::toJsonModel()`. The shape carries the full node and link graph plus nested-map metadata:

```json
{
  "_format": "weathermapng-map-v1",
  "id": 1,
  "name": "production_map",
  "title": "Production Network Map",
  "parent_map_id": null,
  "breadcrumb": [
    {"id": 1, "name": "root_map", "title": "Root Map"}
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

### Nested-map fields

| Field | Type | Description |
|-------|------|-------------|
| `parent_map_id` | int\|null | ID of the parent map in the drill-down hierarchy. `null` for a root map. |
| `breadcrumb` | array | Chain from the root ancestor to the current map, inclusive. Each entry is `{id, name, title}`. Empty only when the map is itself the root. |
| `sub_map_id` (per-node) | int\|null | If set, clicking this node in the embed view navigates to the sub-map with this ID, preserving query params. `null` means no drill-down. Stored in the node's `meta` JSON. |

### Per-node style fields

Nodes render their device-type shape and status color by default. Four optional keys in each node's `meta` JSON override that, in the editor, the embed view, and the SVG export (`map` saves include them; the API returns them unchanged):

| Key | Values | Effect |
|-----|--------|--------|
| `meta.color` | `#RRGGBB` | Node fill, replacing the status/device color. |
| `meta.label_color` | `#RRGGBB` | Label text color, replacing the theme/default label color. |
| `meta.shape` | `circle`, `square`, `diamond`, `triangle` | Shape override, replacing the device-type shape derived from the label. |
| `meta.size` | 6–40 | World-unit radius. Without `shape`, the natural shape scales by `size / 10` (embed/SVG) or `size / 12` (editor circle). |

`POST /api/maps/{map}/save` validates those keys (hex colors, the four shape names, numeric 6–40) and drops invalid values instead of storing them. Every other `meta` key — `interface_id`, `sub_map_id`, `tooltip` — is preserved verbatim. The editor's node sidebar edits the same fields and can copy one node's style onto every multi-selected node (`Apply style to all selected`).

## Export (GET /api/maps/{map}/export)

Pass `format` as a query parameter:

- `?format=json` (default) — returns the JSON payload above with `Content-Disposition: attachment; filename="<name>.json"`.
- `?format=conf` — returns legacy PHP Weathermap-style text with `Content-Type: text/plain; charset=UTF-8` and `Content-Disposition: attachment; filename="<name>.conf"`.

You can also export from the CLI:

```bash
php artisan weathermapng:export 1 --format=conf
```

## Import (POST /api/import)

Accepts a multipart file upload. The validator accepts mimes `json`, `txt`, and `conf` (max 10 MB). Dispatch is by file extension:

- `.json` — parsed as JSON (must contain `nodes` and `links` arrays).
- `.conf` — parsed by `LegacyConfService` into the same internal structure.

Required fields:

```json
{
  "name": "imported_map",
  "title": "Optional display title",
  "file": "<uploaded .json or .conf file>"
}
```

`name` must be unique in `wmng_maps`. `title` falls back to the imported map's title, then to `name`. On success the response is:

```json
{
  "success": true,
  "map_id": 2,
  "message": "Map imported successfully"
}
```

Imported map options are narrowed to the supported set — `width`, `height`, `background`, `tags`, `default_node_style`, `default_link_style`. Other keys in the file are dropped. Width and height are clamped to 100–4096 (defaults 800×600) and an unparseable `background` becomes `#ffffff`. Every entry in `nodes` and `links` must be an object; otherwise the import is rejected with HTTP 400.

## Client URL parameters

`?debugDots=1|2` is a client-side embed diagnostic parameter, not an API endpoint. It overlays particle-position markers and render-pipeline state on the canvas. Omit it for normal use.

## Lookup Routes (admin-only)

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/devices` | Device lookup for the editor (admin) |
| `GET` | `/plugin/WeathermapNG/api/device/{id}/ports` | Ports for one LibreNMS device (admin) |

## Map Management Routes

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/plugin/WeathermapNG/map` | Create a map |
| `PUT` | `/plugin/WeathermapNG/map/{map}` | Update map metadata |
| `DELETE` | `/plugin/WeathermapNG/map/{map}` | Delete a map |
| `POST` | `/plugin/WeathermapNG/map/{map}/autodiscover` | Run current auto-discovery flow |

Create map request:

```json
{
  "name": "production_map",
  "title": "Production Network Map",
  "width": 1200,
  "height": 800
}
```

Typical response:

```json
{
  "success": true,
  "map": {
    "id": 1,
    "name": "production_map",
    "title": "Production Network Map",
    "width": 1200,
    "height": 800
  },
  "redirect": "https://librenms/plugin/WeathermapNG/editor/1"
}
```

## Favorite Route

| Auth | Method | Path | Purpose |
|------|--------|------|---------|
| Any authenticated user | `POST` | `/plugin/WeathermapNG/map/{map}/favorite` | Toggle the current user's favorite of a map |

The toggle is scoped to the session user, so two users can favorite the same map independently. The `(user_id, map_id)` unique key makes the call safe to repeat: a concurrent double request cannot create duplicate favorites. Favorite rows are removed automatically when the map is deleted.

Successful response:

```json
{
  "success": true,
  "favorited": true,
  "count": 3
}
```

`favorited` is the state after the toggle, and `count` is the number of users who have favorited that map. The index page renders the star state and per-map count from `TopologyPreview::loadMaps()` with two batched queries for the whole page (never one query per card).

Failure responses use the standard error shape: HTTP 401 with `{"success": false, "message": "Authentication required"}` when the request has no session user, and HTTP 500 with a generic message when the write fails (details are logged, not returned).

## Node Routes

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/plugin/WeathermapNG/map/{map}/nodes` | Store node through collection route |
| `POST` | `/plugin/WeathermapNG/map/{map}/node` | Create one node |
| `PATCH` | `/plugin/WeathermapNG/map/{map}/node/{node}` | Update one node |
| `DELETE` | `/plugin/WeathermapNG/map/{map}/node/{node}` | Delete one node |

Create node request:

```json
{
  "label": "Core Router",
  "x": 400,
  "y": 300,
  "device_id": 42
}
```

## Link Routes

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/plugin/WeathermapNG/map/{map}/links` | Store link through collection route |
| `POST` | `/plugin/WeathermapNG/map/{map}/link` | Create one link |
| `PATCH` | `/plugin/WeathermapNG/map/{map}/link/{link}` | Update one link |
| `DELETE` | `/plugin/WeathermapNG/map/{map}/link/{link}` | Delete one link |

Create link request:

```json
{
  "src_node_id": 1,
  "dst_node_id": 2,
  "port_id_a": 101,
  "port_id_b": 102,
  "bandwidth_bps": 1000000000,
  "style": {
    "via_style": "angled",
    "via_points": [
      {"x": 500, "y": 240}
    ]
  }
}
```

## Template Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/WeathermapNG/templates` | List templates |
| `GET` | `/plugin/WeathermapNG/templates/{id}` | Show one template |
| `POST` | `/plugin/WeathermapNG/templates` | Create a template |
| `PUT` | `/plugin/WeathermapNG/templates/{id}` | Update a template |
| `DELETE` | `/plugin/WeathermapNG/templates/{id}` | Delete a template |
| `POST` | `/plugin/WeathermapNG/templates/{id}/create-map` | Create a map from a template |

## Health And Metrics Routes

| Auth | Method | Path | Purpose |
|------|--------|------|---------|
| Public | `GET` | `/plugin/WeathermapNG/health` | Basic health |
| Public | `GET` | `/plugin/WeathermapNG/ready` | Readiness probe |
| Public | `GET` | `/plugin/WeathermapNG/live` | Basic liveness |
| Admin | `GET` | `/plugin/WeathermapNG/health/detailed` | Detailed health |
| Admin | `GET` | `/plugin/WeathermapNG/health/stats` | Plugin stats |
| Admin | `GET` | `/plugin/WeathermapNG/metrics` | Prometheus-style metrics |

## Version History Routes

Version routes are registered in `routes/web.php` under the `web` + `auth` middleware group. All version endpoints (list, show, compare, and export included) require admin via `requireAdmin()`.

| Auth | Method | Path | Purpose |
|------|--------|------|---------|
| Admin | `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions` | List versions for a map |
| Admin | `POST` | `/plugin/WeathermapNG/api/maps/{map}/versions` | Create a named version |
| Admin | `GET` | `/plugin/WeathermapNG/api/versions/{versionId}` | Show version details + snapshot |
| Admin | `POST` | `/plugin/WeathermapNG/api/versions/{versionId}/restore` | Restore map to a version |
| Admin | `GET` | `/plugin/WeathermapNG/api/versions/{versionId}/compare/{compareId}` | Compare two versions (returns flat diff: `nodes_added`, `nodes_removed`, `nodes_modified`, `links_added`, `links_removed`, `links_modified`) |
| Admin | `DELETE` | `/plugin/WeathermapNG/api/versions/{versionId}` | Delete a single version |
| Admin | `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions/export` | Export all versions as JSON |

In v1.16.0, restore returns `422` when a snapshot cannot be safely
applied, with `success: false` and a redacted message. The map and history remain
unchanged. A successful restore returns the fresh map and adds a pre-restore
checkpoint plus a restored-state version; prior saved versions are retained.
The history response uses `display_limit` for the newest 20 displayed entries,
retains `max_versions` as a compatibility alias for that display limit,
and reports manual retention, without automatic cleanup or conflict detection.

The v1.16.0 history export includes all versions for the selected map,
independently of list limits, with complete snapshots and available creator and
exporter names. It remains administrator-only and does not change saved rows.

Comparison keeps the six existing ID-change lists and both version snapshots in
the response. The v1.16.0 editor renders side-by-side geometry and field
differences from that data, including explicit unavailable historical fields.
List-shaped and ID-keyed snapshots both compare by each record's saved ID.


## Webhooks

When configured, WeathermapNG POSTs a JSON payload to each webhook URL on map lifecycle events: `map.created`, `map.updated` (including editor saves), `map.deleted`, and `map.version_saved`.

```json
{
  "event": "map.updated",
  "map_id": 12,
  "map_name": "campus-core",
  "user": "admin",
  "timestamp": "2026-10-20T18:55:00Z"
}
```

`user` is the acting LibreNMS username, or `null` for CLI-triggered changes (poller, artisan commands).

**Configuration** — settings page (Webhook URLs, one per line), or config:

```php
// WEATHERMAPNG_WEBHOOK_URLS (newline-separated) or webhooks.urls (array)
'webhooks' => [
    'urls' => ['https://hooks.slack.com/services/...'],
    'secret' => env('WEATHERMAPNG_WEBHOOK_SECRET'),
],
```

**Signature verification** — when `WEATHERMAPNG_WEBHOOK_SECRET` is set, each delivery carries `X-WeathermapNG-Signature: t=<unix_ts>,v1=<hex>`. The `v1` value is `HMAC-SHA256("<unix_ts>.<raw_body>", secret)`. Reject deliveries with a timestamp older than five minutes to prevent replay:

```php
[$t, $v1] = array_map(fn($p) => explode('=', $p, 2), explode(',', $_SERVER['HTTP_X_WEATHERMAPNG_SIGNATURE']));
assert(abs(time() - $t[1]) < 300);
assert(hash_equals('v1=' . hash_hmac('sha256', $t[1] . '.' . $body, $secret), 'v1=' . $v1[1]));
```

Delivery is best-effort: a 5-second timeout per URL, failures are logged and never block the map mutation that triggered them.

## Error Shape

Most JSON write endpoints return a success flag and a message on errors:

```json
{
  "success": false,
  "message": "Map not found"
}
```

Validation errors may include field-level details depending on the controller/request class.
