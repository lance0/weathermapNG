# WeathermapNG FAQ

## Installation

### How do I install WeathermapNG?

**Quick install:**
```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
./install.sh
```

### The plugin doesn't show up?

1. Check file permissions: `ls -la /opt/librenms/html/plugins/WeathermapNG`
2. Check logs: `tail -f /var/log/librenms/weathermapng.log`
3. Verify installation: `php verify.php`
4. Enable in LibreNMS: Overview → Plugins → Plugin Admin

### Do I need to configure anything?

Basic installation works out of the box. For production:
- Copy `.env.example` to `.env` for custom settings
- Edit `config/weathermapng.php` for advanced options

## Usage

### How do I create a map?

1. Go to `/plugins/weathermapng`
2. Click "Create New Map"
3. Add devices and links
4. Save

### How often does data update?

Default is every 5 minutes (configured in cron).

### Can I embed maps?

Yes, use the embed URL:
```html
<iframe src="/plugins/weathermapng/embed/MAP_ID"></iframe>
```

## Troubleshooting

### Maps not updating?

Check if poller is running:
```bash
ps aux | grep map-poller
crontab -l | grep weathermapng
```

### Permission errors?

Fix ownership:
```bash
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

### Database errors?

Check tables exist:
```bash
mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"
```

## Docker

### How do I install in Docker?

Use the provided docker-compose:
```bash
cp docker-compose.example.yml docker-compose.yml
docker-compose up -d
```

### Where are the logs in Docker?

Container logs go to stdout. View with:
```bash
docker-compose logs weathermap-poller
```

## API

### How do I access the API?

Use LibreNMS authentication:
```bash
curl -H "X-Auth-Token: YOUR_TOKEN" \
     https://librenms/plugins/weathermapng/api/maps
```

### Is there a health check?

Yes: `/plugins/weathermapng/health`

## Support

### Where can I get help?

- GitHub Issues: https://github.com/lance0/weathermapNG/issues
- LibreNMS Community: https://community.librenms.org

### What PHP version is required?

PHP 8.0 or higher.

### Does it work with PostgreSQL?

Yes, both MySQL and PostgreSQL are supported.