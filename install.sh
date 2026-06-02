#!/bin/bash
#
# HybridStream Core - Ubuntu Server Install Script
# Tested on Ubuntu 22.04/24.04 LTS
#
# For fresh VPS: copy this file + project to /tmp/hybridstream, then:
#   cd /tmp/hybridstream && sudo bash install.sh
#

set -e

APP_DIR="/var/www/hybridstream"
PHP_VERSION="8.2"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  HybridStream Core Server Installer        ${NC}"
echo -e "${GREEN}  Flussonic 24.02 + Laravel 13 + Vue 3      ${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""

if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}Please run with: sudo bash install.sh${NC}"
    exit 1
fi

# --- Ask for server IP ---
echo -e "${YELLOW}Enter your server's public IP or domain:${NC}"
read -r SERVER_IP
if [ -z "$SERVER_IP" ]; then
    SERVER_IP="127.0.0.1"
    echo -e "${YELLOW}No IP entered, using 127.0.0.1${NC}"
fi

echo -e "${YELLOW}Enter MySQL root password (blank for none):${NC}"
read -rs MYSQL_PASS
echo ""

echo -e "${YELLOW}Enter a database name [hybridstream]:${NC}"
read -r DB_NAME
DB_NAME=${DB_NAME:-hybridstream}

echo ""
echo -e "${GREEN}Installing to ${APP_DIR} for ${SERVER_IP}${NC}"
sleep 2

# --- [1/10] System updates ---
echo -e "${YELLOW}[1/10] Updating system packages...${NC}"
apt-get update -y
apt-get upgrade -y

# --- [2/10] PHP ---
echo -e "${YELLOW}[2/10] Installing PHP 8.2 + extensions...${NC}"
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -y
apt-get install -y \
    php${PHP_VERSION} php${PHP_VERSION}-cli php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-common php${PHP_VERSION}-mysql php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml php${PHP_VERSION}-curl \
    php${PHP_VERSION}-gd php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-redis php${PHP_VERSION}-pcntl php${PHP_VERSION}-posix \
    php${PHP_VERSION}-intl

# --- [3/10] FFmpeg ---
echo -e "${YELLOW}[3/10] Installing FFmpeg...${NC}"
apt-get install -y ffmpeg

# --- [4/10] MySQL ---
echo -e "${YELLOW}[4/10] Setting up MySQL...${NC}"
if ! command -v mysql &> /dev/null; then
    apt-get install -y mysql-server
    systemctl enable mysql
    systemctl start mysql
fi

# Create database
mysql -u root ${MYSQL_PASS:+-p"$MYSQL_PASS"} -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
    echo -e "${YELLOW}Could not create DB automatically. Create it manually and update .env later.${NC}"
}

# --- [5/10] Redis ---
echo -e "${YELLOW}[5/10] Installing Redis...${NC}"
apt-get install -y redis-server
systemctl enable redis-server
systemctl start redis-server

# --- [6/10] Composer + Node ---
echo -e "${YELLOW}[6/10] Installing Composer + Node.js...${NC}"
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

# --- [7/10] Flussonic ---
echo -e "${YELLOW}[7/10] Installing Flussonic Media Server 24.02...${NC}"
if ! command -v flussonic &> /dev/null; then
    apt-get install -y curl gnupg
    curl -fsSL https://flussonic.com/install.sh | bash
    systemctl enable flussonic
    systemctl start flussonic
    sleep 5
    curl -s -u admin:admin -X POST http://127.0.0.1:8080/flussonic/api/install_license \
        -d '{"key":"free"}'
    echo -e "${GREEN}Flussonic installed (free license: 1 input, unlimited outputs)${NC}"
else
    echo -e "${GREEN}Flussonic already installed${NC}"
fi

# --- [8/10] Application files ---
echo -e "${YELLOW}[8/10] Setting up application...${NC}"

mkdir -p "$APP_DIR"
cp -r ./* "$APP_DIR/"
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

cd "$APP_DIR"

# --- Install dependencies ---
echo -e "${YELLOW}Installing PHP dependencies...${NC}"
sudo -u www-data composer install --no-dev --optimize-autoloader

# --- Configure .env ---
echo -e "${YELLOW}Configuring environment...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Update .env for production
sed -i "s/APP_URL=.*/APP_URL=http:\/\/${SERVER_IP}/" .env
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${MYSQL_PASS}/" .env
sed -i "s/STREAM_HOST=.*/STREAM_HOST=\"${SERVER_IP}\"/" .env
sed -i "s/FLUSSONIC_HOST=.*/FLUSSONIC_HOST=\"${SERVER_IP}\"/" .env

# Update config cache
php artisan config:clear

# --- [9/10] Frontend build ---
echo -e "${YELLOW}[9/10] Building frontend...${NC}"
npm ci
npm run build

# --- Run migrations ---
echo -e "${YELLOW}Running database migrations...${NC}"
php artisan migrate --force
php artisan db:seed --force

# Create storage symlink
php artisan storage:link

# --- [10/10] System services ---
echo -e "${YELLOW}[10/10] Setting up systemd services...${NC}"

cat > /etc/systemd/system/hybridstream-monitor.service << 'EOF'
[Unit]
Description=HybridStream Health Monitor
After=network.target redis-server.service flussonic.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/hybridstream
ExecStart=/usr/bin/php artisan stream:monitor
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/hybridstream-reverb.service << 'EOF'
[Unit]
Description=HybridStream Reverb WebSocket
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
EOF

cat > /etc/systemd/system/hybridstream-horizon.service << 'EOF'
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
EOF

# Schedule health monitor via cron (fallback)
cat > /etc/cron.d/hybridstream << 'EOF'
* * * * * www-data /usr/bin/php /var/www/hybridstream/artisan schedule:run >> /dev/null 2>&1
EOF

# Configure Laravel scheduler
php artisan schedule:list

systemctl daemon-reload
systemctl enable hybridstream-monitor hybridstream-reverb hybridstream-horizon
systemctl start hybridstream-monitor hybridstream-reverb hybridstream-horizon

# --- Nginx ---
echo -e "${YELLOW}Configuring Nginx...${NC}"
apt-get install -y nginx 2>/dev/null || true

cat > /etc/nginx/sites-available/hybridstream << NGINXEOF
server {
    listen 80;
    server_name ${SERVER_IP};
    root /var/www/hybridstream/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket -> Reverb on port 6001
    location /ws/ {
        proxy_pass http://127.0.0.1:6001/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
    }

    # Flussonic HLS proxy (optional, direct port 8082 also works)
    location /hls/ {
        proxy_pass http://127.0.0.1:8082/;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXEOF

ln -sf /etc/nginx/sites-available/hybridstream /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# --- Firewall ---
echo -e "${YELLOW}Configuring firewall (if ufw is active)...${NC}"
if command -v ufw &> /dev/null && ufw status | grep -q active; then
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow 1935/tcp
    ufw allow 554/tcp
    ufw allow 8080/tcp
    ufw allow 8082/tcp
    ufw allow 10000/udp
    ufw allow 6001/tcp
fi

# --- Done ---
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  Installation Complete!                    ${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo "Default logins:"
echo "  Admin    : admin@hybridstream.local / admin123"
echo "  User     : demo@hybridstream.local / demo123"
echo ""
echo "URLs:"
echo "  Web App       : http://${SERVER_IP}"
echo "  Flussonic UI  : http://${SERVER_IP}:8080  (admin/admin)"
echo "  HLS Playback  : http://${SERVER_IP}:8082/{stream}/index.m3u8"
echo "  Horizon Queue : http://${SERVER_IP}/horizon"
echo ""
echo "Port map:"
echo "  80    Nginx (Laravel)"
echo "  1935  Flussonic RTMP"
echo "  554   Flussonic RTSP"
echo "  8080  Flussonic API/UI"
echo "  8082  Flussonic HLS/DASH"
echo "  10000 Flussonic SRT"
echo "  6001  Reverb WebSocket"
echo ""
echo "Push from encoder:  rtmp://${SERVER_IP}:1935/{stream_key}"
echo "Play HLS:           http://${SERVER_IP}:8082/{stream_key}/index.m3u8"
echo ""
echo -e "${YELLOW}CHANGE the default admin password immediately!${NC}"
echo -e "${YELLOW}Visit http://${SERVER_IP}:8080 to check Flussonic status${NC}"
echo ""
