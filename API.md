# WeathermapNG API And Route Reference

Routes are registered through `routes/web.php` when LibreNMS discovers the Composer package provider. If routes are missing, run package discovery from the LibreNMS root and verify with:

```bash
cd /opt/librenms
php artisan route:list | grep -iE 'weathermap|wmng'
```

## Authentication

Most plugin routes are inside LibreNMS `web` and `auth` middleware. Use an authenticated LibreNMS browser session for the editor and UI-driven API calls.

Public probe routes are intentionally limited:

- `GET /plugin/WeathermapNG/health`
- `GET /plugin/WeathermapNG/ready`
- `GET /plugin/WeathermapNG/live`

Detailed health, stats, metrics, map management, editor, templates, import/export, and live map data require LibreNMS authentication.

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

The codebase includes `MapVersionController` and editor UI for map version history, but the authoritative route list is `routes/web.php`. If version routes are added or restored, update this section and `VERSIONING.md` in the same change.

## Error Shape

Most JSON write endpoints return a success flag and a message on errors:

```json
{
  "success": false,
  "message": "Map not found"
}
```

Validation errors may include field-level details depending on the controller/request class.
