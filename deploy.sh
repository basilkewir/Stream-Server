#!/bin/bash
# deploy.sh - Run on server: bash /var/www/hybridstream/deploy.sh
# Copies updated source files from /tmp/hybridstream (public repo, no auth needed)

set -e

APP_DIR="/var/www/hybridstream"
TMP_DIR="/tmp/hybridstream"

echo "Fetching latest code..."
if [ -d "$TMP_DIR/.git" ]; then
    git -C "$TMP_DIR" fetch origin main
    git -C "$TMP_DIR" reset --hard origin/main
else
    rm -rf "$TMP_DIR"
    git clone https://github.com/basilkewir/Stream-Server.git "$TMP_DIR"
fi

echo "Syncing files (preserving .env and storage)..."
rsync -a \
    --exclude='.env' \
    --exclude='storage/app/public' \
    --exclude='storage/logs' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='public/build' \
    --exclude='public/storage' \
    "$TMP_DIR/" "$APP_DIR/"

chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

cd "$APP_DIR"

echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo "Done. Run 'npm run build' if frontend changes were made."
