# WeathermapNG API Documentation

This document provides comprehensive documentation for the WeathermapNG REST API.

## Table of Contents

- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [Endpoints](#endpoints)
  - [Maps](#maps)
  - [Devices](#devices)
  - [Health](#health)
- [Data Formats](#data-formats)
- [Examples](#examples)

## Authentication

All API endpoints require authentication through LibreNMS. The plugin inherits LibreNMS's authentication system.

```bash
# API requests should include LibreNMS session cookies or API tokens
curl -H "X-Auth-Token: your-librenms-token" \
     https://your-librenms/plugins/weathermapng/api/maps
```

## Error Handling

The API returns standard HTTP status codes and JSON error responses:

```json
{
  "error": "Error message description",
  "code": "ERROR_CODE",
  "details": {
    "field": "validation error details"
  }
}
```

### Common HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

## Rate Limiting

API requests are subject to LibreNMS rate limiting. For high-volume applications, consider implementing client-side caching.

## Endpoints

### Maps

#### List Maps
Get all maps with basic information.

```http
GET /plugins/weathermapng/api/maps
```

**Response:**
```json
{
  "maps": [
    {
      "id": 1,
      "name": "core_network",
      "title": "Core Network Map",
      "width": 800,
      "height": 600,
      "nodes_count": 5,
      "links_count": 4,
      "created_at": "2025-01-29T10:00:00Z",
      "updated_at": "2025-01-29T10:30:00Z"
    }
  ]
}
```

#### Get Map Details
Get complete map structure including nodes and links.

```http
GET /plugins/weathermapng/api/maps/{mapId}
```

**Response:**
```json
{
  "id": 1,
  "name": "core_network",
  "title": "Core Network Map",
  "width": 800,
  "height": 600,
  "background": "#ffffff",
  "options": {
    "grid_size": 20,
    "theme": "default"
  },
  "nodes": [
    {
      "id": 1,
      "label": "Core Router",
      "x": 400,
      "y": 100,
      "device_id": 1,
      "device_name": "core-router-01",
      "status": "up",
      "meta": {
        "vendor": "Cisco",
        "model": "ISR 4451"
      }
    }
  ],
  "links": [
    {
      "id": 1,
      "src": 1,
      "dst": 2,
      "port_id_a": 1,
      "port_id_b": 1,
      "bandwidth_bps": 1000000000,
      "source_port_name": "GigabitEthernet0/0/0",
      "destination_port_name": "GigabitEthernet0/0/1",
      "bandwidth_formatted": "1 Gbps",
      "style": {
        "color": "#28a745",
        "width": 3
      }
    }
  ],
  "metadata": {
    "total_nodes": 5,
    "total_links": 4,
    "last_updated": "2025-01-29T10:30:00Z"
  }
}
```

#### Get Live Data
Get real-time utilization data for map links.

```http
GET /plugins/weathermapng/api/maps/{mapId}/live
```

**Response:**
```json
{
  "ts": 1643456789,
  "links": {
    "1": {
      "in_bps": 55000000,
      "out_bps": 120000000,
      "pct": 17.5,
      "err": null
    },
    "2": {
      "in_bps": 0,
      "out_bps": 0,
      "pct": null,
      "err": "RRD file not found"
    }
  }
}
```

#### Create Map
Create a new map.

```http
POST /plugins/weathermapng/maps
Content-Type: application/json

{
  "name": "new_network",
  "title": "New Network Map",
  "width": 800,
  "height": 600
}
```

**Response:**
```json
{
  "id": 2,
  "name": "new_network",
  "title": "New Network Map",
  "width": 800,
  "height": 600,
  "created_at": "2025-01-29T11:00:00Z",
  "updated_at": "2025-01-29T11:00:00Z"
}
```

#### Update Map
Update map properties.

```http
PUT /plugins/weathermapng/maps/{mapId}
Content-Type: application/json

{
  "title": "Updated Network Map",
  "width": 1024,
  "height": 768
}
```

#### Delete Map
Delete a map and all associated data.

```http
DELETE /plugins/weathermapng/maps/{mapId}
```

#### Export Map
Export map as JSON file.

```http
GET /plugins/weathermapng/api/maps/{mapId}/export?format=json
```

**Response:** JSON file download

#### Import Map
Import map from JSON file.

```http
POST /plugins/weathermapng/api/maps/import
Content-Type: multipart/form-data

File: map.json (JSON export from another instance)
```

### Devices

#### List Devices
Get all devices for map creation.

```http
GET /plugins/weathermapng/api/devices
```

**Response:**
```json
{
  "devices": [
    {
      "id": 1,
      "hostname": "core-router-01",
      "sysName": "Core Router 01",
      "ip": "192.168.1.1",
      "status": "up"
    }
  ]
}
```

#### Search Devices
Search devices by hostname or sysName.

```http
GET /plugins/weathermapng/api/devices/search?q=router
```

#### Get Device Ports
Get all ports for a specific device.

```http
GET /plugins/weathermapng/api/devices/{deviceId}/ports
```

**Response:**
```json
{
  "ports": [
    {
      "id": 1,
      "name": "GigabitEthernet0/0/0",
      "index": 1,
      "status": "up",
      "admin_status": "up"
    }
  ]
}
```

### Health

#### Health Check
Get system health status.

```http
GET /plugins/weathermapng/health
```

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2025-01-29T12:00:00Z",
  "version": "1.0.0",
  "checks": {
    "database": {
      "status": "healthy",
      "message": "Database connected, 5 maps found"
    },
    "rrd": {
      "status": "healthy",
      "message": "RRD directory accessible"
    },
    "output": {
      "status": "healthy",
      "message": "Output directory writable"
    },
    "api_token": {
      "status": "warning",
      "message": "API token not configured"
    }
  }
}
```

#### System Statistics
Get detailed system statistics.

```http
GET /plugins/weathermapng/health/stats
```

**Response:**
```json
{
  "maps": 5,
  "nodes": 23,
  "links": 18,
  "last_updated": "2025-01-29T12:00:00Z",
  "database_size": "2.5 MB",
  "cache_info": {
    "driver": "file",
    "status": "available"
  }
}
```

## Data Formats

### Map Object
```json
{
  "id": "integer",
  "name": "string",
  "title": "string",
  "width": "integer",
  "height": "integer",
  "background": "string (hex color)",
  "options": "object",
  "nodes": "array of Node objects",
  "links": "array of Link objects",
  "metadata": "object"
}
```

### Node Object
```json
{
  "id": "integer",
  "label": "string",
  "x": "float",
  "y": "float",
  "device_id": "integer|null",
  "device_name": "string|null",
  "status": "string (up|down|unknown)",
  "meta": "object"
}
```

### Link Object
```json
{
  "id": "integer",
  "src": "integer (node id)",
  "dst": "integer (node id)",
  "port_id_a": "integer|null",
  "port_id_b": "integer|null",
  "bandwidth_bps": "integer|null",
  "source_port_name": "string|null",
  "destination_port_name": "string|null",
  "bandwidth_formatted": "string",
  "style": "object"
}
```

### Live Data Object
```json
{
  "ts": "integer (unix timestamp)",
  "links": {
    "link_id": {
      "in_bps": "integer",
      "out_bps": "integer",
      "pct": "float|null",
      "err": "string|null"
    }
  }
}
```

## Examples

### JavaScript Integration

```javascript
// Load map structure
async function loadMap(mapId) {
  const response = await fetch(`/plugins/weathermapng/api/maps/${mapId}`);
  const mapData = await response.json();
  renderMap(mapData);
}

// Load live utilization data
async function loadLiveData(mapId) {
  const response = await fetch(`/plugins/weathermapng/api/maps/${mapId}/live`);
  const liveData = await response.json();
  updateUtilization(liveData);
}

// Create new map
async function createMap(name, title) {
  const response = await fetch('/plugins/weathermapng/maps', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({ name, title })
  });
  const result = await response.json();
  return result;
}
```

### Python Integration

```python
import requests

# Load map data
def get_map_data(map_id, base_url, auth_token):
    headers = {'X-Auth-Token': auth_token}
    response = requests.get(f"{base_url}/plugins/weathermapng/api/maps/{map_id}", headers=headers)
    return response.json()

# Get live utilization
def get_live_data(map_id, base_url, auth_token):
    headers = {'X-Auth-Token': auth_token}
    response = requests.get(f"{base_url}/plugins/weathermapng/api/maps/{map_id}/live", headers=headers)
    return response.json()

# Create map
def create_map(name, title, base_url, auth_token):
    headers = {'X-Auth-Token': auth_token, 'Content-Type': 'application/json'}
    data = {'name': name, 'title': title}
    response = requests.post(f"{base_url}/plugins/weathermapng/maps", json=data, headers=headers)
    return response.json()
```

### cURL Examples

```bash
# Get all maps
curl -H "X-Auth-Token: your-token" \
     https://librenms.example.com/plugins/weathermapng/api/maps

# Get specific map
curl -H "X-Auth-Token: your-token" \
     https://librenms.example.com/plugins/weathermapng/api/maps/1

# Get live data
curl -H "X-Auth-Token: your-token" \
     https://librenms.example.com/plugins/weathermapng/api/maps/1/live

# Health check
curl https://librenms.example.com/plugins/weathermapng/health
```

## Webhooks and Integrations

The API can be integrated with external monitoring systems, dashboards, and automation tools. Consider implementing webhooks for:

- Map creation/update notifications
- Utilization threshold alerts
- Device status changes
- System health alerts

## Best Practices

1. **Caching**: Implement client-side caching for frequently accessed data
2. **Error Handling**: Always check response status and handle errors gracefully
3. **Rate Limiting**: Respect API rate limits and implement exponential backoff
4. **Authentication**: Use secure token storage and rotation
5. **Validation**: Validate all input data before sending API requests
6. **Monitoring**: Monitor API usage and performance metrics

## Support

For API support and questions:
- [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- [LibreNMS Community](https://community.librenms.org/)
- [Documentation](https://github.com/lance0/weathermapNG/blob/main/README.md)

## Version History

- **v1.0.0**: Complete REST API with CRUD operations, live data, health checks
- **v0.1.0**: Basic API functionality (pre-release)