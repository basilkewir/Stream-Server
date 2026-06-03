#!/bin/bash

echo "🔧 Alternative Method: Using Laravel configuration..."

# Method 4: Create Laravel-specific upload configuration
sudo tee /var/www/hybridstream/.env.upload << 'EOF'
# Large file upload configuration
PHP_UPLOAD_MAX_FILESIZE=2G
PHP_POST_MAX_SIZE=2G
PHP_MAX_EXECUTION_TIME=300
PHP_MEMORY_LIMIT=512M
NGINX_CLIENT_MAX_BODY_SIZE=2G
EOF

# Add to main .env file
echo "" | sudo tee -a /var/www/hybridstream/.env
echo "# Upload limits" | sudo tee -a /var/www/hybridstream/.env
cat /var/www/hybridstream/.env.upload | sudo tee -a /var/www/hybridstream/.env

# Method 5: Direct file replacement approach
echo "📝 Direct configuration update..."

# Create backup of original PHP.ini
sudo cp /etc/php/8.3/fpm/php.ini /etc/php/8.3/fpm/php.ini.backup

# Use awk to replace values (more reliable than sed)
sudo awk '
/^upload_max_filesize/ { print "upload_max_filesize = 2G"; next }
/^post_max_size/ { print "post_max_size = 2G"; next }
/^max_execution_time/ { print "max_execution_time = 300"; next }
/^memory_limit/ { print "memory_limit = 512M"; next }
{ print }
' /etc/php/8.3/fpm/php.ini.backup | sudo tee /etc/php/8.3/fpm/php.ini > /dev/null

# Method 6: Using php.ini override
echo "📝 Creating PHP.ini override..."
sudo tee /etc/php/8.3/fpm/pool.d/upload-limits.conf << 'EOF'
[upload-limits]
php_admin_value[upload_max_filesize] = 2G
php_admin_value[post_max_size] = 2G
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 512M
php_admin_value[max_input_time] = 300
EOF

# Restart services
sudo systemctl restart php8.3-fpm
sudo systemctl reload nginx

echo "✅ Multiple methods applied! Testing..."
php -r "echo 'Final settings:' . PHP_EOL;"
php -r "echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL;"