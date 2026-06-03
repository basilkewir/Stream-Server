#!/bin/bash

# YouTube metadata extraction tools installer for Ubuntu Server with Flussonic
# This script installs yt-dlp for proper YouTube video metadata extraction

echo "Installing YouTube metadata extraction tools on Ubuntu Server..."
echo "Flussonic integration: Port 8090"

# Update package list
sudo apt update

# Install Python and pip if not already installed
echo "Ensuring Python and pip are installed..."
sudo apt install -y python3 python3-pip python3-venv curl

# Install yt-dlp (preferred over youtube-dl)
echo "Installing yt-dlp..."
sudo pip3 install --upgrade yt-dlp

# Verify installation
if command -v yt-dlp &> /dev/null; then
    echo "✓ yt-dlp installation successful!"
    echo "Version: $(yt-dlp --version)"
else
    echo "❌ yt-dlp installation failed. Trying alternative method..."
    
    # Try installing in user space
    pip3 install --user yt-dlp
    export PATH="$HOME/.local/bin:$PATH"
    echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.bashrc
    
    if command -v yt-dlp &> /dev/null; then
        echo "✓ yt-dlp installed in user space successfully!"
    else
        echo "❌ Failed to install yt-dlp. Manual installation required."
        exit 1
    fi
fi

# Test YouTube metadata extraction
echo "Testing YouTube metadata extraction..."
TEST_URL="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
if timeout 30 yt-dlp --no-playlist --dump-json "$TEST_URL" > /dev/null 2>&1; then
    echo "✓ YouTube metadata extraction test passed!"
else
    echo "⚠️ Metadata extraction test failed or timed out"
    echo "This might be due to network issues or YouTube rate limiting"
fi

# Check if we're running as the web server user and set appropriate permissions
WEB_USER=$(ps aux | grep -E '(apache2|nginx|www-data)' | grep -v grep | head -1 | awk '{print $1}')
if [ ! -z "$WEB_USER" ] && [ "$WEB_USER" != "root" ]; then
    echo "Setting up yt-dlp for web server user: $WEB_USER"
    sudo -u $WEB_USER pip3 install --user yt-dlp
fi

# Ensure Flussonic can work with YouTube streams
echo "Checking Flussonic configuration..."
if systemctl is-active --quiet flussonic; then
    echo "✓ Flussonic is running on the system"
    
    # Check if Flussonic config is accessible
    if [ -f "/etc/flussonic/flussonic.conf" ]; then
        echo "✓ Flussonic configuration found"
        
        # Backup the config
        sudo cp /etc/flussonic/flussonic.conf /etc/flussonic/flussonic.conf.backup.$(date +%Y%m%d_%H%M%S)
        echo "✓ Flussonic config backed up"
    fi
else
    echo "⚠️ Flussonic service not detected. Please ensure it's running for stream processing."
fi

# Set up proper permissions for Laravel storage and cache
echo "Setting up Laravel permissions..."
if [ -d "/var/www/html/storage" ]; then
    sudo chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    sudo chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache
fi

echo ""
echo "✓ Installation complete!"
echo "Your YouTube videos should now show proper duration and metadata."
echo ""
echo "Next steps:"
echo "1. Run: php artisan youtube:refresh-metadata"
echo "2. Test by adding a new YouTube video to your playlist"
echo "3. Verify Flussonic integration at http://your-server:8090"
echo ""
echo "If you encounter issues:"
echo "- Check Laravel logs: tail -f storage/logs/laravel.log"
echo "- Check Flussonic logs: sudo journalctl -u flussonic -f"
echo "- Verify yt-dlp works: yt-dlp --version"