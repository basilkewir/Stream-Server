#!/bin/bash
#
# HybridStream Deploy Script
# Run on server: bash deploy.sh
#
set -e

PROJECT_DIR="/var/www/hybridstream"
BACKUP_DIR="/var/backups/hybridstream"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"; }

# --- Pre-flight checks ---
if [ ! -d "$PROJECT_DIR" ]; then
    log "ERROR: $PROJECT_DIR not found. Run install.sh first."
    exit 1
fi
cd "$PROJECT_DIR"

if [ ! -d ".git" ]; then
    log "ERROR: .git directory not found. Not a git repo."
    exit 1
fi

# --- Backup ---
mkdir -p "$BACKUP_DIR"
log "Creating backup..."
tar -czf "$BACKUP_DIR/hybridstream_backup_$TIMESTAMP.tar.gz" \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=storage/logs \
    --exclude=storage/framework/cache \
    --exclude=storage/framework/sessions \
    --exclude=storage/framework/views \
    . 2>/dev/null

# --- Git pull ---
log "Pulling latest from git..."
git fetch origin
git checkout main 2>/dev/null || true
git pull origin main

# --- YouTube API key prompt ---
CURRENT_KEY=$(grep "^YOUTUBE_API_KEY=" .env 2>/dev/null | cut -d= -f2- | tr -d '"')
if [ -z "$CURRENT_KEY" ]; then
    echo ""
    echo "YouTube Data API v3 key not set."
    echo "Get one at: https://console.cloud.google.com/apis/credentials"
    echo -n "Enter key (Enter to skip): "
    read -r YOUTUBE_API_KEY
    if [ -n "$YOUTUBE_API_KEY" ]; then
        if grep -q "^YOUTUBE_API_KEY=" .env 2>/dev/null; then
            sed -i "s/^YOUTUBE_API_KEY=.*/YOUTUBE_API_KEY=\"${YOUTUBE_API_KEY}\"/" .env
        else
            echo "YOUTUBE_API_KEY=\"${YOUTUBE_API_KEY}\"" >> .env
        fi
        log "YouTube API key saved."
        php artisan config:clear 2>/dev/null || true
    else
        log "Skipped. Will use yt-dlp fallback for metadata."
    fi
else
    log "YouTube API key already configured."
fi

# --- Composer ---
log "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# --- NPM build ---
if [ -f package.json ]; then
    log "Installing NPM dependencies..."
    npm ci 2>/dev/null || npm install
    log "Building frontend..."
    npm run build
fi

# --- Migrations ---
log "Running database migrations..."
php artisan migrate --force

# --- Cache ---
log "Optimizing caches..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Permissions ---
log "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# --- yt-dlp ---
log "Checking yt-dlp..."
if ! command -v yt-dlp &> /dev/null; then
    apt update -qq
    apt install -y python3-pip 2>/dev/null || true
    pip3 install --upgrade yt-dlp 2>/dev/null || true
fi
# Also install for www-data user
sudo -u www-data pip3 install --user --upgrade yt-dlp 2>/dev/null || true

# --- Flussonic ---
log "Checking Flussonic..."
if systemctl is-active --quiet flussonic 2>/dev/null; then
    log "Flussonic is running."
    systemctl reload flussonic 2>/dev/null || true
else
    log "WARNING: Flussonic not running. Start with: systemctl start flussonic"
fi

# --- Reload services ---
log "Reloading services..."
PHP_VER=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "8.3")
systemctl reload php${PHP_VER}-fpm 2>/dev/null || systemctl reload php-fpm 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true
systemctl restart hybridstream-monitor 2>/dev/null || true

# --- Clean old backups ---
find "$BACKUP_DIR" -name "hybridstream_backup_*.tar.gz" -type f | sort | head -n -5 | xargs -r rm -f 2>/dev/null || true

# --- Done ---
echo ""
log "Deploy complete!"
log "  Branch : $(git branch --show-current 2>/dev/null || echo unknown)"
log "  Commit : $(git log --oneline -1 2>/dev/null || echo unknown)"
log "  URL    : http://$(hostname -I | awk '{print $1}')"
echo ""
