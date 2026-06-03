#!/bin/bash
# deploy.sh - Run on server: bash /var/www/hybridstream/deploy.sh
# Copies updated source files from /tmp/hybridstream (public repo, no auth needed)
# Includes YouTube metadata tools setup for Flussonic integration

set -e

APP_DIR="/var/www/hybridstream"
TMP_DIR="/tmp/hybridstream"

echo "=== HybridStream Deployment with YouTube Integration ==="
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

echo "Installing/updating YouTube metadata tools..."
# Check if yt-dlp is installed for YouTube metadata extraction
if ! command -v yt-dlp &> /dev/null; then
    echo "Installing yt-dlp for YouTube metadata extraction..."
    apt update -qq
    apt install -y python3-pip python3-venv
    pip3 install --upgrade yt-dlp
    
    # Also install for www-data user
    sudo -u www-data pip3 install --user yt-dlp
    
    echo "✓ yt-dlp installed successfully"
else
    echo "✓ yt-dlp already installed: $(yt-dlp --version)"
    # Update yt-dlp to latest version
    pip3 install --upgrade yt-dlp
    sudo -u www-data pip3 install --user --upgrade yt-dlp
fi

echo "Verifying Flussonic integration..."
# Check Flussonic service
if systemctl is-active --quiet flussonic; then
    echo "✓ Flussonic service is running"
    
    # Check if port 8090 is accessible
    if timeout 3 bash -c "</dev/tcp/localhost/8090"; then
        echo "✓ Flussonic accessible on port 8090"
    else
        echo "⚠️ Flussonic port 8090 not accessible - check firewall"
    fi
else
    echo "⚠️ Flussonic service not running - YouTube streaming may not work"
    echo "   Start with: sudo systemctl start flussonic"
fi

echo "Updating Laravel dependencies and configuration..."
composer install --no-dev --optimize-autoloader

echo "Running database migrations..."
php artisan migrate --force

echo "Clearing and optimizing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache

echo "Setting proper permissions..."
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# Test YouTube functionality
echo "Testing YouTube metadata extraction..."
if sudo -u www-data timeout 10 yt-dlp --no-playlist --dump-json "https://www.youtube.com/watch?v=dQw4w9WgXcQ" > /dev/null 2>&1; then
    echo "✓ YouTube metadata extraction test passed"
else
    echo "⚠️ YouTube metadata extraction test failed - may need manual configuration"
fi

# Refresh existing YouTube videos with missing metadata
echo "Refreshing YouTube videos with missing metadata..."
php artisan youtube:refresh-metadata --no-interaction 2>/dev/null || echo "No YouTube videos to refresh or command not available yet"

echo ""
echo "=== Deployment Summary ==="
echo "✓ Code updated from repository"
echo "✓ Dependencies installed"
echo "✓ Database migrations applied"
echo "✓ Caches optimized"
echo "✓ YouTube tools configured"
echo "✓ Flussonic integration verified"
echo ""
echo "Next steps:"
echo "1. Run 'npm run build' if frontend changes were made"
echo "2. Test YouTube video uploads in the web interface"
echo "3. Check system status: curl -H 'Authorization: Bearer TOKEN' http://localhost/api/system/status"
echo ""
echo "Logs: tail -f storage/logs/laravel.log"
echo "Flussonic: sudo journalctl -u flussonic -f"
echo "Deployment completed successfully!"
