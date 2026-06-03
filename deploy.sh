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

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   log "⚠️  This script should not be run as root. Please run as the web user (www-data) or a user with appropriate permissions."
   exit 1
fi

# Check if project directory exists
if [ ! -d "$PROJECT_DIR" ]; then
    log "❌ Project directory $PROJECT_DIR does not exist!"
    exit 1
fi

cd "$PROJECT_DIR"

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
php artisan down --message="Deploying updates..." --retry=60

# Function to restore from maintenance mode on exit
cleanup() {
    log "🔄 Disabling maintenance mode..."
    php artisan up
}
trap cleanup EXIT

log "📥 Pulling latest changes from GitHub..."
git fetch origin
git reset --hard origin/main

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
sudo systemctl reload php8.1-fpm
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

# Restart stream monitoring
log "🔄 Restarting stream monitor..."
sudo systemctl restart hybridstream-monitor || log "⚠️  Stream monitor service not found - please set up manually"

log "✅ Deployment completed successfully!"
log "🔗 Your application is now updated with the latest VOD playlist fixes"
log "📊 Check the admin dashboard to verify everything is working"

# Clean up old backups (keep last 5)
log "🧹 Cleaning up old backups..."
sudo find "$BACKUP_DIR" -name "hybridstream_backup_*.tar.gz" -type f | sort | head -n -5 | xargs -r sudo rm

log "🎉 Deployment finished! The server is ready."