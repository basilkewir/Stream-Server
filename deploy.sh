#!/bin/bash

# HybridStream Deployment Script for Ubuntu Server
# This script pulls the latest changes from GitHub and updates the live server

set -e  # Exit on any error

echo "🚀 Starting HybridStream deployment..."

# Define paths
PROJECT_DIR="/var/www/hybridstream"
BACKUP_DIR="/var/backups/hybridstream"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Check if running as root - allow but warn
if [[ $EUID -eq 0 ]]; then
   log "⚠️  Running as root. Consider using a dedicated deployment user for better security."
fi

# Check if project directory exists
if [ ! -d "$PROJECT_DIR" ]; then
    log "❌ Project directory $PROJECT_DIR does not exist!"
    exit 1
fi

cd "$PROJECT_DIR"

# Verify this is a git repository
if [ ! -d ".git" ]; then
    log "❌ This is not a git repository!"
    exit 1
fi

# Create backup directory if it doesn't exist
sudo mkdir -p "$BACKUP_DIR"

log "📦 Creating backup before deployment..."
sudo tar -czf "$BACKUP_DIR/hybridstream_backup_$TIMESTAMP.tar.gz" \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=storage/logs \
    --exclude=storage/framework/cache \
    --exclude=storage/framework/sessions \
    --exclude=storage/framework/views \
    .

log "✨ Enabling maintenance mode..."
php artisan down --retry=60 2>/dev/null || php artisan down

# Function to restore from maintenance mode on exit
cleanup() {
    log "🔄 Disabling maintenance mode..."
    php artisan up
}
trap cleanup EXIT

log "📥 Pulling latest changes from GitHub..."
# Stash any local changes first
git stash push -m "Auto-stash before deployment $TIMESTAMP"
# Fetch and pull the latest changes
git fetch origin
git checkout main
git pull origin main

# Ask for YouTube API key if not configured
CURRENT_KEY=$(grep "^YOUTUBE_API_KEY=" "$PROJECT_DIR/.env" | cut -d= -f2- | tr -d '"')
if [ -z "$CURRENT_KEY" ]; then
    echo ""
    echo "YouTube Data API v3 key not set. (Get one: https://console.cloud.google.com/apis/credentials)"
    echo -n "Enter YouTube API key (press Enter to skip): "
    read -r YOUTUBE_API_KEY
    if [ -n "$YOUTUBE_API_KEY" ]; then
        if grep -q "^YOUTUBE_API_KEY=" "$PROJECT_DIR/.env"; then
            sed -i "s/YOUTUBE_API_KEY=.*/YOUTUBE_API_KEY=\"${YOUTUBE_API_KEY}\"/" "$PROJECT_DIR/.env"
        else
            echo "YOUTUBE_API_KEY=\"${YOUTUBE_API_KEY}\"" >> "$PROJECT_DIR/.env"
        fi
        log "YouTube API key saved. Clear config cache to apply..."
        php artisan config:clear
    else
        log "Skipped. YouTube metadata will rely on yt-dlp fallback."
    fi
else
    log "YouTube API key already configured."
fi

log "📦 Installing/updating Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

log "📦 Installing/updating NPM dependencies..."
npm ci --only=production

log "🔧 Building assets..."
npm run build

log "🗃️  Running database migrations..."
php artisan migrate --force

log "🧹 Clearing and optimizing caches..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

log "📁 Setting proper permissions..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

log "🔄 Restarting services..."
# Detect PHP version and restart appropriate service
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
sudo systemctl reload php${PHP_VERSION}-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || log "⚠️  Could not reload PHP-FPM"
sudo systemctl reload nginx

# Install/Update yt-dlp if needed
log "📺 Checking yt-dlp installation..."
if ! command -v yt-dlp &> /dev/null; then
    log "📺 Installing yt-dlp for YouTube metadata extraction..."
    sudo apt update
    sudo apt install -y python3-pip
    sudo pip3 install --upgrade yt-dlp
    sudo -u www-data pip3 install --user --upgrade yt-dlp
else
    log "📺 Updating yt-dlp..."
    sudo pip3 install --upgrade yt-dlp
    sudo -u www-data pip3 install --user --upgrade yt-dlp
fi

# Ensure Flussonic is running
log "📡 Checking Flussonic status..."
if systemctl is-active --quiet flussonic; then
    log "✅ Flussonic is running"
    log "🔄 Reloading Flussonic configuration..."
    sudo systemctl reload flussonic
else
    log "⚠️  Flussonic is not running, attempting to start..."
    sudo systemctl start flussonic
    sleep 5
    if systemctl is-active --quiet flussonic; then
        log "✅ Flussonic started successfully"
    else
        log "❌ Failed to start Flussonic - please check manually"
    fi
fi

# Restart stream monitoring if service exists
log "🔄 Restarting stream monitor..."
if systemctl is-enabled --quiet hybridstream-monitor 2>/dev/null; then
    sudo systemctl restart hybridstream-monitor
    log "✅ Stream monitor restarted"
else
    log "ℹ️  Stream monitor service not found - may need manual setup"
fi

# Trigger VOD system check for all channels
log "🎥 Checking VOD systems for all channels..."
php artisan stream:monitor --interval=1 2>/dev/null &
MONITOR_PID=$!
sleep 3
kill $MONITOR_PID 2>/dev/null || true

log "✅ Deployment completed successfully!"
log "🔗 Your application is now updated with the latest VOD playlist fixes"
log "📊 Check the admin dashboard to verify everything is working"

# Clean up old backups (keep last 5)
log "🧹 Cleaning up old backups..."
sudo find "$BACKUP_DIR" -name "hybridstream_backup_*.tar.gz" -type f | sort | head -n -5 | xargs -r sudo rm

# Display current git status
log "📋 Current deployment info:"
echo "   Branch: $(git branch --show-current)"
echo "   Commit: $(git log --oneline -1)"
echo "   Time: $(date)"

log "🎉 Deployment finished! The server is ready."
log "🌐 Access your application at: http://$(hostname -I | awk '{print $1}')"
log "📺 VOD playlist functionality has been updated and improved"