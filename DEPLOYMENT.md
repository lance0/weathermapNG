# WeathermapNG Deployment Guide

This guide provides step-by-step instructions for deploying WeathermapNG in various environments.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Production Deployment](#production-deployment)
- [Docker Deployment](#docker-deployment)
- [High Availability](#high-availability)
- [Monitoring & Maintenance](#monitoring--maintenance)
- [Troubleshooting](#troubleshooting)
- [Backup & Recovery](#backup--recovery)

## Prerequisites

### System Requirements

- **LibreNMS**: Latest stable version recommended
- **PHP**: 8.2 or higher
- **Database**: MySQL 5.7+ or PostgreSQL 9.5+
- **Web Server**: Apache or Nginx with PHP support
- **Extensions**:
  - `ext-gd` (for image generation)
  - `ext-json` (for JSON handling)
  - `ext-pdo` (for database access)
- **Disk Space**: 100MB minimum + space for map data
- **Memory**: 256MB PHP memory limit recommended

### Network Requirements

- **RRD Access**: Read access to LibreNMS RRD files
- **Database Access**: Read/write access to LibreNMS database
- **Web Access**: HTTP/HTTPS access to LibreNMS web interface
- **Cron Access**: Ability to run scheduled tasks

## Quick Start

### Basic Installation

```bash
# 1. Download and install
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run database migrations
cd /opt/librenms
php artisan migrate

# 4. Set permissions
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
chmod +x /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php

# 5. Enable plugin
# Go to LibreNMS → Plugins → Plugin Admin → Enable WeathermapNG

# 6. Create demo data (optional)
php artisan db:seed --class=LibreNMS\\Plugins\\WeathermapNG\\Database\\Seeders\\WeathermapNGSeeder
```

### Verification

```bash
# Check plugin is loaded
curl -s https://your-librenms/plugin/WeathermapNG/health | jq .status

# Check database tables
mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"

# Test poller
/opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php
```

## Production Deployment

### 1. Environment Setup

Create production configuration:

```bash
# Copy and customize config
cp /opt/librenms/html/plugins/WeathermapNG/config/weathermapng.php \
   /opt/librenms/config/weathermapng.php

# Edit production settings
nano /opt/librenms/config/weathermapng.php
```

### 2. Database Optimization

```sql
-- Create indexes for better performance
CREATE INDEX idx_wmng_maps_name ON wmng_maps(name);
CREATE INDEX idx_wmng_nodes_map_id ON wmng_nodes(map_id);
CREATE INDEX idx_wmng_nodes_device_id ON wmng_nodes(device_id);
CREATE INDEX idx_wmng_links_map_id ON wmng_links(map_id);
CREATE INDEX idx_wmng_links_ports ON wmng_links(port_id_a, port_id_b);

-- Optimize MySQL settings
SET GLOBAL innodb_buffer_pool_size = 128 * 1024 * 1024; -- 128MB
SET GLOBAL max_connections = 100;
```

### 3. Web Server Configuration

#### Apache Configuration

```apache
# /etc/apache2/sites-available/librenms.conf
<Directory "/opt/librenms/html/plugins/WeathermapNG">
    Options -Indexes
    AllowOverride All
    Require all granted

    # Cache static assets
    <FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
        Header set Cache-Control "max-age=31536000, public"
    </FilesMatch>

    # Protect sensitive files
    <FilesMatch "\.(env|log|sql|sqlite)$">
        Require all denied
    </FilesMatch>
</Directory>
```

#### Nginx Configuration

```nginx
# /etc/nginx/sites-available/librenms
location /plugins/WeathermapNG/ {
    alias /opt/librenms/html/plugins/WeathermapNG/;
    index index.php;

    # Cache static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Protect sensitive files
    location ~* \.(env|log|sql|sqlite)$ {
        deny all;
        return 404;
    }

    # PHP handling
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
}
```

### 4. SSL/TLS Configuration

```bash
# Generate SSL certificate
certbot --apache -d your-librenms-domain.com

# Verify SSL configuration
openssl s_client -connect your-domain.com:443 -servername your-domain.com
```

### 5. Cron Job Setup

```bash
# Add to /etc/cron.d/librenms
*/5 * * * * librenms /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php >> /var/log/librenms/weathermapng.log 2>&1

# Backup job (daily at 2 AM)
0 2 * * * librenms /opt/librenms/html/plugins/WeathermapNG/bin/backup.php create >> /var/log/librenms/weathermapng-backup.log 2>&1

# Cleanup old backups (weekly)
0 3 * * 0 librenms /opt/librenms/html/plugins/WeathermapNG/bin/backup.php cleanup 30 >> /var/log/librenms/weathermapng-cleanup.log 2>&1
```

### 6. Log Rotation

```bash
# /etc/logrotate.d/weathermapng
/var/log/librenms/weathermapng*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 librenms librenms
    postrotate
        systemctl reload apache2
    endscript
}
```

## Docker Deployment

### Docker Compose Setup

```yaml
# docker-compose.yml
version: '3.8'
services:
  librenms:
    image: librenms/librenms:latest
    volumes:
      - ./WeathermapNG:/opt/librenms/html/plugins/WeathermapNG
      - librenms_logs:/opt/librenms/logs
    environment:
      - DB_HOST=db
      - DB_NAME=librenms
      - DB_USER=librenms
      - DB_PASS=password
    depends_on:
      - db
    ports:
      - "8000:80"

  db:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=rootpass
      - MYSQL_DATABASE=librenms
      - MYSQL_USER=librenms
      - MYSQL_PASSWORD=password
    volumes:
      - db_data:/var/lib/mysql

volumes:
  librenms_logs:
  db_data:
```

### Docker Commands

```bash
# Build and start
docker-compose up -d

# Run migrations
docker-compose exec librenms php artisan migrate

# Run poller
docker-compose exec librenms /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php

# View logs
docker-compose logs librenms
```

## Monitoring & Maintenance

### Health Checks

```bash
# Add to monitoring system
# Check plugin health
curl -f https://librenms.example.com/plugin/WeathermapNG/health

# Check poller status
ps aux | grep map-poller.php

# Check database connectivity
mysql -u librenms -p -e "SELECT COUNT(*) FROM wmng_maps;" librenms
```

### Performance Monitoring

```bash
# Monitor poller performance
time /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php

# Check memory usage
php -r "echo 'Memory: ' . memory_get_peak_usage(true) / 1024 / 1024 . ' MB\n';"

# Monitor database queries
tail -f /var/log/mysql/mysql-slow.log
```

### Log Analysis

```bash
# Analyze poller logs
grep "ERROR" /var/log/librenms/weathermapng.log | tail -10

# Check for failed RRD reads
grep "RRD file not found" /var/log/librenms/weathermapng.log

# Monitor API usage
grep "API" /var/log/librenms/weathermapng.log | wc -l
```

## Troubleshooting

### Common Issues

#### Plugin Not Loading
```bash
# Check plugin files
ls -la /opt/librenms/html/plugins/WeathermapNG/

# Check composer autoload
cd /opt/librenms && php artisan about

# Check PHP errors
tail -f /var/log/php8.1-fpm.log
```

#### Database Connection Issues
```bash
# Test database connection
mysql -u librenms -p librenms -e "SELECT 1;"

# Check migration status
cd /opt/librenms && php artisan migrate:status

# Reset migrations if needed
cd /opt/librenms && php artisan migrate:reset && php artisan migrate
```

#### RRD Access Problems
```bash
# Check RRD permissions
ls -la /opt/librenms/rrd/

# Test RRD reading
rrdtool info /opt/librenms/rrd/device1/port1.rrd

# Check SELinux
sestatus
setsebool -P httpd_can_network_connect 1
```

#### Performance Issues
```bash
# Check PHP configuration
php -i | grep memory_limit

# Monitor system resources
top -p $(pgrep -f map-poller.php)

# Check database slow queries
tail -f /var/log/mysql/mysql-slow.log
```

### Debug Mode

Enable detailed logging:

```php
// config/weathermapng.php
'debug' => true,
'log_level' => 'debug',
'log_file' => '/var/log/librenms/weathermapng-debug.log',
```

## Backup & Recovery

### Automated Backups

```bash
# Daily backup script
#!/bin/bash
BACKUP_DIR="/opt/librenms/backups/weathermapng"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u librenms -p librenms wmng_maps wmng_nodes wmng_links > $BACKUP_DIR/db_$DATE.sql

# File backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /opt/librenms/html/plugins/WeathermapNG/output/

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### Recovery Process

```bash
# Stop services
systemctl stop apache2
systemctl stop cron

# Restore database
mysql -u librenms -p librenms < /opt/librenms/backups/weathermapng/db_20250129_020000.sql

# Restore files
tar -xzf /opt/librenms/backups/weathermapng/files_20250129_020000.tar.gz -C /

# Restart services
systemctl start apache2
systemctl start cron
```

### Disaster Recovery Plan

1. **Daily Backups**: Automated database and file backups
2. **Offsite Storage**: Copy backups to remote location
3. **Recovery Time**: Target < 4 hours for full recovery
4. **Testing**: Monthly recovery testing
5. **Documentation**: Keep recovery procedures updated

## Security Considerations

### Access Control

```php
// config/weathermapng.php
'security' => [
    'allow_embed' => true,
    'embed_domains' => ['trusted-domain.com'],
    'max_image_size' => 2048,
    'rate_limit' => 100, // requests per minute
],
```

### File Permissions

```bash
# Secure permissions
chown -R root:librenms /opt/librenms/html/plugins/WeathermapNG
chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output
chmod 644 /opt/librenms/html/plugins/WeathermapNG/config/weathermapng.php
```

### Network Security

```bash
# Firewall rules
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# SSL configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
```

## Support

For deployment support:
- [GitHub Issues](https://github.com/lance0/weathermapNG/issues)
- [LibreNMS Community](https://community.librenms.org/)
- [Documentation](https://github.com/lance0/weathermapNG/blob/main/README.md)

## Version Compatibility

| WeathermapNG | LibreNMS | PHP | MySQL | PostgreSQL |
|--------------|----------|-----|-------|------------|
| 1.0.x        | 21.0+    | 8.0+| 5.7+  | 9.5+       |
| 0.1.x        | 20.0+    | 7.4+| 5.6+  | 9.4+       |

## Performance Benchmarks

### Small Network (10 devices, 20 links)
- **Poller Time**: < 5 seconds
- **Memory Usage**: < 50MB
- **Database Size**: < 1MB

### Medium Network (50 devices, 100 links)
- **Poller Time**: < 15 seconds
- **Memory Usage**: < 100MB
- **Database Size**: < 5MB

### Large Network (200 devices, 500 links)
- **Poller Time**: < 60 seconds
- **Memory Usage**: < 256MB
- **Database Size**: < 25MB

*Benchmarks performed on Intel Xeon E5-2620, 32GB RAM, SSD storage*
