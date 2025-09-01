# WeathermapNG Quick Start Guide

## 🚀 Quick Deployment (One Command)

```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
cd WeathermapNG && ./deploy.sh
```

That's it! The deployment script handles everything automatically.

## 📋 What the Deployment Script Does

1. **Pulls latest code** from git
2. **Installs dependencies** via composer
3. **Runs database migrations** to create tables
4. **Sets permissions** for LibreNMS user
5. **Clears caches** for fresh start
6. **Registers plugin hooks** with LibreNMS v2
7. **Sets up cron job** for map updates
8. **Verifies installation** was successful

## ✅ Post-Deployment Verification

Run the verification script to check everything is working:

```bash
php verify-deployment.php
```

## 🌐 Access the Plugin

1. **Visit**: `http://your-server/plugin/WeathermapNG`
2. **Check Menu**: Look for "Network Maps" in the LibreNMS menu
3. **Create Map**: Click "Create New Map" to get started

## 🔧 Manual Deployment (if needed)

If the automatic deployment doesn't work, here are the manual steps:

### 1. Clone the Repository
```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git WeathermapNG
```

### 2. Install Dependencies
```bash
cd WeathermapNG
composer install --no-dev --optimize-autoloader
```

### 3. Run Migrations
```bash
cd /opt/librenms
php artisan migrate --path=html/plugins/WeathermapNG/database/migrations
```

### 4. Fix Permissions
```bash
chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
chmod 755 /opt/librenms/html/plugins/WeathermapNG/output
```

### 5. Enable Plugin
```bash
./lnms plugin:enable WeathermapNG
```

### 6. Add Cron Job
```bash
echo "*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php" | sudo tee -a /etc/cron.d/librenms
```

### 7. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

## 🐛 Troubleshooting

### Plugin Not Showing in Menu
- Check permissions: `ls -la /opt/librenms/html/plugins/WeathermapNG`
- Clear cache: `php artisan cache:clear`
- Check logs: `tail -f /opt/librenms/logs/librenms.log`

### Database Tables Missing
```bash
cd /opt/librenms
php artisan migrate --path=html/plugins/WeathermapNG/database/migrations --force
```

### Maps Not Updating
- Check cron is running: `ps aux | grep map-poller`
- Check cron logs: `tail -f /tmp/weathermapng.log`
- Verify cron entry: `crontab -u librenms -l`

### Permission Errors
```bash
sudo chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
sudo chmod -R 755 /opt/librenms/html/plugins/WeathermapNG/output
```

## 📊 Features Available

After deployment, you can:

- ✅ Create interactive network maps
- ✅ Drag-and-drop node positioning
- ✅ Real-time traffic visualization
- ✅ Device and port integration
- ✅ Auto-refresh with live data
- ✅ Export/Import map configurations
- ✅ Embed maps in dashboards

## 🔄 Updating

To update to the latest version:

```bash
cd /opt/librenms/html/plugins/WeathermapNG
git pull
composer install --no-dev
php /opt/librenms/artisan migrate --path=html/plugins/WeathermapNG/database/migrations
php /opt/librenms/artisan cache:clear
```

## 💡 Tips

- **First Map**: Start with a simple topology to test
- **Performance**: Keep maps under 100 nodes for best performance
- **Refresh Rate**: Default is 5 minutes, adjustable in config
- **Permissions**: Only authenticated LibreNMS users can access

## 📚 More Information

- [Full Documentation](README.md)
- [Configuration Guide](config/weathermapng.php)
- [API Documentation](API.md)
- [GitHub Issues](https://github.com/lance0/weathermapNG/issues)