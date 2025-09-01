# WeathermapNG Installation Guide

## ⚠️ Important: LibreNMS 24.x Compatibility

WeathermapNG has been updated to use LibreNMS's hook-based plugin architecture. If you're upgrading from an older version:
1. Remove any old `WeathermapNG.php` file that implements non-existent interfaces
2. Ensure you're running LibreNMS 24.1.0 or later
3. The plugin now uses hooks in `app/Plugins/WeathermapNG/` directory

## 🚀 Quick Start (1 minute!)

### One-Line Installation
```bash
curl -sL https://raw.githubusercontent.com/lance0/weathermapNG/main/install.sh | bash
```

Or if you prefer to review first:
```bash
wget https://raw.githubusercontent.com/lance0/weathermapNG/main/install.sh
./install.sh        # Express mode (fully automatic)
./install.sh custom # Interactive mode with prompts
```

### Docker Quick Start
```bash
# Using our simplified Docker setup
curl -sL https://raw.githubusercontent.com/lance0/weathermapNG/main/docker-compose.simple.yml -o docker-compose.yml
curl -sL https://raw.githubusercontent.com/lance0/weathermapNG/main/.env.docker -o .env
docker-compose up -d
```

## Installation Methods

### Method 1: Express Installation (Recommended)
```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
./install.sh  # Runs in express mode by default
```

**What happens automatically:**
- ✅ Checks all prerequisites
- ✅ Installs dependencies
- ✅ Sets up database
- ✅ Configures permissions
- ✅ Adds cron job
- ✅ Enables plugin via LibreNMS CLI

### Method 2: Custom Installation
```bash
cd /opt/librenms/html/plugins
git clone https://github.com/lance0/weathermapNG.git
cd WeathermapNG
./install.sh custom  # Interactive prompts
```

### Method 3: Docker Installation
```bash
# Copy example files
cp docker-compose.simple.yml docker-compose.yml
cp .env.docker .env

# Edit .env with your settings (especially passwords!)
nano .env

# Start services
docker-compose up -d

# View logs
docker-compose logs -f
```

### Method 4: Manual Installation
Only if automated methods fail:

1. **Install Dependencies**
```bash
cd /opt/librenms/html/plugins/WeathermapNG
composer install --no-dev
```

2. **Set Permissions**
```bash
sudo chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG
chmod 755 /opt/librenms/html/plugins/WeathermapNG
chmod 775 /opt/librenms/html/plugins/WeathermapNG/output
```

3. **Add Cron Job**
```bash
echo '*/5 * * * * librenms php /opt/librenms/html/plugins/WeathermapNG/bin/map-poller.php' | sudo tee -a /etc/cron.d/librenms
```

4. **Enable Plugin**
```bash
# Via CLI (recommended)
cd /opt/librenms
./lnms plugin:enable WeathermapNG

# Or via Web UI
# Navigate to: Overview → Plugins → Plugin Admin
# Click Enable for WeathermapNG
```

## Verification & Troubleshooting

### Verify Installation
```bash
cd /opt/librenms/html/plugins/WeathermapNG
php verify.php         # Check installation
php verify.php --fix   # Auto-fix issues
```

### Common Issues & Solutions

#### Prerequisites Missing?
```bash
# Debian/Ubuntu (PHP 8.3)
sudo apt-get install php8.3-gd php8.3-mbstring php8.3-mysql composer git

# Debian/Ubuntu (Default PHP)
sudo apt-get install php-gd php-mbstring php-mysql composer git

# RHEL/CentOS
sudo yum install php-gd php-mbstring php-mysqlnd composer git
```

#### Permission Errors?
```bash
# Fix ownership
sudo chown -R librenms:librenms /opt/librenms/html/plugins/WeathermapNG

# Fix permissions
sudo chmod -R 755 /opt/librenms/html/plugins/WeathermapNG
sudo chmod -R 775 /opt/librenms/html/plugins/WeathermapNG/output
```

#### Plugin Not Showing?
1. Check if enabled in Plugin Admin
2. Clear LibreNMS cache: `cd /opt/librenms && php artisan cache:clear`
3. Check logs: `tail -f /var/log/librenms/weathermapng.log`

#### "Interface not found" Error?
This means you're using the old plugin structure. Fix:
```bash
# Remove old incompatible plugin file
rm /opt/librenms/html/plugins/WeathermapNG/WeathermapNG.php

# Pull latest version
cd /opt/librenms/html/plugins/WeathermapNG
git pull

# Re-enable plugin
cd /opt/librenms
./lnms plugin:enable WeathermapNG
```

#### Database Setup Failed?
If automatic database setup fails, WeathermapNG provides multiple ways to create the required tables:

**Option 1: Run the setup script**
```bash
cd /opt/librenms/html/plugins/WeathermapNG
php database/setup.php
```

**Option 2: Import SQL directly**
```bash
cd /opt/librenms/html/plugins/WeathermapNG
mysql -u librenms -p librenms < database/schema.sql
```

**Option 3: Check existing tables**
```bash
# Verify if tables already exist
mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"
```

**Note**: The `plugin:migrate` command does not exist in LibreNMS. Local plugins cannot use Laravel migrations, so we create tables directly using the setup script or SQL file.

## Plugin Structure (LibreNMS 24.x)

The plugin follows LibreNMS's hook-based architecture:

```
WeathermapNG/
├── app/
│   └── Plugins/
│       └── WeathermapNG/
│           ├── Menu.php         # Menu hook
│           ├── Page.php         # Page hook
│           └── Settings.php     # Settings hook
├── resources/
│   └── views/
│       └── weathermapng/
│           ├── menu.blade.php
│           ├── page.blade.php
│           └── settings.blade.php
├── routes.php                   # Plugin routes
├── plugin.json                  # Plugin metadata
└── install.sh                   # Installation script
```

## System Requirements

### Minimum Requirements
- LibreNMS (latest stable)
- PHP 8.0+
- MySQL 5.7+ or PostgreSQL 9.5+
- 100MB disk space

### Required PHP Extensions
- gd (image generation)
- json (data handling)
- pdo (database)
- mbstring (string handling)

### Required Commands
- git (for installation)
- composer (for dependencies)
- mysql or psql (for database)

## Post-Installation

### Access the Plugin
The plugin can be accessed via two methods:
- **v2 Plugin System**: `https://your-librenms/plugin/WeathermapNG`
- **v1 Legacy System**: `https://your-librenms/plugin/v1/WeathermapNG`

Settings can be configured at:
- `https://your-librenms/plugin/settings/WeathermapNG`

### Create Your First Map
1. Click "Create New Map"
2. Add devices from your LibreNMS inventory
3. Draw connections between devices
4. Save and view real-time data

### Health Check
```bash
# Check if plugin is healthy
curl https://your-librenms/plugin/WeathermapNG/health

# View metrics
curl https://your-librenms/plugin/WeathermapNG/metrics
```

## Support

- **GitHub Issues**: https://github.com/lance0/weathermapNG/issues
- **Documentation**: See README.md
- **Installation Logs**: `/tmp/weathermapng_install_*.log`

## Advanced Options

### Installation Modes
- `./install.sh` or `./install.sh express` - Fully automatic
- `./install.sh custom` - Interactive with prompts
- `./install.sh docker` - Optimized for containers
- `./install.sh dev` - Includes development dependencies

### Environment Variables
```bash
# Override LibreNMS path
LIBRENMS_PATH=/custom/path ./install.sh

# Force Docker mode
LIBRENMS_DOCKER=true ./install.sh

# Skip certain checks
SKIP_CRON=true ./install.sh
```
