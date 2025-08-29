# WeathermapNG - Next-Generation LibreNMS Weathermap Plugin

A modern, secure, and extensible weathermap plugin for LibreNMS that provides interactive network topology visualization with real-time data integration using a database-driven architecture.

## Features

- **Modern Architecture**: Built for PHP 8+ with Laravel-ish MVC structure and clean routing
- **Database-Driven**: Uses MySQL/PostgreSQL with proper migrations and Eloquent models
- **Real-time Data**: Local RRD integration with API fallback for maximum compatibility
- **Interactive Editor**: Web-based drag-and-drop map editor with device integration
- **Embeddable Views**: Dashboard widgets and iframe support for external systems
- **Security First**: Auth-guarded routes, server-side data fetching, and secure file handling
- **JSON API**: RESTful API for programmatic access and third-party integrations
- **Responsive Design**: Mobile-friendly interface with modern UI components
- **Service Layer**: Dedicated services for RRD fetching, device lookup, and business logic

## Requirements

- LibreNMS (latest stable version recommended)
- PHP 8.0 or higher
- MySQL or PostgreSQL (LibreNMS database)
- GD extension for image generation
- Composer for dependency management
- Web server with URL rewriting support

## Installation

### 1. Download and Install

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
composer install
```

### 2. Run Database Migrations

```bash
cd /opt/librenms
php artisan migrate
```

This will create the necessary tables:
- `wmng_maps` - Store map metadata and configuration
- `wmng_nodes` - Network devices with positioning
- `wmng_links` - Connections between nodes with bandwidth

### 3. Set Permissions

```bash
# Set proper ownership
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# Set directory permissions
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output

# Make poller executable
chmod +x /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php

# Set SELinux context if applicable
chcon -R -t httpd_sys_content_t /opt/librenms/html/plugins/WeathermapNG
```

### 4. Enable Plugin

1. Log into LibreNMS web interface
2. Navigate to **Overview → Plugins → Plugin Admin**
3. Find **WeathermapNG** in the list
4. Click **Enable**

### 5. Configure Cron Job

Add the following line to `/etc/cron.d/librenms`:

```bash
*/5 * * * * librenms /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1
```

### 6. Verify Installation

Visit `https://your-librenms/plugins/weathermapng` to access the plugin.

## Usage

### Creating Your First Map

1. **Access the Editor**: Navigate to the WeathermapNG section and click "Create New Map"
2. **Configure Map**: Enter a name, title, and dimensions for your map
3. **Add Devices**: Use the device search to find and add network devices from LibreNMS
4. **Position Nodes**: Drag and drop devices on the canvas to position them
5. **Create Links**: Draw connections between devices and configure bandwidth
6. **Save Map**: Your map is automatically saved to the database

### Database Schema

Maps are stored in a relational database with the following structure:

**Maps Table (`wmng_maps`)**:
- `id` - Primary key
- `name` - Unique map identifier
- `title` - Human-readable title
- `options` - JSON configuration (dimensions, background, etc.)
- `timestamps` - Created/updated timestamps

**Nodes Table (`wmng_nodes`)**:
- `id` - Primary key
- `map_id` - Foreign key to maps
- `label` - Display name
- `x`, `y` - Canvas coordinates
- `device_id` - LibreNMS device reference
- `meta` - JSON metadata (custom properties)

**Links Table (`wmng_links`)**:
- `id` - Primary key
- `map_id` - Foreign key to maps
- `src_node_id`, `dst_node_id` - Connected nodes
- `port_id_a`, `port_id_b` - Interface references
- `bandwidth_bps` - Link capacity
- `style` - JSON styling options

### Embedding Maps

#### Dashboard Widget
```html
<iframe src="/plugins/weathermapng/embed/your-map-name"
        width="100%" height="400" frameborder="0">
</iframe>
```

#### External System
```html
<div style="width: 100%; height: 400px; border: 1px solid #ccc;">
    <iframe src="https://your-librenms/plugins/weathermapng/embed/your-map-name"
            width="100%" height="100%" frameborder="0">
    </iframe>
</div>
```

## API Reference

### REST API Endpoints

All API endpoints require authentication and return JSON responses.

#### List Maps
```
GET /plugins/weathermapng/api/maps
```

#### Get Map Data
```
GET /plugins/weathermapng/api/maps/{mapId}
```

#### Create Map
```
POST /plugins/weathermapng/maps
Content-Type: application/json

{
    "name": "My Network Map",
    "title": "Network Core Map",
    "width": 800,
    "height": 600
}
```

#### Update Map
```
PUT /plugins/weathermapng/maps/{map}
```

#### Delete Map
```
DELETE /plugins/weathermapng/maps/{map}
```

#### Get Devices
```
GET /plugins/weathermapng/api/devices
```

#### Get Device Interfaces
```
GET /plugins/weathermapng/api/devices/{deviceId}/ports
```

#### Live Data (Real-time)
```
GET /plugins/weathermapng/api/maps/{mapId}/live
```

#### Export Map
```
GET /plugins/weathermapng/api/maps/{mapId}/export?format=json
```

#### Import Map
```
POST /plugins/weathermapng/api/maps/import
Content-Type: multipart/form-data
File: map.json
```

### JavaScript API

```javascript
// Load map data
fetch('/plugins/weathermapng/api/map/my-map')
    .then(response => response.json())
    .then(data => {
        console.log('Map nodes:', data.nodes);
        console.log('Map links:', data.links);
    });
```

## Configuration

### Plugin Settings

Edit `config/weathermapng.php` to customize:

```php
return [
    'poll_interval' => 300,         // seconds for CLI poller default
    'thresholds' => [50,80,95],     // % utilization thresholds
    'scale' => 'bits',              // 'bits' or 'bytes'
    'rrd_base' => '/opt/librenms/rrd',
    'rrdcached' => [
        'socket' => null,           // e.g. /var/run/rrdcached.sock
    ],
    'enable_local_rrd' => true,     // Use local RRD files
    'enable_api_fallback' => true,  // Fallback to LibreNMS API
    'cache_ttl' => 300,             // Cache TTL in seconds
    'api_token' => env('LIBRENMS_API_TOKEN'),
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        'node_warning' => '#ffc107',
        'node_unknown' => '#6c757d',
        'link_normal' => '#28a745',
        'link_warning' => '#ffc107',
        'link_critical' => '#dc3545',
        'background' => '#ffffff',
    ],
    'rendering' => [
        'image_format' => 'png',
        'quality' => 90,
        'font_size' => 10,
        'node_radius' => 10,
        'link_width' => 2,
    ],
    'security' => [
        'allow_embed' => true,
        'embed_domains' => ['localhost', '*.yourdomain.com'],
        'max_image_size' => 2048, // KB
    ],
    'editor' => [
        'grid_size' => 20,
        'snap_to_grid' => true,
        'auto_save' => true,
        'auto_save_interval' => 30, // seconds
    ],
];
```

### Environment Variables

Set these in your `.env` file:

```bash
LIBRENMS_API_TOKEN=your_api_token_here
WEATHERMAPNG_RRD_PATH=/opt/librenms/rrd
```

## Security Considerations

- **Authentication**: All routes are protected by LibreNMS authentication
- **File Access**: Sensitive files are protected by `.htaccess` rules
- **Data Validation**: All input is validated and sanitized
- **RRD Security**: Local RRD access is restricted to read-only operations
- **API Security**: API tokens are required for external access

## Troubleshooting

### Common Issues

#### Maps Not Updating
- Check cron job is running: `crontab -l | grep weathermapng`
- Verify poller permissions: `ls -la bin/map-poller.php`
- Check log file: `tail -f /var/log/librenms/weathermapng.log`
- Ensure poller is executable: `chmod +x bin/map-poller.php`

#### Database Issues
- Run migrations: `cd /opt/librenms && php artisan migrate`
- Check database permissions for LibreNMS user
- Verify table creation: `SHOW TABLES LIKE 'wmng_%';`

#### Images Not Generating
- Ensure GD extension is installed: `php -m | grep gd`
- Check output directory permissions: `ls -la output/`
- Verify LibreNMS bootstrap path in `bin/map-poller.php`

#### API Errors
- Verify API token in `config/weathermapng.php`
- Check LibreNMS API is enabled in web interface
- Review web server error logs: `tail -f /var/log/httpd/error_log`

#### Permission Issues
- Set correct ownership: `chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG`
- Set executable permissions: `chmod +x bin/map-poller.php`
- Check SELinux: `chcon -R -t httpd_sys_content_t /opt/librenms/html/plugins/WeathermapNG`

### Debug Mode

Enable debug logging by adding to `config/weathermapng.php`:

```php
'debug' => true,
'log_level' => 'debug',
'log_file' => '/var/log/librenms/weathermapng.log',
```

## Development

### Project Structure

```
WeathermapNG/
├── WeathermapNG.php                 # Plugin bootstrap
├── composer.json                    # Dependencies & autoloading
├── routes.php                       # Route definitions
├── database/
│   └── migrations/
│       └── 2025_08_29_000001_create_weathermapng_tables.php
├── Http/
│   └── Controllers/
│       ├── MapController.php        # Web interface controllers
│       └── RenderController.php     # JSON API controllers
├── Models/
│   ├── Map.php                      # Map Eloquent model
│   ├── Node.php                     # Node Eloquent model
│   └── Link.php                     # Link Eloquent model
├── Policies/
│   └── MapPolicy.php                # Authorization policies
├── Services/
│   ├── PortUtilService.php          # RRD/API data fetching
│   └── DevicePortLookup.php         # Device/port lookups
├── Resources/
│   ├── views/
│   │   ├── index.blade.php          # Maps list
│   │   ├── editor.blade.php         # Map editor
│   │   ├── show.blade.php           # Map viewer
│   │   └── embed.blade.php          # Embeddable viewer
│   ├── js/
│   │   ├── editor.js                # Editor functionality
│   │   └── viewer.js                # Viewer functionality
│   └── css/
│       └── weathermapng.css        # Stylesheets
├── config/
│   └── weathermapng.php             # Configuration
├── bin/
│   └── map-poller.php               # Executable poller
├── lib/
│   └── RRD/
│       ├── RRDTool.php              # RRD file handling
│       └── LibreNMSAPI.php          # API fallback
├── output/                          # Generated content (git-ignored)
│   ├── maps/
│   └── thumbnails/
├── LICENSE                          # Unlicense
└── README.md                        # This file
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Testing

```bash
composer test
composer lint
```

## License

This project is licensed under the Unlicense - see the LICENSE file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- **Documentation**: [LibreNMS Docs](https://docs.librenms.org/)
- **Community**: [LibreNMS Community](https://community.librenms.org/)

## Changelog

### Version 1.0.0
- Complete rewrite with database-driven architecture
- Laravel-ish MVC structure with proper separation of concerns
- Database migrations for `wmng_maps`, `wmng_nodes`, `wmng_links` tables
- Eloquent models with relationships and accessors
- Service layer for business logic (RRD fetching, device lookup)
- RESTful JSON API with authentication
- Interactive drag-and-drop editor
- Real-time data polling with caching
- Embeddable viewers for dashboards
- CLI poller for background processing
- Comprehensive security hardening
- Unlicense for maximum freedom