#!/bin/bash

echo "🔧 Creating custom PHP configuration for large uploads..."

# Method 1: Create custom PHP-FPM configuration file
sudo tee /etc/php/8.3/fpm/conf.d/99-upload-limits.ini << 'EOF'
; Custom upload limits for video files
upload_max_filesize = 2G
post_max_size = 2G
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
max_file_uploads = 20
EOF

# Method 2: Create custom nginx configuration with more settings
sudo tee /etc/nginx/conf.d/large-uploads.conf << 'EOF'
# Large file upload configuration
client_max_body_size 2G;
client_body_timeout 300s;
client_header_timeout 60s;
client_body_temp_path /var/cache/nginx/client_temp;
client_body_buffer_size 128k;
send_timeout 300s;

# Proxy settings for large uploads
proxy_connect_timeout 300s;
proxy_send_timeout 300s;
proxy_read_timeout 300s;
proxy_buffering off;
proxy_request_buffering off;

# FastCGI settings
fastcgi_read_timeout 300s;
fastcgi_send_timeout 300s;
fastcgi_connect_timeout 300s;
fastcgi_buffer_size 128k;
fastcgi_buffers 4 256k;
fastcgi_busy_buffers_size 256k;
EOF

# Method 3: Update Laravel .htaccess for additional limits
if [ -f "/var/www/hybridstream/public/.htaccess" ]; then
    echo "📝 Adding PHP limits to .htaccess..."
    sudo tee -a /var/www/hybridstream/public/.htaccess << 'EOF'

# Large file upload limits
php_value upload_max_filesize 2G
php_value post_max_size 2G
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 512M
EOF
fi

# Create nginx cache directory if it doesn't exist
sudo mkdir -p /var/cache/nginx/client_temp
sudo chown -R www-data:www-data /var/cache/nginx/

# Restart services
echo "🔄 Restarting services..."
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx

# Verify configuration
echo "✅ Configuration created! Checking results..."
echo "PHP settings:"
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"
php -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"

echo ""
echo "Nginx configuration test:"
sudo nginx -t

echo ""
echo "🎉 Upload limits should now be 2GB!"
echo "If still having issues, try Method 4 below..."