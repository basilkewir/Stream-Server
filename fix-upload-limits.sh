#!/bin/bash

echo "🔧 Fixing upload size limits for large video files..."

# 1. Update nginx configuration
echo "📝 Updating nginx configuration..."
sudo bash -c 'cat > /etc/nginx/conf.d/upload_limits.conf << EOF
# Large file upload settings
client_max_body_size 2G;
client_body_timeout 300s;
client_header_timeout 300s;
send_timeout 300s;
proxy_connect_timeout 300s;
proxy_send_timeout 300s;
proxy_read_timeout 300s;
fastcgi_read_timeout 300s;
EOF'

# 2. Update PHP configuration
echo "📝 Updating PHP configuration..."
PHP_INI=$(php --ini | grep "Loaded Configuration File" | cut -d: -f2 | xargs)
echo "PHP config file: $PHP_INI"

sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 2G/' "$PHP_INI"
sudo sed -i 's/^post_max_size = .*/post_max_size = 2G/' "$PHP_INI"
sudo sed -i 's/^max_execution_time = .*/max_execution_time = 300/' "$PHP_INI"
sudo sed -i 's/^max_input_time = .*/max_input_time = 300/' "$PHP_INI"
sudo sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$PHP_INI"

# Also check for PHP-FPM pool configuration
if [ -f "/etc/php/8.3/fpm/pool.d/www.conf" ]; then
    echo "📝 Updating PHP-FPM pool configuration..."
    sudo sed -i 's/^;request_terminate_timeout = .*/request_terminate_timeout = 300/' /etc/php/8.3/fpm/pool.d/www.conf
elif [ -f "/etc/php/8.2/fpm/pool.d/www.conf" ]; then
    sudo sed -i 's/^;request_terminate_timeout = .*/request_terminate_timeout = 300/' /etc/php/8.2/fpm/pool.d/www.conf
elif [ -f "/etc/php/8.1/fpm/pool.d/www.conf" ]; then
    sudo sed -i 's/^;request_terminate_timeout = .*/request_terminate_timeout = 300/' /etc/php/8.1/fpm/pool.d/www.conf
fi

# 3. Restart services
echo "🔄 Restarting services..."
sudo systemctl reload nginx
sudo systemctl restart php*-fpm

echo "✅ Upload limits updated:"
echo "   - Max file size: 2GB"
echo "   - Timeout: 300 seconds"
echo "   - Memory limit: 512MB"

# 4. Test configuration
echo "🧪 Testing nginx configuration..."
sudo nginx -t

echo "📊 Current PHP limits:"
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"

echo "🎉 Upload limits fixed! Try uploading your video again."