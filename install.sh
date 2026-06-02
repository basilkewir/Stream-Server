#!/bin/bash
#
# HybridStream Core - Ubuntu Server Install Script
# Tested on Ubuntu 22.04/24.04 LTS
#
# Usage: sudo bash install.sh

set -e

APP_DIR="/var/www/hybridstream"
PHP_VERSION="8.2"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}=== HybridStream Core Server Installer ===${NC}"
echo ""

if [[ $EUID -eq 0 ]]; then
    echo -e "${YELLOW}Running as root${NC}"
else
    echo -e "${RED}Please run with: sudo bash install.sh${NC}"
    exit 1
fi

echo -e "${YELLOW}[1/10] Updating system packages...${NC}"
apt-get update -y
apt-get upgrade -y

echo -e "${YELLOW}[2/10] Installing PHP and extensions...${NC}"
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-redis \
    php${PHP_VERSION}-pcntl \
    php${PHP_VERSION}-posix \
    php${PHP_VERSION}-intl

echo -e "${YELLOW}[3/10] Installing FFmpeg...${NC}"
apt-get install -y ffmpeg

echo -e "${YELLOW}[4/10] Installing Redis...${NC}"
apt-get install -y redis-server
systemctl enable redis-server
systemctl start redis-server

echo -e "${YELLOW}[5/10] Installing Composer...${NC}"
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo -e "${YELLOW}[6/10] Installing Node.js and npm...${NC}"
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo -e "${YELLOW}[7/10] Installing Flussonic Media Server...${NC}"
if ! command -v flussonic &> /dev/null; then
    apt-get install -y curl gnupg
    curl -fsSL https://flussonic.com/install.sh | bash
    systemctl enable flussonic
    systemctl start flussonic

    # Wait for Flussonic to start
    sleep 5

    # Configure Flussonic license (free tier: 1 input stream, unlimited outputs)
    # For production: replace with your license key
    curl -s -u admin:admin -X POST http://127.0.0.1:8080/flussonic/api/install_license \
        -d '{"key":"free"}'

    echo -e "${GREEN}Flussonic installed. Access admin UI at http://YOUR_IP:8080 (admin/admin)${NC}"
else
    echo -e "${GREEN}Flussonic already installed${NC}"
fi

echo -e "${YELLOW}[8/10] Setting up application...${NC}"

if [ ! -d "$APP_DIR" ]; then
    mkdir -p $APP_DIR
fi

cp -r ./* $APP_DIR/
chown -R www-data:www-data $APP_DIR
chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

cd $APP_DIR

echo -e "${YELLOW}[8/10] Installing PHP dependencies...${NC}"
sudo -u www-data composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}Configuring environment...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
sed -i 's/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/' .env
sed -i 's/BROADCAST_CONNECTION=.*/BROADCAST_CONNECTION=reverb/' .env
sed -i 's/CACHE_STORE=.*/CACHE_STORE=redis/' .env
sed -i 's/SESSION_DRIVER=.*/SESSION_DRIVER=redis/' .env
sed -i 's/APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i 's/APP_URL=.*/APP_URL=http:\/\/YOUR_SERVER_IP/' .env
sed -i 's/REVERB_HOST=.*/REVERB_HOST="0.0.0.0"/' .env
sed -i 's/STREAM_HOST=.*/STREAM_HOST="YOUR_SERVER_IP"/' .env

echo -e "${YELLOW}[9/10] Building frontend...${NC}"
npm ci
npm run build

echo -e "${YELLOW}Running migrations and seeding...${NC}"
php artisan migrate --force
php artisan db:seed --force

echo -e "${YELLOW}[10/10] Setting up services...${NC}"

cat > /etc/systemd/system/hybridstream-monitor.service << 'MONITOR_SERVICE'
[Unit]
Description=HybridStream Health Monitor
After=network.target redis-server.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/hybridstream
ExecStart=/usr/bin/php artisan stream:monitor
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
MONITOR_SERVICE

cat > /etc/systemd/system/hybridstream-reverb.service << 'REVERB_SERVICE'
[Unit]
Description=HybridStream Reverb WebSocket Server
After=network.target redis-server.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/hybridstream
    ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=6001
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
REVERB_SERVICE

cat > /etc/systemd/system/hybridstream-horizon.service << 'HORIZON_SERVICE'
[Unit]
Description=HybridStream Horizon Queue Worker
After=network.target redis-server.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/hybridstream
ExecStart=/usr/bin/php artisan horizon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
HORIZON_SERVICE

systemctl daemon-reload
systemctl enable hybridstream-monitor hybridstream-reverb hybridstream-horizon
systemctl start hybridstream-monitor hybridstream-reverb hybridstream-horizon

cat > /etc/nginx/sites-available/hybridstream << 'NGINX_CONFIG'
server {
    listen 80;
    server_name _;
    root /var/www/hybridstream/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket proxy -> Laravel Reverb on port 6001
    location /ws/ {
        proxy_pass http://127.0.0.1:6001/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
    }

    # Flussonic HLS/DASH playback proxy (container port 80, host 8082)
    # Access via: http://YOUR_IP:8082/{stream}/index.m3u8
    # Or proxy through Nginx (optional):
    # location /streams/ {
    #     proxy_pass http://127.0.0.1:8082/;
    # }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_CONFIG

ln -sf /etc/nginx/sites-available/hybridstream /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  HybridStream Core Installation Done!  ${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Default logins:"
echo "  Admin: admin@hybridstream.local / admin123"
echo "  User:  demo@hybridstream.local / demo123"
echo ""
echo "Services:"
echo "  Web App:      http://YOUR_SERVER_IP"
echo "  Flussonic UI: http://YOUR_SERVER_IP:8080 (admin/admin)"
echo "  HLS Playback: http://YOUR_SERVER_IP:8082/STREAM_KEY/index.m3u8"
echo "  Horizon:      http://YOUR_SERVER_IP/horizon"
echo ""
echo "Port map (no conflicts):"
echo "  80    - Nginx (Laravel web app)"
echo "  1935  - Flussonic RTMP ingest"
echo "  554   - Flussonic RTSP"
echo "  8080  - Flussonic API/Admin UI"
echo "  8082  - Flussonic HLS/DASH playback"
echo "  10000 - Flussonic SRT ingest"
echo "  6001  - Laravel Reverb WebSocket"
echo "  6379  - Redis"
echo "  3306  - MySQL"
echo ""
echo "Push stream (vMix/OBS): rtmp://YOUR_IP:1935/STREAM_KEY"
echo ""
echo -e "${YELLOW}IMPORTANT: Update APP_URL, STREAM_HOST, and FLUSSONIC_HOST in .env with your server IP${NC}"
echo -e "${YELLOW}After first login, CHANGE the default passwords!${NC}"
echo -e "${YELLOW}Flussonic free license: 1 input stream, unlimited outputs. Get a license at flussonic.com${NC}"
echo ""
