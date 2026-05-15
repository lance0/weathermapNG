# WeathermapNG - Network Visualization for LibreNMS

A modern network weathermap plugin for LibreNMS v2 that provides real-time network topology visualization with traffic flow animations.

![WeathermapNG Live View](wmng.png)

![WeathermapNG Editor](wmng-editor.png)

## Features

- **Real-time Visualization**: Live traffic data with animated flow indicators
- **Professional Editor**: 3-panel layout with toolbox, canvas, and properties sidebar
- **Via Points & Curved Links**: Route links through intermediate waypoints with straight, angled, or Catmull-Rom curved paths
- **Zoom, Pan & Minimap**: Mouse-wheel zoom, middle-click pan, click-to-navigate minimap
- **Keyboard Shortcuts**: Ctrl+S save, Ctrl+Z/Y undo/redo, arrow nudge, Delete, +/-/0 zoom
- **Undo/Redo**: 50-state history with full node and link state tracking
- **Dark/Light Mode**: Auto-detects LibreNMS theme and matches it
- **Grid Snapping**: Toggle snap-to-grid for precise node alignment
- **RRD-based Traffic Data**: Real-time bandwidth from LibreNMS RRD files
- **Server-Sent Events**: Live updates without polling
- **Import/Export**: JSON format for backup and sharing maps
- **Embed Support**: Embed maps in dashboards with live updates
- **Map Templates**: Built-in templates for common network topologies
- **Map Versioning Foundation**: Snapshot storage and history services for map rollback workflows

## Quick Start

### One-Command Install (Recommended)

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
sudo chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
sudo -u librenms -H bash -lc 'cd /opt/librenms/html/plugins/WeathermapNG && ./quick-install.sh'
```

The script automatically installs dependencies, registers the Composer package with LibreNMS, sets up database tables, configures permissions, and enables the plugin.
Run it as the `librenms` user on native installs; running as root can leave root-owned Composer files behind.

### Manual Install

```bash
# 1. Clone and install
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG
composer install --no-dev

# 2. Register with LibreNMS Composer
cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover

# 3. Setup database
cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php

# 4. Configure LibreNMS
cd /opt/librenms
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# 5. Enable plugin
./lnms plugin:enable WeathermapNG

# 6. Verify routes
php artisan route:list | grep -iE 'weathermap|wmng'
```

### Requirements

- LibreNMS (latest stable)
- PHP 8.2+
- Composer
- MySQL/MariaDB

**Access the plugin at**: `https://your-librenms/plugin/WeathermapNG`

## Getting Started

1. **Access the Plugin**: Navigate to `https://your-librenms/plugin/WeathermapNG`
2. **Create Map**: Click "Create New Map" or select a template
3. **Configure**: Enter name, title, and dimensions
4. **Design**: Use the canvas editor to add devices and connections
5. **Save**: Your map is now live with real-time traffic data

### Tips

- Use the device dropdown in the right sidebar to add nodes
- Drag nodes to position them; enable grid snapping for alignment
- Double-click a link to add a waypoint; drag to reposition; double-click again to remove
- Use Ctrl+Z/Y to undo/redo, arrow keys to nudge selected nodes
- Scroll wheel to zoom, middle-click to pan around large maps
- Export maps as JSON for backup or sharing

### Demo Mode

Test the plugin without real LibreNMS devices:

```bash
# Enable demo mode (generates simulated traffic)
echo "WEATHERMAPNG_DEMO_MODE=true" >> /opt/librenms/.env

# Create sample network topology
php /opt/librenms/html/plugins/WeathermapNG/database/seed-demo.php
```

## Embedding Maps

```html
<iframe src="https://your-librenms/plugin/WeathermapNG/embed/1"
        width="800" height="600" frameborder="0">
</iframe>
```

Optional query parameters: `metric` (percent/in/out/sum), `sse=0` (force polling), `nav=0` (disable pan/zoom), `scale=bytes`. See [Embed Viewer Guide](docs/EMBED.md).

## Troubleshooting

### Plugin Not Showing

```bash
cd /opt/librenms
php artisan route:list | grep -iE 'weathermap|wmng'
php artisan cache:clear
php artisan view:clear
```

If no WeathermapNG routes are listed, register the plugin as a Composer path package:

```bash
cd /opt/librenms
composer config repositories.weathermapng '{"type":"path","url":"html/plugins/WeathermapNG","options":{"symlink":true}}'
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan config:clear
```

LibreNMS `validate.php` may report `wmng_*` tables as extra tables. Those tables belong to WeathermapNG and should not be dropped.

It may also report `utf8mb4_bin` collation warnings on JSON-backed WeathermapNG columns such as `wmng_map_templates.config`, `wmng_nodes.meta`, `wmng_maps.options`, and `wmng_links.style`. Those warnings are expected for the current schema.

If an older install left duplicate `WeathermapNG` rows in LibreNMS' `plugins` table, rerun `quick-install.sh` as the `librenms` user. The installer normalizes plugin registration and removes stale duplicate rows.

### Permission Errors

```bash
sudo chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

### Database Issues

```bash
cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php
```

### Maps Not Updating

- Verify the map has valid device and port associations.
- Check that the LibreNMS user can read the relevant RRD files.
- If you use the optional poller, check its cron entry and logs.
- Use demo mode to separate rendering issues from live data issues.

## Updating

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev --optimize-autoloader
php database/setup.php
cd /opt/librenms
FORCE=1 composer require 'librenms/weathermapng:*' --with-dependencies --no-interaction
php artisan package:discover
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:list | grep -iE 'weathermap|wmng'
```

## Architecture

WeathermapNG follows a modular service-oriented architecture:

| Service | Purpose |
|---------|---------|
| NodeDataService | Node data aggregation, metrics, and traffic |
| DeviceDataService | Device status and hostname-based traffic |
| LinkDataService | Link alerts and port-level aggregation |
| PortUtilService | RRD-based traffic data for links |
| MapService | Map CRUD and JSON import/export |
| SseStreamService | Real-time Server-Sent Events streaming |

## Development

### Running Tests

```bash
composer test
```

### Code Quality

```bash
composer quality
```

## Documentation

- [Detailed Installation Guide](INSTALL.md)
- [Deployment Guide](DEPLOYMENT.md)
- [API Documentation](API.md)
- [Embed Viewer Guide](docs/EMBED.md)
- [Versioning Guide](VERSIONING.md)
- [Performance Notes](PERFORMANCE.md)
- [Roadmap](ROADMAP.md)
- [Configuration Reference](config/config.php)

## Contributing

Pull requests welcome. Please follow PSR-12 coding standards and include tests.

## Support

- **Issues**: [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- **Community**: [community.librenms.org](https://community.librenms.org)

## License

MIT License - see [LICENSE](LICENSE)
