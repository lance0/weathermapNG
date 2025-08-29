# WeathermapNG FAQ

Frequently asked questions about WeathermapNG plugin for LibreNMS.

## Table of Contents

- [General Questions](#general-questions)
- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [Usage](#usage)
- [Troubleshooting](#troubleshooting)
- [Performance](#performance)
- [Security](#security)
- [Development](#development)

## General Questions

### What is WeathermapNG?

WeathermapNG is a modern, interactive network topology visualization plugin for LibreNMS. It provides real-time network maps showing device connectivity, link utilization, and network health status.

### How does it differ from the original Weathermap plugin?

WeathermapNG is a complete rewrite with:
- **Database-driven architecture** instead of file-based storage
- **Modern PHP 8+** with Laravel-ish MVC structure
- **Real-time data** with automatic updates
- **Interactive editor** with drag-and-drop interface
- **RESTful API** for integrations
- **Better security** and performance

### Is it compatible with LibreNMS?

Yes, WeathermapNG is designed specifically for LibreNMS and integrates seamlessly with its device database, RRD files, and authentication system.

### What are the system requirements?

- LibreNMS (latest stable recommended)
- PHP 8.0 or higher
- MySQL 5.7+ or PostgreSQL 9.5+
- GD extension for image generation
- 100MB disk space minimum

## Installation & Setup

### How do I install WeathermapNG?

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
composer install
cd /opt/librenms
php artisan migrate
```

See [README.md](README.md) for complete installation instructions.

### The plugin doesn't appear in LibreNMS. What should I do?

1. Check if the plugin files are in the correct location
2. Verify composer dependencies are installed
3. Check PHP error logs for any loading issues
4. Ensure proper file permissions
5. Restart your web server
6. Check LibreNMS logs for plugin loading errors

### How do I enable the plugin?

1. Log into LibreNMS web interface
2. Navigate to **Overview → Plugins → Plugin Admin**
3. Find **WeathermapNG** in the list
4. Click **Enable**

### Do I need to run database migrations?

Yes, the plugin requires database tables to store map data. Run:

```bash
cd /opt/librenms
php artisan migrate
```

### How do I set up the poller?

Add this to your crontab:

```bash
*/5 * * * * librenms /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

## Configuration

### How do I configure the plugin?

Edit `/opt/librenms/html/plugins/WeathermapNG/config/weathermapng.php`:

```php
return [
    'poll_interval' => 300,         // Poller interval in seconds
    'rrd_base' => '/opt/librenms/rrd',  // RRD file location
    'enable_local_rrd' => true,    // Use local RRD files
    'enable_api_fallback' => true, // Fallback to API
    'cache_ttl' => 300,           // Cache TTL in seconds
];
```

### Can I use API tokens instead of local RRD files?

Yes, set `enable_local_rrd => false` and configure your API token:

```bash
# In your .env file
LIBRENMS_API_TOKEN=your_api_token_here
```

### How do I change the map update interval?

Modify the `poll_interval` in the configuration file. The default is 300 seconds (5 minutes).

### Can I customize the colors and styling?

Yes, edit the `colors` section in `config/weathermapng.php`:

```php
'colors' => [
    'node_up' => '#28a745',      // Green for up devices
    'node_down' => '#dc3545',    // Red for down devices
    'link_normal' => '#28a745',  // Green for normal links
    'link_warning' => '#ffc107', // Yellow for warning
    'link_critical' => '#dc3545', // Red for critical
],
```

## Usage

### How do I create my first map?

1. Navigate to `/plugins/weathermapng`
2. Click "Create New Map"
3. Fill in the map details (name, title, dimensions)
4. Click "Create"
5. Use the editor to add devices and links

### How do I add devices to a map?

1. Open the map editor
2. Use the device search to find devices
3. Drag devices onto the canvas
4. Position them as desired
5. Save the map

### How do I create links between devices?

1. In the map editor, devices are automatically connected if they're on the same canvas
2. For manual links, use the link tool in the editor
3. Configure bandwidth and styling options
4. Save the map

### How do I embed maps in other systems?

Use the embed URL: `/plugins/weathermapng/embed/{mapId}`

```html
<iframe src="https://your-librenms/plugins/weathermapng/embed/1"
        width="800" height="600" frameborder="0">
</iframe>
```

### Can I export and import maps?

Yes:
- **Export**: Visit `/plugins/weathermapng/api/maps/{id}/export?format=json`
- **Import**: Use the API endpoint or web interface

### How do I monitor map performance?

Check the health endpoint: `/plugins/weathermapng/health`

## Troubleshooting

### Maps are not updating. What should I check?

1. **Poller Status**: Check if the cron job is running
   ```bash
   ps aux | grep map-poller.php
   ```

2. **Log Files**: Check for errors
   ```bash
   tail -f /var/log/librenms/weathermapng.log
   ```

3. **Permissions**: Ensure poller has execute permissions
   ```bash
   ls -la /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
   ```

4. **RRD Access**: Verify RRD file permissions
   ```bash
   ls -la /opt/librenms/rrd/
   ```

### I'm getting database errors. What should I do?

1. **Check Database Connection**:
   ```bash
   mysql -u librenms -p librenms -e "SELECT 1;"
   ```

2. **Run Migrations**:
   ```bash
   cd /opt/librenms
   php artisan migrate:status
   php artisan migrate
   ```

3. **Check Table Creation**:
   ```bash
   mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"
   ```

### RRD files are not being read. What should I do?

1. **Check RRD Path**: Verify the path in configuration
2. **File Permissions**: Ensure LibreNMS user can read RRD files
3. **RRD Tool**: Test RRD access manually
   ```bash
   rrdtool info /opt/librenms/rrd/device1/port1.rrd
   ```

4. **API Fallback**: Enable API fallback in configuration

### The web interface is slow. How can I improve performance?

1. **Enable Caching**: Configure Redis or file caching
2. **Optimize Database**: Add indexes if needed
3. **Reduce Poll Interval**: Increase the polling interval
4. **Check Server Resources**: Monitor CPU, memory, and disk I/O

### I'm getting permission errors. What should I do?

```bash
# Fix ownership
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# Fix permissions
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output
chmod +x /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

## Performance

### What's the performance impact on LibreNMS?

- **Minimal CPU Impact**: Poller runs every 5 minutes
- **Low Memory Usage**: < 50MB for typical networks
- **Database Load**: Lightweight queries with caching
- **Web Performance**: Fast API responses with caching

### How many maps/devices can it handle?

**Performance Benchmarks**:
- **Small Network** (10 devices, 20 links): < 5 seconds poll time
- **Medium Network** (50 devices, 100 links): < 15 seconds poll time
- **Large Network** (200 devices, 500 links): < 60 seconds poll time

### Can I run multiple pollers?

Yes, but coordinate them to avoid conflicts. Use different log files and ensure proper locking.

### How do I monitor plugin performance?

Use the health endpoints:
- `/plugins/weathermapng/health` - Overall health status
- `/plugins/weathermapng/health/stats` - Detailed statistics

## Security

### Is the plugin secure?

Yes, with multiple security measures:
- **Authentication**: All routes require LibreNMS authentication
- **Authorization**: Policy-based access control
- **Input Validation**: All inputs are validated and sanitized
- **File Security**: Sensitive files are protected with .htaccess
- **RRD Security**: Read-only access to RRD files

### Can I restrict access to specific users?

Yes, you can modify the policies in `Policies/MapPolicy.php` to implement custom authorization logic.

### How are API tokens handled?

API tokens are stored securely and used only for LibreNMS API communication. They are not exposed in client-side code.

### Can I disable embedding?

Yes, set `'allow_embed' => false` in the configuration.

## Development

### How do I contribute to the project?

1. Fork the repository on GitHub
2. Create a feature branch
3. Make your changes following the coding standards
4. Add tests for new functionality
5. Submit a pull request

See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed guidelines.

### How do I run the tests?

```bash
cd /opt/librenms/html/plugins/WeathermapNG
composer test
```

### How do I add new features?

1. **Plan**: Create an issue describing the feature
2. **Design**: Consider the database schema changes needed
3. **Implement**: Follow the existing code patterns
4. **Test**: Add comprehensive tests
5. **Document**: Update documentation

### Can I customize the appearance?

Yes, modify the CSS files and configuration options:
- Edit `Resources/css/weathermapng.css` for styling
- Modify color schemes in `config/weathermapng.php`
- Customize templates in `Resources/views/`

### How do I debug issues?

Enable debug mode in configuration:

```php
'debug' => true,
'log_level' => 'debug',
'log_file' => '/var/log/librenms/weathermapng-debug.log',
```

Check logs:
```bash
tail -f /var/log/librenms/weathermapng.log
tail -f /var/log/librenms/weathermapng-debug.log
```

## Support

### Where can I get help?

- **GitHub Issues**: [Report bugs and request features](https://github.com/lance0/weathermapNG/issues)
- **LibreNMS Community**: [Get help from the community](https://community.librenms.org/)
- **Documentation**: [Read the full documentation](README.md)

### How do I report a bug?

1. Check existing issues on GitHub
2. Gather system information (LibreNMS version, PHP version, etc.)
3. Include steps to reproduce the issue
4. Attach relevant log files
5. Create a new issue with detailed information

### What's the best way to ask for help?

1. **Check Documentation**: Review README.md and FAQ first
2. **Search Issues**: Look for similar problems on GitHub
3. **Provide Context**: Include your environment details
4. **Be Specific**: Describe exactly what you're trying to do and what's not working
5. **Include Logs**: Attach relevant log entries

## Version Information

- **Current Version**: 1.0.0
- **Release Date**: January 29, 2025
- **Compatibility**: LibreNMS 21.0+, PHP 8.0+, MySQL 5.7+/PostgreSQL 9.5+

---

*This FAQ is regularly updated. Last updated: January 29, 2025*