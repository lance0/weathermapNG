# WeathermapNG API And Route Reference

Routes are registered through `routes/web.php` when LibreNMS discovers the Composer package provider. If routes are missing, run package discovery from the LibreNMS root and verify with:

```bash
cd /opt/librenms
php artisan route:list | grep -iE 'weathermap|wmng'
```

## Authentication & Authorization

All routes are registered inside LibreNMS `web` + `auth` middleware, so every request resolves against an authenticated LibreNMS session — except for three public probe routes. There is no per-map ownership: any authenticated user can read any map, and all write operations require an admin. The admin gate is enforced in each controller via the shared `AdminCheck` trait, which accepts `hasGlobalAdmin()`, `isAdmin()`, or a LibreNMS `level >= 10` and otherwise `abort(403)`.

### Public probe routes (no auth)

These sit outside the `auth` group and are intentionally minimal so they can back health checks and load balancers:

- `GET /plugin/WeathermapNG/health`
- `GET /plugin/WeathermapNG/ready`
- `GET /plugin/WeathermapNG/live`

### Read routes (open to all authenticated users)

`GET` map index/show/editor/view/embed/json/live/sse/export, `GET templates` index/show, `GET /api/devices` and `GET /api/device/{id}/ports` lookups, and `GET /health/detailed`, `/health/stats`, `/metrics`. None of these call `requireAdmin()`.

### Admin-only routes (require `hasGlobalAdmin()`, `isAdmin()`, or `level >= 10`)

Every `POST`, `PUT`, `PATCH`, and `DELETE` endpoint calls `requireAdmin()` at the top of the controller action and `abort(403)` for non-admins. This covers all map CRUD, node CRUD, link CRUD, template CRUD, full-map save, import, auto-discovery, and the install runner. The `GET /plugin/WeathermapNG/install` UI is authenticated (it sits in the `auth` group); its `POST` counterpart is admin-only.

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
| `GET` | `/plugin/WeathermapNG/api/maps/{map}/export` | Export a map |
| `POST` | `/plugin/WeathermapNG/api/import` | Import a map |
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

## Lookup Routes

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/plugin/WeathermapNG/api/devices` | Device lookup for the editor |
| `GET` | `/plugin/WeathermapNG/api/device/{id}/ports` | Ports for one LibreNMS device |

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
| Authenticated | `GET` | `/plugin/WeathermapNG/health/detailed` | Detailed health |
| Authenticated | `GET` | `/plugin/WeathermapNG/health/stats` | Plugin stats |
| Authenticated | `GET` | `/plugin/WeathermapNG/metrics` | Prometheus-style metrics |

## Version History Routes

Version routes are registered in `routes/web.php` under the `web` + `auth` middleware group. All mutating endpoints require admin via `requireAdmin()`.

| Auth | Method | Path | Purpose |
|------|--------|------|---------|
| Authenticated | `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions` | List versions for a map |
| Admin | `POST` | `/plugin/WeathermapNG/api/maps/{map}/versions` | Create a named version |
| Authenticated | `GET` | `/plugin/WeathermapNG/api/versions/{versionId}` | Show version details + snapshot |
| Admin | `POST` | `/plugin/WeathermapNG/api/versions/{versionId}/restore` | Restore map to a version |
| Admin | `GET` | `/plugin/WeathermapNG/api/versions/{versionId}/compare/{compareId}` | Compare two versions (returns flat diff: `nodes_added`, `nodes_removed`, `nodes_modified`, `links_added`, `links_removed`, `links_modified`) |
| Admin | `DELETE` | `/plugin/WeathermapNG/api/versions/{versionId}` | Delete a single version |
| Admin | `GET` | `/plugin/WeathermapNG/api/maps/{map}/versions/export` | Export all versions as JSON |

Diff direction: `compare(v1, v2)` reports what changed going from v1 → v2. `nodes_added` = IDs in v2 but not v1.

## Error Shape

Most JSON write endpoints return a success flag and a message on errors:

```json
{
  "success": false,
  "message": "Map not found"
}
```

Validation errors may include field-level details depending on the controller/request class.
