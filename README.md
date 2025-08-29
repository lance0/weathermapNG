# WeathermapNG - Next-Generation LibreNMS Weathermap Plugin

A modern, secure, and extensible weathermap plugin for LibreNMS that provides interactive network topology visualization with real-time data integration.

## Features

- **Modern Architecture**: Built for PHP 8+ with Laravel-ish structure and clean routing
- **Real-time Data**: Local RRD integration with API fallback for maximum compatibility
- **Interactive Editor**: Web-based drag-and-drop map editor with device integration
- **Embeddable Views**: Dashboard widgets and iframe support for external systems
- **Security First**: Auth-guarded routes, server-side data fetching, and secure file handling
- **JSON API**: RESTful API for programmatic access and third-party integrations
- **Responsive Design**: Mobile-friendly interface with modern UI components

## Requirements

- LibreNMS (latest stable version recommended)
- PHP 8.0 or higher
- GD extension for image generation
- Composer for dependency management
- Web server with URL rewriting support

## Installation

### 1. Download and Install

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/yourusername/WeathermapNG.git
cd WeathermapNG
composer install
```

### 2. Set Permissions

```bash
# Set proper ownership
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# Set directory permissions
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/config
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output

# Set SELinux context if applicable
chcon -R -t httpd_sys_content_t /opt/librenms/html/plugins/WeathermapNG
```

### 3. Enable Plugin

1. Log into LibreNMS web interface
2. Navigate to **Overview → Plugins → Plugin Admin**
3. Find **WeathermapNG** in the list
4. Click **Enable**

### 4. Configure Cron Job

Add the following line to `/etc/cron.d/librenms`:

```bash
*/5 * * * * librenms /opt/librenms/html/plugins/WeathermapNG/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1
```

### 5. Verify Installation

Visit `https://your-librenms/plugins/weathermapng` to access the plugin.

## Usage

### Creating Your First Map

1. **Access the Editor**: Navigate to the WeathermapNG section and click "Create New Map"
2. **Select Devices**: Use the device dropdown to choose network devices from LibreNMS
3. **Add Interfaces**: Select interfaces for each device to monitor
4. **Place Nodes**: Click "Add Node" to place devices on the canvas
5. **Configure Links**: The system automatically suggests connections between nodes
6. **Save Map**: Enter a name and save your configuration

### Map Configuration Format

Maps are stored as INI-style configuration files in `config/maps/`. Example:

```ini
[global]
width 800
height 600
title "Network Core Map"

[node:router1]
label "Core Router 1"
x 200
y 150
device_id 1
interface_id 1
metric traffic_in

[node:switch1]
label "Access Switch"
x 400
y 300
device_id 2
interface_id 2
metric traffic_in

[link:router1-switch1]
nodes router1 switch1
bandwidth 1000000000
label "1Gbps Uplink"
```

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
GET /plugins/weathermapng/api/map/{mapId}
```

#### Create Map
```
POST /plugins/weathermapng/api/map
Content-Type: application/json

{
    "name": "My Network Map",
    "config": "[global]\nwidth 800\nheight 600\n..."
}
```

#### Update Map
```
PUT /plugins/weathermapng/api/map/{mapId}
```

#### Delete Map
```
DELETE /plugins/weathermapng/api/map/{mapId}
```

#### Get Devices
```
GET /plugins/weathermapng/api/devices
```

#### Get Device Interfaces
```
GET /plugins/weathermapng/api/devices/{deviceId}/interfaces
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

Edit `config/settings.php` to customize:

```php
return [
    'map_dir' => __DIR__ . '/maps/',
    'output_dir' => __DIR__ . '/output/maps/',
    'poll_interval' => 300, // 5 minutes
    'default_width' => 800,
    'default_height' => 600,
    'enable_local_rrd' => true,
    'enable_api_fallback' => true,
    'cache_ttl' => 300,
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        // ... more colors
    ]
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
- Verify poller permissions: `ls -la map-poller.php`
- Check log file: `tail -f /var/log/librenms/weathermapng.log`

#### Images Not Generating
- Ensure GD extension is installed: `php -m | grep gd`
- Check output directory permissions
- Verify LibreNMS bootstrap path in `map-poller.php`

#### API Errors
- Verify API token in configuration
- Check LibreNMS API is enabled
- Review web server error logs

### Debug Mode

Enable debug logging by adding to `config/settings.php`:

```php
'debug' => true,
'log_level' => 'debug',
```

## Development

### Project Structure

```
WeathermapNG/
├── WeathermapNG.php          # Main plugin file
├── composer.json             # Dependencies
├── config/
│   ├── settings.php         # Configuration
│   └── maps/                # Map configurations
├── lib/                     # Core classes
│   ├── Map.php             # Map management
│   ├── Node.php            # Node representation
│   ├── Link.php            # Link representation
│   ├── DataSource.php      # Data integration
│   ├── API/                # API controllers
│   └── RRD/                # RRD handling
├── templates/               # Blade templates
├── js/                      # Frontend JavaScript
├── css/                     # Stylesheets
├── output/                  # Generated content
└── map-poller.php          # Cron script
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

This project is licensed under the GPL-3.0-or-later License - see the LICENSE file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/WeathermapNG/issues)
- **Documentation**: [LibreNMS Docs](https://docs.librenms.org/)
- **Community**: [LibreNMS Community](https://community.librenms.org/)

## Changelog

### Version 1.0.0
- Initial release
- Basic map creation and editing
- Real-time data integration
- JSON API
- Embeddable views
- Security hardening