# 🚀 WeathermapNG - Next-Generation LibreNMS Weathermap Plugin

[![Installation Time](https://img.shields.io/badge/Installation-2%20minutes-brightgreen)](https://github.com/lance0/weathermapNG)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-Unlicense-green)](LICENSE)

**The easiest way to create interactive network topology maps in LibreNMS!**

WeathermapNG provides modern, secure, and extensible network topology visualization with real-time data integration using a database-driven architecture.

## ✨ Quick Start (2 Minutes!)

### 🚀 One-Click Installation
```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
./install.sh
```

**That's it!** 🎉 Your plugin is now installed and ready to use.

### 🌐 Alternative: Web Installer (1 Minute)
1. Download and place plugin in `/opt/librenms/html/plugins/WeathermapNG`
2. Visit `https://your-librenms/plugins/weathermapng/install`
3. Click "Start Installation"

### ✅ Verify Installation
```bash
cd /opt/librenms/html/plugins/WeathermapNG
php verify.php
```

## 📋 Quick Reference

| Task | Command | Description |
|------|---------|-------------|
| **Install** | `./install.sh` | One-click installation |
| **Verify** | `php verify.php` | Check installation |
| **Update** | `composer update` | Update dependencies |
| **Test** | `composer test` | Run test suite |
| **Logs** | `tail -f /var/log/librenms/weathermapng.log` | View logs |

## 🎯 What You Get

- **📊 Interactive Maps**: Drag-and-drop network topology editor
- **⚡ Real-time Data**: Live bandwidth utilization from LibreNMS
- **🎨 Modern UI**: Responsive design with dark mode support
- **🔗 API Integration**: RESTful API for external systems
- **📱 Embeddable**: Dashboard widgets and iframe support
- **🔒 Secure**: Auth-guarded routes and secure file handling
- **🔌 LibreNMS Integration**: Full plugin system compliance with hooks for Device Overview, Port Tabs, Menu, and Settings

## 📋 System Requirements

| Component | Requirement | Status |
|-----------|-------------|---------|
| LibreNMS | Latest stable | ✅ |
| PHP | 8.0 or higher | ✅ |
| Database | MySQL/PostgreSQL | ✅ |
| GD Extension | Enabled | ✅ |
| Composer | Latest | ✅ |

## 📦 Installation Methods

### 🎯 Recommended: Automated Installation

#### Method 1: One-Click Script (Fastest)
```bash
# Run as librenms user (not root)
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
./install.sh
```

**What happens automatically:**
- ✅ Downloads dependencies
- ✅ Creates database tables
- ✅ Sets proper permissions
- ✅ Configures cron job
- ✅ Enables plugin in LibreNMS

#### Method 2: Web-Based Installer (Easiest)
1. Download and extract to `/opt/librenms/html/plugins/WeathermapNG`
2. Visit: `https://your-librenms/plugins/weathermapng/install`
3. Click **"Start Installation"**
4. Done! 🎉

### 🔧 Manual Installation (Advanced)

If automated methods fail:

```bash
# 1. Install dependencies
cd /opt/librenms/html/plugins/WeathermapNG
composer install

# 2. Run migrations
cd /opt/librenms
php -r "
require 'vendor/autoload.php';
require 'bootstrap/app.php';
\$plugin = new \LibreNMS\Plugins\WeathermapNG\WeathermapNG();
\$plugin->activate();
"

# 3. Set permissions
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output
chmod +x /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php

# 4. Add cron job
echo '*/5 * * * * librenms /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1' >> /etc/cron.d/librenms
```

### ✅ Final Steps

1. **Enable Plugin:**
   - Login to LibreNMS web interface
   - Go to **Overview → Plugins → Plugin Admin**
   - Find **WeathermapNG** and click **Enable**

2. **Verify Installation:**
   ```bash
   cd /opt/librenms/html/plugins/WeathermapNG
   php verify.php
   ```

3. **Access Plugin:**
   - Visit: `https://your-librenms/plugins/weathermapng`

## 🐳 Docker Installation

WeathermapNG has **full Docker support** with automatic environment detection and container-optimized installation!

### Docker Installation Methods

#### Method 1: Docker Compose (Recommended)
```yaml
version: '3.8'
services:
  librenms:
    image: librenms/librenms:latest
    environment:
      - LIBRENMS_DOCKER=true
      - SKIP_CRON=true  # Let orchestration handle scheduling
    volumes:
      - ./WeathermapNG:/opt/librenms/html/plugins/WeathermapNG
    command: >
      bash -c "
        cd /opt/librenms/html/plugins/WeathermapNG &&
        ./install.sh &&
        /opt/librenms/librenms.sh
      "
    depends_on:
      - db

  weathermap-poller:
    image: librenms/librenms:latest
    environment:
      - LIBRENMS_DOCKER=true
    volumes:
      - ./WeathermapNG:/opt/librenms/html/plugins/WeathermapNG
    command: >
      bash -c "
        while true; do
          cd /opt/librenms/html/plugins/WeathermapNG &&
          php bin/map-poller.php
          sleep 300
        done
      "
    depends_on:
      - librenms
```

#### Method 2: Docker Compose (Recommended)
```bash
# Use the provided example
cp docker-compose.example.yml docker-compose.yml
# Edit docker-compose.yml with your settings
docker-compose up -d

# Or use the quick setup
curl -s https://raw.githubusercontent.com/lance0/weathermapNG/main/docker-compose.example.yml > docker-compose.yml
docker-compose up -d
```

#### Method 3: Manual Docker Installation
```bash
# Install in running container
docker exec -it librenms bash -c "
  cd /opt/librenms/html/plugins/WeathermapNG &&
  ./install.sh
"

# Or use environment variables
docker run -e LIBRENMS_DOCKER=true \
  -v $(pwd)/WeathermapNG:/opt/librenms/html/plugins/WeathermapNG \
  librenms/librenms:latest \
  bash -c "cd /opt/librenms/html/plugins/WeathermapNG && ./install.sh"
```

#### Method 4: Build-Time Installation
```dockerfile
FROM librenms/librenms:latest

# Install WeathermapNG during build
COPY WeathermapNG /opt/librenms/html/plugins/WeathermapNG
RUN cd /opt/librenms/html/plugins/WeathermapNG && \
    LIBRENMS_DOCKER=true ./install.sh

# Set environment
ENV LIBRENMS_DOCKER=true
```

#### Method 3: Build-Time Installation
```dockerfile
FROM librenms/librenms:latest

# Install WeathermapNG during build
COPY WeathermapNG /opt/librenms/html/plugins/WeathermapNG
RUN cd /opt/librenms/html/plugins/WeathermapNG && \
    LIBRENMS_DOCKER=true ./install.sh

# Set environment
ENV LIBRENMS_DOCKER=true
```

### Docker Configuration

#### Configuration File
For advanced Docker setups, use the provided Docker configuration:

```bash
# Copy the Docker-optimized config
cp config/weathermapng.docker.php config/weathermapng.php
```

This configuration includes:
- Docker-aware logging (stdout instead of files)
- Container networking database settings
- Optimized polling intervals for containers
- Environment variable support

#### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `LIBRENMS_DOCKER` | Enable Docker mode | `false` |
| `SKIP_CRON` | Skip cron setup (use orchestration) | `false` |
| `LIBRENMS_PATH` | Custom LibreNMS path | Auto-detected |
| `WEATHERMAP_OUTPUT` | Custom output directory | Auto-detected |
| `LOG_TO_STDOUT` | Log to stdout instead of file | `true` in Docker |
| `WEATHERMAP_LOG` | Custom log file path | `/dev/stdout` |
| `LIBRENMS_RRD_BASE` | RRD files location | `/opt/librenms/rrd` |

### Docker Troubleshooting

#### Permission Issues in Container?
```bash
# Check container user
docker exec librenms whoami

# Fix permissions in running container
docker exec librenms chown -R www-data:www-data /opt/librenms/html/plugins/WeathermapNG
```

#### Database Connection Failed?
```bash
# Check database connectivity from container
docker exec librenms mysql -h db -u librenms -p librenms

# Verify environment variables
docker exec librenms env | grep DB_
```

#### Cron Not Working in Container?
```bash
# Use Docker Compose for scheduling
version: '3.8'
services:
  weathermap-cron:
    image: librenms/librenms:latest
    command: >
      bash -c "
        while true; do
          php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
          sleep 300
        done
      "
```

#### Plugin Not Loading?
```bash
# Check plugin directory in container
docker exec librenms ls -la /opt/librenms/html/plugins/

# Verify plugin files
docker exec librenms find /opt/librenms/html/plugins/WeathermapNG -name "*.php" | head -5
```

### Docker Compose Examples

#### Basic Setup
```yaml
version: '3.8'
services:
  librenms:
    image: librenms/librenms:latest
    environment:
      - LIBRENMS_DOCKER=true
    volumes:
      - ./WeathermapNG:/opt/librenms/html/plugins/WeathermapNG
```

#### Advanced Setup with Separate Poller
```yaml
version: '3.8'
services:
  librenms:
    image: librenms/librenms:latest
    environment:
      - LIBRENMS_DOCKER=true
      - SKIP_CRON=true
    volumes:
      - ./WeathermapNG:/opt/librenms/html/plugins/WeathermapNG
    depends_on:
      - db

  weathermap-poller:
    image: librenms/librenms:latest
    environment:
      - LIBRENMS_DOCKER=true
    volumes:
      - ./WeathermapNG:/opt/librenms/html/plugins/WeathermapNG
    command: >
      bash -c "
        while true; do
          php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
          sleep 300
        done
      "
    depends_on:
      - librenms
```

## 📊 Installation Comparison

| Method | Time | Steps | User Level | Automation | Docker Support |
|--------|------|-------|------------|------------|----------------|
| **Docker Compose** | 3 min | 2 commands | Beginner | 100% | ✅ Native |
| **One-Click Script** | 2 min | 3 commands | Beginner | 100% | ✅ Full |
| **Web Installer** | 1 min | 1 click | Beginner | 100% | ✅ Full |
| **Manual Docker** | 5 min | 4 commands | Intermediate | 90% | ✅ Full |
| **Manual Setup** | 30-45 min | 15+ steps | Advanced | 0% | ❌ Limited |

## 🔍 Troubleshooting

### Installation Fails?
```bash
# Check system requirements
cd /opt/librenms/html/plugins/WeathermapNG
php verify.php

# View installation logs
tail -f /var/log/librenms/weathermapng_install.log
```

### Plugin Not Showing?
- Ensure you're running as `librenms` user (not root)
- Check file permissions: `ls -la /opt/librenms/html/plugins/WeathermapNG`
- Verify LibreNMS can read the plugin files

### Database Issues?
- Check LibreNMS database credentials
- Ensure user has table creation permissions
- Verify MySQL/PostgreSQL is running

### Permission Errors?
```bash
# Fix ownership
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# Fix permissions
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output
```

## 🎨 Features

### Core Features
- **📊 Interactive Editor**: Drag-and-drop network topology creation
- **⚡ Real-time Monitoring**: Live bandwidth utilization from RRD files
- **🎨 Modern UI**: Responsive design with Bootstrap styling
- **🌙 Dark Mode**: Automatic dark mode support
- **📱 Mobile Friendly**: Works on all device sizes
- **🔗 API Integration**: RESTful API for external systems
- **📊 Multiple Data Sources**: RRD files + LibreNMS API fallback
- **🔒 Security First**: Auth-guarded routes and secure file handling

### Advanced Features
- **Custom Styling**: Color-coded nodes and links based on status
- **Auto Layout**: Intelligent node positioning algorithms
- **Export/Import**: JSON-based map backup and sharing
- **Embeddable**: Dashboard widgets and iframe support
- **Multi-user**: Role-based access control
- **Performance**: Optimized for large networks (1000+ devices)

## 🚀 Getting Started

### Creating Your First Map

1. **Access WeathermapNG**: Visit `https://your-librenms/plugins/weathermapng`
2. **Create New Map**: Click the **"Create New Map"** button
3. **Configure Settings**:
   - **Name**: Unique identifier (e.g., `core-network`)
   - **Title**: Display name (e.g., `Core Network Topology`)
   - **Dimensions**: Canvas size (default: 800x600)
4. **Add Devices**:
   - Use the device search dropdown
   - Select network devices from LibreNMS
   - Choose interfaces for monitoring
5. **Position Elements**: Drag nodes to arrange your topology
6. **Save & View**: Your map is automatically saved and starts monitoring

### Example Map Creation
```bash
# After installation, visit:
https://your-librenms/plugins/weathermapng

# Create a simple 3-node topology:
# Router1 (Core) ↔ Switch1 (Distribution) ↔ Server1 (Access)
```

## 🔌 LibreNMS Plugin Architecture

WeathermapNG is a **distributed plugin package** that fully complies with LibreNMS plugin standards:

### Plugin Hooks
- **Menu Hook**: Adds WeathermapNG to the LibreNMS navigation menu
- **Device Overview Hook**: Shows maps containing the device on device pages
- **Port Tab Hook**: Displays weathermap links on port detail pages
- **Settings Hook**: Integrates with LibreNMS settings for centralized configuration
- **Page Hook**: Provides the main weathermap interface

### Integration Points
- Database migrations managed through LibreNMS
- Authentication inherited from LibreNMS
- Permissions use LibreNMS user roles
- API endpoints secured with LibreNMS tokens

## 🏗️ Project Structure

```
WeathermapNG/
├── 📁 Hooks/                    # LibreNMS plugin hooks
│   ├── Menu.php                 # Navigation menu integration
│   ├── DeviceOverview.php       # Device page integration
│   ├── PortTab.php              # Port page integration
│   ├── Settings.php             # Settings page integration
│   └── Page.php                 # Main plugin page
├── 📁 Http/Controllers/          # Web controllers
│   ├── MapController.php        # Main map management
│   ├── RenderController.php     # API and rendering
│   ├── InstallController.php    # Installation wizard
│   └── HealthController.php     # Health check endpoints
├── 📁 Models/                   # Eloquent models
│   ├── Map.php                  # Map model
│   ├── Node.php                 # Network node model
│   └── Link.php                 # Connection model
├── 📁 Resources/views/          # Blade templates
│   ├── index.blade.php          # Map listing
│   ├── editor.blade.php         # Map editor
│   ├── embed.blade.php          # Embedded view
│   └── install/index.blade.php  # Installation wizard
├── 📁 Services/                 # Business logic
│   ├── DevicePortLookup.php     # Device/port integration
│   └── PortUtilService.php      # RRD data processing
├── 📁 database/migrations/      # Database schema
├── 📁 config/                   # Configuration files
├── 📁 lib/                      # Core libraries
│   ├── API/                     # API integrations
│   └── RRD/                     # RRD file handling
├── 📁 tests/                    # Unit tests
├── 📁 bin/                      # Executables
│   └── map-poller.php           # Background poller
├── install.sh                   # One-click installer
├── verify.php                   # Installation verifier
└── composer.json               # Dependencies
```

## 📚 Usage Examples

### Basic Map Creation
```javascript
// Create a simple network map
POST /plugins/weathermapng/maps
{
  "name": "office-network",
  "title": "Office Network Topology",
  "width": 800,
  "height": 600
}
```

### Embedding Maps
```html
<!-- Dashboard Widget -->
<iframe src="/plugins/weathermapng/embed/office-network"
        width="100%" height="400" frameborder="0">
</iframe>

<!-- External System -->
<div style="width: 100%; height: 400px; border: 1px solid #ccc;">
    <iframe src="https://librenms.company.com/plugins/weathermapng/embed/office-network"
            width="100%" height="100%" frameborder="0">
    </iframe>
</div>
```

### API Integration
```javascript
// Get map data
fetch('/plugins/weathermapng/api/maps/office-network')
    .then(response => response.json())
    .then(data => {
        console.log('Map nodes:', data.nodes);
        console.log('Map links:', data.links);
    });

// Get live data
fetch('/plugins/weathermapng/api/maps/office-network/live')
    .then(response => response.json())
    .then(data => {
        // Real-time bandwidth data
        data.nodes.forEach(node => {
            console.log(`${node.label}: ${node.current_value} Mbps`);
        });
    });
```

## ⚙️ Configuration

### Default Settings
WeathermapNG comes pre-configured with optimal settings:

```php
// config/weathermapng.php
return [
    'default_width' => 800,
    'default_height' => 600,
    'poll_interval' => 300, // 5 minutes
    'thresholds' => [50, 80, 95], // % utilization
    'colors' => [
        'node_up' => '#28a745',
        'node_down' => '#dc3545',
        'link_normal' => '#28a745',
        'link_warning' => '#ffc107',
        'link_critical' => '#dc3545'
    ]
];
```

### Customization
Edit `/opt/librenms/html/plugins/WeathermapNG/config/weathermapng.php` to customize:

- Polling intervals
- Color schemes
- Threshold values
- RRD file locations
- API endpoints

## 🆘 Getting Help

### Quick Diagnosis
```bash
# Check installation
cd /opt/librenms/html/plugins/WeathermapNG
php verify.php

# View logs
tail -f /var/log/librenms/weathermapng_install.log
tail -f /var/log/librenms/weathermapng.log
```

### Common Issues

#### Maps Not Updating?
```bash
# Check cron job
crontab -l | grep weathermapng

# Manual poll
cd /opt/librenms/html/plugins/WeathermapNG
php bin/map-poller.php
```

#### Permission Errors?
```bash
# Fix ownership
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# Fix permissions
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output
```

#### Database Issues?
```bash
# Check tables exist
mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"

# Check LibreNMS database connection
cd /opt/librenms
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected\n';"
```

### Support Resources

- 📖 **Documentation**: [GitHub Wiki](https://github.com/lance0/weathermapNG/wiki)
- 🐛 **Bug Reports**: [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- 💬 **Community**: [LibreNMS Community](https://community.librenms.org)
- 📧 **Email**: info@librenms.org

### Debug Mode
Enable detailed logging by adding to `config/weathermapng.php`:
```php
'debug' => true,
'log_level' => 'debug',
'log_file' => '/var/log/librenms/weathermapng_debug.log'
```

## 🤝 Contributing

We welcome contributions! Here's how to get involved:

### Development Setup
```bash
# Clone repository
git clone https://github.com/lance0/weathermapNG.git
cd weathermapNG

# Install dependencies
composer install

# Run tests
composer test

# Run linting
composer lint
```

### Code Style
- Follow PSR-12 coding standards
- Use meaningful commit messages
- Add tests for new features
- Update documentation

### Pull Request Process
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📋 Changelog

### Version 1.0.0 (Latest)
- 🚀 **Automated Installation**: One-click setup with `./install.sh`
- 🌐 **Web Installer**: GUI-based installation wizard
- 🔧 **Smart Requirements Check**: Automatic system validation
- 📊 **Real-time Verification**: Post-installation verification script
- ⚡ **Performance Improvements**: Optimized database queries and caching
- 🎨 **Enhanced UI**: Modern responsive design with dark mode
- 🔒 **Security Hardening**: Improved authentication and file handling
- 📱 **Mobile Support**: Fully responsive mobile interface
- 🔗 **API Enhancements**: RESTful API for external integrations
- 📈 **Monitoring**: Comprehensive logging and error tracking

### Previous Versions
- **0.9.0**: Database-driven architecture, basic editor
- **0.8.0**: Initial LibreNMS integration
- **0.7.0**: Core functionality and RRD integration

## 📄 License

This project is licensed under the **Unlicense** - see the [LICENSE](LICENSE) file for details.

```
This is free and unencumbered software released into the public domain.

Anyone is free to copy, modify, publish, use, compile, sell, or
distribute this software, either in source code form or as a compiled
binary, for any purpose, commercial or non-commercial, and by any
means.
```

## 🙏 Acknowledgments

- **LibreNMS Community**: For the amazing monitoring platform
- **PHP Community**: For the robust language ecosystem
- **Open Source Community**: For making this possible

---

<div align="center">

**Made with ❤️ by the LibreNMS Community**

[⭐ Star us on GitHub](https://github.com/lance0/weathermapNG) • [🐛 Report Issues](https://github.com/lance0/weathermapNG/issues) • [📖 Documentation](https://github.com/lance0/weathermapNG/wiki)

</div>

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

#### Health Check
```
GET /plugins/weathermapng/health
```

#### System Statistics
```
GET /plugins/weathermapng/health/stats
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
WEATHERMAPNG_CACHE_TTL=300
WEATHERMAPNG_API_FALLBACK=true
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

## CI/CD Pipeline

This project uses GitHub Actions for continuous integration and deployment:

### Workflows

- **CI** (`ci.yml`): Runs tests on multiple PHP versions and databases
- **Quality** (`quality.yml`): Code quality checks and security scanning
- **Release** (`release.yml`): Automated release creation and asset publishing
- **Security** (`security.yml`): Security vulnerability scanning
- **Documentation** (`docs.yml`): Documentation validation and link checking

### Status Badges

[![CI](https://github.com/lance0/weathermapNG/actions/workflows/ci.yml/badge.svg)](https://github.com/lance0/weathermapNG/actions/workflows/ci.yml)
[![Code Quality](https://github.com/lance0/weathermapNG/actions/workflows/quality.yml/badge.svg)](https://github.com/lance0/weathermapNG/actions/workflows/quality.yml)
[![Security](https://github.com/lance0/weathermapNG/actions/workflows/security.yml/badge.svg)](https://github.com/lance0/weathermapNG/actions/workflows/security.yml)
[![Documentation](https://github.com/lance0/weathermapNG/actions/workflows/docs.yml/badge.svg)](https://github.com/lance0/weathermapNG/actions/workflows/docs.yml)

### Test Matrix

| PHP Version | Database | Status |
|-------------|----------|---------|
| 8.0 | SQLite | ✅ |
| 8.0 | MySQL | ✅ |
| 8.0 | PostgreSQL | ✅ |
| 8.1 | SQLite | ✅ |
| 8.1 | MySQL | ✅ |
| 8.1 | PostgreSQL | ✅ |
| 8.2 | SQLite | ✅ |
| 8.2 | MySQL | ✅ |
| 8.2 | PostgreSQL | ✅ |

### Automated Dependency Updates

This project uses Dependabot for automatic dependency updates:

- **Composer packages**: Updated weekly
- **GitHub Actions**: Updated weekly
- **Security updates**: Processed immediately

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
- **NEW**: Complete CI/CD pipeline with automated testing
- **NEW**: Multi-database support (MySQL, PostgreSQL, SQLite)
- **NEW**: Automated dependency management
- **NEW**: Security vulnerability scanning
- **NEW**: Code quality enforcement
- **NEW**: Automated release management