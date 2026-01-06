# WeathermapNG - Network Visualization for LibreNMS

A modern network weathermap plugin for LibreNMS v2 that provides real-time network topology visualization with traffic flow animations.

## ✨ Features

- **Real-time Visualization**: Live traffic data with animated flow indicators
- **Interactive Editor**: Professional map editor with device integration
- **Multiple Data Sources**: RRD files → LibreNMS API → SNMP fallback
- **Server-Sent Events**: Real-time updates without polling
- **Import/Export**: JSON format for backup and sharing maps
- **Embed Support**: Embed maps in dashboards with live updates

## 🚀 Installation (Choose Your Method)

### Method 1: One-Command Install (Recommended - 1 minute)

```bash
# Clone and install automatically
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG && ./quick-install.sh
```

**That's it!** The script automatically:
- ✅ Installs dependencies
- ✅ Sets up database tables
- ✅ Configures permissions
- ✅ Clears caches
- ✅ Enables the plugin
- ✅ Sets up background polling

### Method 2: Manual Install

For users who prefer manual control:

```bash
# 1. Clone and install
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG
composer install --no-dev

# 2. Setup database
php database/setup.php

# 3. Configure LibreNMS
cd /opt/librenms
php artisan cache:clear
php artisan view:clear
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# 4. Enable plugin
./lnms plugin:enable WeathermapNG
```

### Requirements
- LibreNMS (latest stable)
- PHP 8.2+
- Composer
- MySQL/MariaDB

**Visit**: `https://your-librenms/plugin/WeathermapNG`

## 🎯 Getting Started

### Create Your First Map

1. **Access the Plugin**: Visit `https://your-librenms/plugin/WeathermapNG`
2. **Create Map**: Click **"Create New Map"**
3. **Configure**: Enter name, title, and dimensions
4. **Design**: Use the canvas editor to add devices and connections
5. **Save**: Your map is now live with real-time traffic data!

### Quick Tips
- **Add Devices**: Use the device dropdown to populate your map automatically
- **Manual Layout**: Drag nodes to position them perfectly
- **Live Preview**: See traffic updates in real-time
- **Export**: Save maps as JSON for backup/sharing

## 🔧 Troubleshooting

### Plugin Not Showing
```bash
# Clear LibreNMS caches
cd /opt/librenms
php artisan cache:clear
php artisan view:clear
```

### Permission Errors
```bash
# Fix ownership
sudo chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

### Database Issues
```bash
# Run setup again
cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php
```

### Maps Not Updating
- Check cron job: `crontab -u librenms -l | grep weathermap`
- Verify poller: `ps aux | grep map-poller`

## 🔄 Updating

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev
cd /opt/librenms
php artisan cache:clear
php artisan view:clear
```

## 🔌 Embedding Maps

```html
<iframe src="https://your-librenms/plugin/WeathermapNG/embed/1"
        width="800" height="600" frameborder="0">
</iframe>
```

## 📚 Documentation

- **[Detailed Installation Guide](INSTALL.md)** - Advanced setup and troubleshooting
- **[API Documentation](API.md)** - REST API reference
- **[Embed Viewer Guide](docs/EMBED.md)** - Embedding maps and metrics
- **[Configuration Reference](config/weathermapng.php)** - All settings explained

## 🤝 Contributing

Pull requests welcome! Please follow PSR-12 coding standards and include tests.

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- **LibreNMS Community**: [community.librenms.org](https://community.librenms.org)

## 📝 License

Unlicense - Public Domain
