# WeathermapNG API Documentation

All API endpoints require LibreNMS authentication (session cookies or API token).

## Authentication

Most endpoints require authentication. Use one of these methods:

### Session Cookie Authentication (Web UI)
```bash
# Get session cookie by logging into LibreNMS web interface
# Then include the session cookie in requests
curl -b 'laravel_session=YOUR_SESSION_COOKIE' \
     https://librenms/plugin/WeathermapNG/api/maps/1/json
```

### API Token Authentication
```bash
# Use X-Auth-Token header
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/maps/1/json
```

---

## Maps

### List All Maps

```http
GET /plugin/WeathermapNG
```

**Response:**
```json
[
    {
        "id": 1,
        "name": "my_map",
        "title": "My Network Map",
        "width": 800,
        "height": 600,
        "created_at": "2025-01-30T10:00:00Z",
        "updated_at": "2025-01-30T10:00:00Z"
    }
]
```

**Example:**
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG
```

---

### Get Map Data (JSON)

```http
GET /plugin/WeathermapNG/api/maps/{id}/json
```

**Response:**
```json
{
    "id": 1,
    "name": "my_map",
    "title": "My Network Map",
    "width": 800,
    "height": 600,
    "nodes": [
        {
            "id": 1,
            "map_id": 1,
            "label": "Router-A",
            "x": 100,
            "y": 200,
            "device_id": 42,
            "meta": {
                "hostname": "router-a.example.com"
            }
        }
    ],
    "links": [
        {
            "id": 1,
            "map_id": 1,
            "src_node_id": 1,
            "dst_node_id": 2,
            "port_id_a": 101,
            "port_id_b": 102,
            "bandwidth_bps": 1000000000,
            "style": {
                "color": "#007bff",
                "width": 3
            }
        }
    ]
}
```

**Example:**
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/maps/1/json
```

---

### Get Live Map Data

```http
GET /plugin/WeathermapNG/api/maps/{id}/live
```

Returns real-time bandwidth and status data.

**Response:**
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

**Example:**
```bash
# Poll every 5 seconds for live updates
while true; do
    curl -H "X-Auth-Token: YOUR_TOKEN" \
         https://librenms/plugin/WeathermapNG/api/maps/1/live
    sleep 5
done
```

---

### Save Full Map (D3 Editor)

```http
POST /plugin/WeathermapNG/api/maps/{id}/save
Content-Type: application/json
```

**Request Body:**
```json
{
    "nodes": [
        {"id": 1, "label": "Router-A", "x": 100, "y": 200, "device_id": 42}
    ],
    "links": [
        {"id": 1, "src_node_id": 1, "dst_node_id": 2, "style": {"color": "#007bff"}}
    ]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Map saved successfully"
}
```

**Example:**
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d @map.json \
     https://librenms/plugin/WeathermapNG/api/maps/1/save
```

**Error Response:**
```json
{
    "success": false,
    "message": "Map not found"
}
```

---

### Create Map

```http
POST /plugin/WeathermapNG/map
Content-Type: application/json
```

**Request Body:**
```json
{
    "name": "my_map",
    "title": "My Network Map",
    "width": 1024,
    "height": 768
}
```

**Response:**
```json
{
    "success": true,
    "map": {
        "id": 1,
        "name": "my_map",
        "title": "My Network Map",
        "width": 1024,
        "height": 768
    },
    "redirect": "https://librenms/plugin/WeathermapNG/editor/1"
}
```

**Examples:**

Minimal map (uses defaults):
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{"name": "minimal_map"}' \
     https://librenms/plugin/WeathermapNG/map
```

Full specification:
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "name": "production_map",
         "title": "Production Network Map",
         "width": 1200,
         "height": 800
     }' \
     https://librenms/plugin/WeathermapNG/map
```

**Validation Errors:**
```json
{
    "success": false,
    "message": "The name field is required."
}
```

---

### Update Map

```http
PUT /plugin/WeathermapNG/map/{id}
```

**Request Body:**
```json
{
    "title": "Updated Map Title",
    "width": 1200,
    "height": 900
}
```

**Example:**
```bash
curl -X PUT \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "title": "Updated Map Title",
         "width": 1200
     }' \
     https://librenms/plugin/WeathermapNG/map/1
```

---

### Delete Map

```http
DELETE /plugin/WeathermapNG/map/{id}
```

**Example:**
```bash
curl -X DELETE \
     -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/map/1
```

---

## Map Items (Editor CRUD)

### Create Node

```http
POST /plugin/WeathermapNG/map/{id}/node
Content-Type: application/json
```

**Request Body:**
```json
{
    "label": "Router-A",
    "x": 150,
    "y": 250,
    "device_id": 42
}
```

**Response:**
```json
{
    "success": true,
    "node": {
        "id": 5,
        "map_id": 1,
        "label": "Router-A",
        "x": 150,
        "y": 250,
        "device_id": 42
    }
}
```

**Example:**
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "label": "Core Router",
         "x": 400,
         "y": 300,
         "device_id": 42
     }' \
     https://librenms/plugin/WeathermapNG/map/1/node
```

**Without Device:**
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "label": "Label Node",
         "x": 100,
         "y": 200
     }' \
     https://librenms/plugin/WeathermapNG/map/1/node
```

---

### Update Node

```http
PATCH /plugin/WeathermapNG/map/{id}/node/{nodeId}
```

**Request Body:**
```json
{
    "label": "Updated Label",
    "x": 200,
    "y": 250
}
```

**Example:**
```bash
curl -X PATCH \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "label": "Updated Label",
         "x": 200,
         "y": 250
     }' \
     https://librenms/plugin/WeathermapNG/map/1/node/5
```

---

### Delete Node

```http
DELETE /plugin/WeathermapNG/map/{id}/node/{nodeId}
```

**Example:**
```bash
curl -X DELETE \
     -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/map/1/node/5
```

---

### Create Link

```http
POST /plugin/WeathermapNG/map/{id}/link
Content-Type: application/json
```

**Request Body:**
```json
{
    "src_node_id": 1,
    "dst_node_id": 2,
    "port_id_a": 101,
    "port_id_b": 102,
    "bandwidth_bps": 1000000000
}
```

**Response:**
```json
{
    "success": true,
    "link": {
        "id": 3,
        "src_node_id": 1,
        "dst_node_id": 2,
        "port_id_a": 101,
        "port_id_b": 102,
        "bandwidth_bps": 1000000000
    }
}
```

**Example:**
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "src_node_id": 1,
         "dst_node_id": 2,
         "port_id_a": 101,
         "port_id_b": 102,
         "bandwidth_bps": 1000000000
     }' \
     https://librenms/plugin/WeathermapNG/map/1/link
```

**Without Bandwidth (uses RRD):**
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "src_node_id": 1,
         "dst_node_id": 2
     }' \
     https://librenms/plugin/WeathermapNG/map/1/link
```

**Error Response:**
```json
{
    "success": false,
    "message": "Link would create a cycle in the graph"
}
```

---

### Update Link

```http
PATCH /plugin/WeathermapNG/map/{id}/link/{linkId}
```

**Request Body:**
```json
{
    "port_id_a": 103,
    "bandwidth_bps": 2000000000,
    "style": {
        "color": "#dc3545",
        "width": 5
    }
}
```

**Example:**
```bash
curl -X PATCH \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "bandwidth_bps": 2000000000,
         "style": {
             "color": "#dc3545"
         }
     }' \
     https://librenms/plugin/WeathermapNG/map/1/link/3
```

---

### Delete Link

```http
DELETE /plugin/WeathermapNG/map/{id}/link/{linkId}
```

**Example:**
```bash
curl -X DELETE \
     -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/map/1/link/3
```

---

## Discovery

### Auto-Discover Topology

```http
POST /plugin/WeathermapNG/map/{id}/autodiscover
Content-Type: application/json
```

**Request Body:**
```json
{
    "min_degree": 1,
    "os": "ios,junos"
}
```

**Parameters:**
- `min_degree` (optional): Minimum neighbor degree for devices to include
- `os` (optional): Comma-separated list of device OS to filter

**Response:**
```json
{
    "success": true,
    "message": "Discovered 12 devices and 15 links",
    "devices": [
        {
            "device_id": 42,
            "hostname": "router1.example.com",
            "os": "ios"
        }
    ],
    "links": [
        {
            "src_device_id": 42,
            "dst_device_id": 43,
            "src_port_id": 101,
            "dst_port_id": 102
        }
    ]
}
```

**Examples:**

Basic discovery (all devices with at least 1 link):
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{"min_degree": 1}' \
     https://librenms/plugin/WeathermapNG/map/1/autodiscover
```

Filter by OS:
```bash
curl -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "min_degree": 2,
         "os": "ios,junos,cisco-ios"
     }' \
     https://librenms/plugin/WeathermapNG/map/1/autodiscover
```

---

## Devices

### Get All Devices

```http
GET /plugin/WeathermapNG/api/devices
```

**Response:**
```json
[
    {
        "id": 42,
        "hostname": "router1.example.com",
        "sysName": "Router1",
        "os": "ios",
        "location": "Rack A"
    }
]
```

**Example:**
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/devices
```

---

### Search Devices

```http
GET /plugin/WeathermapNG/api/devices/search?q=router
```

**Response:**
```json
[
    {
        "id": 42,
        "hostname": "router1.example.com",
        "sysName": "Router1"
    }
]
```

**Example:**
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/devices/search?q=router
```

---

### Get Device Ports

```http
GET /plugin/WeathermapNG/api/device/{id}/ports
```

**Parameters:**
- `q`: Server-side filter (optional)

**Response:**
```json
[
    {
        "port_id": 101,
        "ifName": "GigabitEthernet0/0",
        "ifDescr": "WAN Interface",
        "ifSpeed": 1000000000
    }
]
```

**Examples:**

Get all ports:
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/device/42/ports
```

Filter ports:
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     'https://librenms/plugin/WeathermapNG/api/device/42/ports?q=Gig'
```

---

## Health & Monitoring

### Basic Health Check

```http
GET /plugin/WeathermapNG/health
```

**Response:**
```json
{
    "status": "healthy",
    "timestamp": "2025-01-30T10:00:00Z",
    "version": "1.2.5",
    "checks": {
        "database": {"status": "healthy"},
        "rrd": {"status": "healthy"},
        "output": {"status": "healthy"}
    }
}
```

**Example:**
```bash
curl https://librenms/plugin/WeathermapNG/health
```

**Unhealthy Response:**
```json
{
    "status": "unhealthy",
    "checks": {
        "database": {
            "status": "unhealthy",
            "message": "Connection refused"
        }
    }
}
```

---

### Statistics

```http
GET /plugin/WeathermapNG/metrics
```

**Response:**
```json
{
    "maps": 12,
    "nodes": 156,
    "links": 89,
    "devices": 45
}
```

**Example:**
```bash
curl https://librenms/plugin/WeathermapNG/metrics
```

---

### Readiness Probe (for Kubernetes/Docker)

```http
GET /plugin/WeathermapNG/ready
```

**Response (Ready):**
```json
{
    "ready": true
}
```

**Response (Not Ready):**
```json
{
    "ready": false,
    "reason": "Database connection failed"
}
```

**Example:**
```bash
# Kubernetes liveness probe
curl https://librenms/plugin/WeathermapNG/ready
```

---

### Liveness Probe (for Kubernetes/Docker)

```http
GET /plugin/WeathermapNG/live
```

**Response:**
```json
{
    "alive": true
}
```

**Example:**
```bash
# Kubernetes liveness probe
curl https://librenms/plugin/WeathermapNG/live
```

---

### Prometheus Metrics

```http
GET /plugin/WeathermapNG/metrics
```

**Response (Prometheus format):**
```
# HELP weathermapng_maps_total Total number of maps
# TYPE weathermapng_maps_total gauge
weathermapng_maps_total 12

# HELP weathermapng_nodes_total Total number of nodes
# TYPE weathermapng_nodes_total gauge
weathermapng_nodes_total 156
```

**Example:**
```bash
# Scrape metrics every 15 seconds
while true; do
    curl https://librenms/plugin/WeathermapNG/metrics
    sleep 15
done
```

---

## Import/Export

### Export Map

```http
GET /plugin/WeathermapNG/api/maps/{id}/export
```

**Parameters:**
- `format`: `json` (default) or `xml`

**Response:**
```json
{
    "map": {
        "id": 1,
        "name": "my_map"
    },
    "nodes": [...],
    "links": [...]
}
```

**Example:**
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/maps/1/export?format=json
```

---

### Import Map

```http
POST /plugin/WeathermapNG/api/import
Content-Type: multipart/form-data
```

**Request:**
```bash
curl -X POST \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -F "file=@map-export.json" \
     https://librenms/plugin/WeathermapNG/api/import
```

**Response:**
```json
{
    "success": true,
    "map_id": 5,
    "message": "Map imported successfully"
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Invalid JSON format"
}
```

---

## Embedding

### Embed Map (authenticated)

```http
GET /plugin/WeathermapNG/embed/{id}
```

**Query parameters:**
- `metric`: `percent` (default), `in`, `out`, `sum`
- `sse`: `1` to enable Server-Sent Events if available
- `w`, `h`: Override viewport width/height in pixels

**Example:**
```html
<!-- Basic embed -->
<iframe src="https://librenms/plugin/WeathermapNG/embed/1"
        width="100%" height="600" frameborder="0"></iframe>

<!-- Custom size -->
<iframe src="https://librenms/plugin/WeathermapNG/embed/1?w=1200&h=800"
        width="1200" height="800" frameborder="0"></iframe>

<!-- Show traffic in/out -->
<iframe src="https://librenms/plugin/WeathermapNG/embed/1?metric=in"
        width="100%" height="600" frameborder="0"></iframe>
```

---

### Public Embed (if configured)

```http
GET /plugin/WeathermapNG/public/embed/{id}
```

Same query parameters as authenticated embed.

---

## Response Codes

| Code | Meaning | Action |
|-------|----------|---------|
| 200 | Success | Request was successful |
| 201 | Created | Resource was created |
| 400 | Bad Request | Invalid input data |
| 401 | Unauthorized | Authentication required/invalid |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Server Error | Internal server error |
| 503 | Service Unavailable | Service temporarily down |

---

## Error Response Format

All endpoints return JSON with the following structure:

**Success:**
```json
{
    "success": true,
    "data": { ... }
}
```

**Error:**
```json
{
    "success": false,
    "message": "Human readable error message",
    "errors": {
        "field_name": ["Validation error details"]
    }
}
```

---

## Common Error Patterns

### Authentication Errors
```json
{
    "success": false,
    "message": "Unauthenticated"
}
```
**Solution:** Provide valid X-Auth-Token header or session cookie

---

### Validation Errors
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "name": ["The name field is required."],
        "width": ["The width must be between 100 and 4096."]
    }
}
```
**Solution:** Fix validation errors and retry request

---

### Not Found Errors
```json
{
    "success": false,
    "message": "Map not found"
}
```
**Solution:** Verify the resource ID exists

---

## Real-World Use Cases

### Creating a New Network Map

```bash
# 1. Create a new map
RESPONSE=$(curl -s -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d '{
         "name": "production_network",
         "title": "Production Network Map",
         "width": 1200,
         "height": 800
     }' \
     https://librenms/plugin/WeathermapNG/map)

# Extract map ID
MAP_ID=$(echo $RESPONSE | jq -r '.map.id')

# 2. Get available devices
curl -s -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugin/WeathermapNG/api/devices > devices.json

# 3. Add nodes from devices
for device in $(jq -r '.[].id' devices.json); do
    curl -s -X POST \
         -H "Content-Type: application/json" \
         -H "X-Auth-Token: YOUR_TOKEN" \
         -d "{\"map_id\": $MAP_ID, \"device_id\": $device, \"label\": \"Device-$device\", \"x\": 100, \"y\": 100}" \
         https://librenms/plugin/WeathermapNG/map/$MAP_ID/node
done

# 4. Create links between nodes
curl -s -X POST \
     -H "Content-Type: application/json" \
     -H "X-Auth-Token: YOUR_TOKEN" \
     -d "{\"map_id\": $MAP_ID, \"src_node_id\": 1, \"dst_node_id\": 2}" \
     https://librenms/plugin/WeathermapNG/map/$MAP_ID/link
```

---

### Monitoring Live Traffic

```bash
# Continuously monitor a specific map
MAP_ID=1

while true; do
    # Get live data
    curl -s -H "X-Auth-Token: YOUR_TOKEN" \
         https://librenms/plugin/WeathermapNG/api/maps/$MAP_ID/live | jq

    echo "--- Monitoring $(date '+%Y-%m-%d %H:%M:%S') ---"

    sleep 10
done
```

---

### Backup All Maps

```bash
# Export all maps to a directory
mkdir -p weathermap-backup

curl -s -H "X-Auth-Token: YOUR_TOKEN" \
    https://librenms/plugin/WeathermapNG > maps.json

for map_id in $(jq -r '.[].id' maps.json); do
    curl -s -H "X-Auth-Token: YOUR_TOKEN" \
         https://librenms/plugin/WeathermapNG/api/maps/$map_id/export \
         > "weathermap-backup/map-$map_id.json"
done

echo "Backup completed!"
```

---

## Rate Limiting

API requests are rate-limited. Recommended limits:

- **GET requests**: 60 requests per minute
- **POST/PUT/PATCH**: 30 requests per minute
- **DELETE requests**: 20 requests per minute

If you exceed the rate limit, you'll receive:

```json
{
    "success": false,
    "message": "Too many requests. Please slow down."
}
```

---

## Pagination

List endpoints that return many items support pagination:

```
GET /plugin/WeathermapNG/api/devices?page=1&per_page=50
```

**Response:**
```json
{
    "data": [ ... ],
    "meta": {
        "current_page": 1,
        "per_page": 50,
        "total": 245,
        "last_page": 5
    }
}
```

---

## Version Information

Current API version: **1.2.5**

See [CHANGELOG.md](CHANGELOG.md) for version history.
