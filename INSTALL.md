# WeathermapNG Installation Guide

## Quick Install (2 minutes)

### Host-Based Installation

```bash
# 1. Clone the plugin
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG

# 2. Run installer
./install.sh

# 3. Done! Access at:
# https://your-librenms/plugins/weathermapng
```

### Docker Installation

```bash
# 1. Use docker-compose
cp docker-compose.example.yml docker-compose.yml
docker-compose up -d

# Plugin will auto-install on container start
```

## Manual Installation (if automated fails)

### Prerequisites
- LibreNMS (latest stable)
- PHP 8.0+
- Composer
- MySQL/PostgreSQL

### Steps

1. **Install Dependencies**
```bash
cd /opt/librenms/html/plugins/WeathermapNG
composer install --no-dev
```

2. **Set Permissions**
```bash
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
chmod 755 /opt/librenms/html/plugins/WeathermapNG
chmod 775 /opt/librenms/html/plugins/WeathermapNG/output
```

3. **Configure Database** (optional)
```bash
cp .env.example .env
# Edit .env with your database credentials
```

4. **Add Cron Job**
```bash
echo '*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php' >> /etc/cron.d/librenms
```

5. **Enable Plugin**
- Go to LibreNMS web interface
- Navigate to: Overview → Plugins → Plugin Admin
- Enable WeathermapNG

## Verification

```bash
# Check installation
php verify.php

# Test health endpoint
curl https://your-librenms/plugins/weathermapng/health
```

## Troubleshooting

### Plugin Not Showing?
```bash
# Check permissions
ls -la /opt/librenms/html/plugins/WeathermapNG

# Check logs
tail -f /var/log/librenms/weathermapng.log
```

### Database Issues?
```bash
# Check tables exist
mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"
```

### Permission Errors?
```bash
# Fix ownership
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
```

## Support

- GitHub Issues: https://github.com/lance0/weathermapNG/issues
- Documentation: See README.md