# WeathermapNG API Documentation

## Authentication

All API endpoints require LibreNMS authentication (session cookies or API token).

## Main Endpoints

### Maps

#### List All Maps
```http
GET /plugin/WeathermapNG
```

#### Get Map Data (JSON)
```http
GET /plugin/WeathermapNG/api/maps/{id}/json
```

#### Get Live Map Data
```http
GET /plugin/WeathermapNG/api/maps/{id}/live
```
Returns real-time bandwidth and status data.

#### Create Map
```http
POST /plugin/WeathermapNG/map
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
PUT /plugin/WeathermapNG/map/{id}
```

#### Delete Map
```http
DELETE /plugin/WeathermapNG/map/{id}
```

### Devices

#### Get All Devices
```http
GET /plugin/WeathermapNG/api/devices
```

#### Search Devices
```http
GET /plugin/WeathermapNG/api/devices/search?q=router
```

#### Get Device Ports
```http
GET /plugin/WeathermapNG/api/device/{id}/ports
```

### Health & Monitoring

#### Basic Health Check
```http
GET /plugin/WeathermapNG/health
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
GET /plugin/WeathermapNG/metrics
```

#### Readiness Probe (for Kubernetes/Docker)
```http
GET /plugin/WeathermapNG/ready
```

#### Liveness Probe (for Kubernetes/Docker)
```http
GET /plugin/WeathermapNG/live
```

#### Prometheus Metrics
```http
GET /plugin/WeathermapNG/metrics
```

### Import/Export

#### Export Map
```http
GET /plugin/WeathermapNG/api/maps/{id}/export
```

#### Import Map
```http
POST /plugin/WeathermapNG/api/import
Content-Type: multipart/form-data
```

### Embedding

#### Embed Map (authenticated)
```http
GET /plugin/WeathermapNG/embed/{id}
```

#### Public Embed (if configured)
```http
GET /plugin/WeathermapNG/public/embed/{id}
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
     https://librenms/plugin/WeathermapNG/api/maps/1/json

# Check health
curl https://librenms/plugin/WeathermapNG/health

# Get metrics for Prometheus
curl https://librenms/plugin/WeathermapNG/metrics
```
