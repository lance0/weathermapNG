# WeathermapNG - Network Visualization for LibreNMS

A modern network weathermap plugin for LibreNMS v2 that provides real-time network topology visualization with traffic flow animations.

## ✨ Features

- **Real-time Visualization**: Live traffic data with animated flow indicators
- **Dual Editors**: Simple canvas editor + advanced D3.js editor
- **Multiple Data Sources**: RRD files → LibreNMS API → SNMP fallback
- **Server-Sent Events**: Real-time updates without polling
- **Professional Tools**: Templates, keyboard shortcuts, undo/redo
- **Export/Import**: JSON format for sharing maps

## 🚀 Quick Installation (2 minutes)

### Requirements
- LibreNMS (latest stable)
- PHP 8.2+
- Composer

### Install Steps

```bash
# 1. Clone the plugin
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG

# 2. Install dependencies
cd WeathermapNG
composer install --no-dev

# 3. Clear LibreNMS caches
cd /opt/librenms
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 4. Set permissions
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

**That's it!** Visit `https://your-librenms/plugin/WeathermapNG`

## 📝 Creating Your First Map

1. Click **"Create New Map"**
2. Enter a name and title
3. Use the **D3.js Editor** for advanced features or **Simple Editor** for basic maps
4. Add devices from the dropdown
5. Connect nodes to create links
6. Save - your map now shows live traffic!

### D3 Editor Guide
- See docs/EDITOR_D3.md for routes, features, data mapping, and tips for the advanced D3 editor.

## 🔧 Architecture

WeathermapNG follows LibreNMS v2 plugin architecture:

```
WeathermapNG/
├── src/
│   ├── WeathermapNGProvider.php    # Service provider
│   ├── Hooks/                       # LibreNMS hooks
│   │   ├── MenuEntry.php           # Menu integration
│   │   └── Settings.php            # Settings page
│   ├── Http/Controllers/           # Web controllers
│   ├── Models/                     # Database models
│   └── Services/                   # Business logic
├── resources/views/                # Blade templates
├── routes/web.php                  # Route definitions
├── database/migrations/            # Database schema
└── composer.json                   # Package definition
```

## 🎯 Key Components

### Data Collection
- **PortUtilService**: Fetches bandwidth data from RRD/API/SNMP
- **Background Poller**: Pre-processes data for performance
- **Caching**: Redis/file-based with configurable TTL

### Visualization
- **Canvas Renderer**: HTML5 canvas with animations
- **D3.js Editor**: SVG-based with professional tools
- **Live Updates**: SSE for real-time, polling fallback

### Integration
- Uses LibreNMS authentication
- Integrates with device/port database
- Follows LibreNMS Bootstrap 4 UI patterns

## 🐛 Troubleshooting

### View/Page Not Found Errors
```bash
cd /opt/librenms
php artisan view:clear
php artisan cache:clear
```

### Maps Not Updating
Check the poller is running:
```bash
php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

Add to cron for automatic updates:
```bash
*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

### Permission Issues
```bash
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
```

## 📊 API Endpoints

| Endpoint | Method | Description |
|----------|---------|------------|
| `/plugin/WeathermapNG` | GET | Main interface |
| `/plugin/WeathermapNG/map` | POST | Create map |
| `/plugin/WeathermapNG/embed/{id}` | GET | Embed view |
| `/plugin/WeathermapNG/api/maps/{id}/json` | GET | Map data |
| `/plugin/WeathermapNG/api/maps/{id}/live` | GET | Live data |
| `/plugin/WeathermapNG/api/maps/{id}/sse` | GET | SSE stream |

## 🔌 Embedding Maps

```html
<iframe src="https://your-librenms/plugin/WeathermapNG/embed/1" 
        width="800" height="600" frameborder="0">
</iframe>
```

## ⚙️ Configuration

Edit `config/weathermapng.php`:

```php
return [
    'poll_interval' => 300,          // Update interval (seconds)
    'thresholds' => [50, 80, 95],    // Utilization thresholds (%)
    'colors' => [
        'link_normal' => '#28a745',   // Green
        'link_warning' => '#ffc107',  // Yellow  
        'link_critical' => '#dc3545'  // Red
    ]
];
```

## 📝 License

Unlicense - Public Domain

## 🤝 Contributing

Pull requests welcome! Please follow PSR-12 coding standards.

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- **LibreNMS Community**: [community.librenms.org](https://community.librenms.org)
