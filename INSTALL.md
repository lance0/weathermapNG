# WeathermapNG Installation Guide

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
- ✅ Attempts to enable plugin

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
- Go to LibreNMS web interface
- Navigate to: Overview → Plugins → Plugin Admin
- Enable WeathermapNG

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
# Debian/Ubuntu
sudo apt-get install php8.0-gd php8.0-mbstring php8.0-mysql composer git

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

#### Database Issues?
```bash
# Check if tables exist
mysql -u librenms -p librenms -e "SHOW TABLES LIKE 'wmng_%';"

# Run migrations manually
cd /opt/librenms
php artisan plugin:migrate WeathermapNG
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
Visit: `https://your-librenms/plugins/weathermapng`

### Create Your First Map
1. Click "Create New Map"
2. Add devices from your LibreNMS inventory
3. Draw connections between devices
4. Save and view real-time data

### Health Check
```bash
# Check if plugin is healthy
curl https://your-librenms/plugins/weathermapng/health

# View metrics
curl https://your-librenms/plugins/weathermapng/metrics
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