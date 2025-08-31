# WeathermapNG API Documentation

## Authentication

All API endpoints require LibreNMS authentication (session cookies or API token).

## Main Endpoints

### Maps

#### List All Maps
```http
GET /plugins/weathermapng/
```

#### Get Map Data (JSON)
```http
GET /plugins/weathermapng/api/maps/{mapId}
```

#### Get Live Map Data
```http
GET /plugins/weathermapng/api/maps/{mapId}/live
```
Returns real-time bandwidth and status data.

#### Create Map
```http
POST /plugins/weathermapng/maps
Content-Type: application/json

{
    "name": "my_map",
    "title": "My Network Map",
    "width": 800,
    "height": 600
}
```

#### Update Map
```http
PUT /plugins/weathermapng/maps/{mapId}
```

#### Delete Map
```http
DELETE /plugins/weathermapng/maps/{mapId}
```

### Devices

#### Get All Devices
```http
GET /plugins/weathermapng/api/devices
```

#### Search Devices
```http
GET /plugins/weathermapng/api/devices/search?q=router
```

#### Get Device Ports
```http
GET /plugins/weathermapng/api/devices/{deviceId}/ports
```

### Health & Monitoring

#### Basic Health Check
```http
GET /plugins/weathermapng/health
```

Response:
```json
{
    "status": "healthy",
    "timestamp": "2025-01-30T10:00:00Z",
    "version": "1.0.0",
    "checks": {
        "database": {"status": "healthy"},
        "rrd": {"status": "healthy"},
        "output": {"status": "healthy"}
    }
}
```

#### Statistics
```http
GET /plugins/weathermapng/health/stats
```

#### Readiness Probe (for Kubernetes/Docker)
```http
GET /plugins/weathermapng/ready
```

#### Liveness Probe (for Kubernetes/Docker)
```http
GET /plugins/weathermapng/live
```

#### Prometheus Metrics
```http
GET /plugins/weathermapng/metrics
```

### Import/Export

#### Export Map
```http
GET /plugins/weathermapng/api/maps/{mapId}/export
```

#### Import Map
```http
POST /plugins/weathermapng/api/maps/import
Content-Type: multipart/form-data
```

### Embedding

#### Embed Map (authenticated)
```http
GET /plugins/weathermapng/embed/{mapId}
```

#### Public Embed (if configured)
```http
GET /plugins/weathermapng/public/embed/{mapId}
```

## Response Codes

- `200` - Success
- `201` - Created
- `401` - Unauthorized
- `404` - Not Found
- `500` - Server Error
- `503` - Service Unavailable (health check failed)

## Example Usage

```bash
# Get map data
curl -H "X-Auth-Token: your-token" \
     https://librenms/plugins/weathermapng/api/maps/1

# Check health
curl https://librenms/plugins/weathermapng/health

# Get metrics for Prometheus
curl https://librenms/plugins/weathermapng/metrics
```