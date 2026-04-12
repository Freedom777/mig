#!/bin/bash

# Использование: ./migrate_site_to_nginx.sh DOMAIN PATH
# Пример: ./migrate_site_to_nginx.sh photo.freedomvibe.net /var/www/photo

DOMAIN="$1"
SITE_PATH="$2"

if [ -z "$DOMAIN" ] || [ -z "$SITE_PATH" ]; then
    echo "Использование: $0 DOMAIN SITE_PATH"
    echo "Пример: $0 lab.freedomvibe.net /var/www/lab"
    exit 1
fi

echo "🚀 Миграция $DOMAIN на Nginx..."

# Определяем Apache конфиг
APACHE_CONF="/etc/apache2/sites-available/${DOMAIN}.conf"

if [ ! -f "$APACHE_CONF" ]; then
    echo "❌ Не найден Apache конфиг: $APACHE_CONF"
    exit 1
fi

# Извлекаем PHP-FPM сокет из существующего конфига
PHP_SOCK=$(grep -oP 'proxy:unix:/run/php/\K[^|]+' "$APACHE_CONF" | head -1)

if [ -z "$PHP_SOCK" ]; then
    echo "⚠️  Не найден PHP-FPM сокет в $APACHE_CONF"
    echo "Укажите вручную или проверьте конфиг"
    exit 1
fi

echo "🔍 Найден PHP-FPM сокет: $PHP_SOCK"

# 1. Создать Nginx конфиг
echo "📝 Создаю Nginx конфиг..."
cat > /tmp/nginx_${DOMAIN}.conf <<EOF
# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name $DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

# HTTPS
server {
    listen 443 ssl http2;
    server_name $DOMAIN;

    # SSL
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # Логи
    access_log /log/sites/$(echo $DOMAIN | sed 's/\.freedomvibe\.net//' | tr '.' '-')-access.log;
    error_log /log/sites/$(echo $DOMAIN | sed 's/\.freedomvibe\.net//' | tr '.' '-')-error.log;

    # Proxy на Apache
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Port 443;
    }
}
EOF

sudo mv /tmp/nginx_${DOMAIN}.conf /etc/nginx/sites-available/${DOMAIN}.conf
sudo ln -sf /etc/nginx/sites-available/${DOMAIN}.conf /etc/nginx/sites-enabled/

# 2. Убрать редирект из Apache VirtualHost *:8080
echo "🔧 Убираю редирект из Apache..."

# Создаём временный файл с изменениями
sudo awk '
    /<VirtualHost \*:8080>/ { in_8080=1 }
    /<\/VirtualHost>/ { if (in_8080) in_8080=0 }
    /RewriteEngine on/ && in_8080 { in_rewrite=1; print "#" $0; next }
    /RewriteCond/ && in_rewrite { print "#" $0; next }
    /RewriteRule.*permanent\]/ && in_rewrite { print "#" $0; in_rewrite=0; next }
    { print }
' "$APACHE_CONF" > /tmp/apache_temp.conf

sudo mv /tmp/apache_temp.conf "$APACHE_CONF"

# 3. Добавить PHP-FPM в VirtualHost *:8080 если нет
echo "🔧 Проверяю PHP-FPM в Apache VirtualHost *:8080..."

if ! awk '/<VirtualHost \*:8080>/,/<\/VirtualHost>/ {if (/FilesMatch.*php/) found=1} END {exit !found}' "$APACHE_CONF"; then
    echo "📝 Добавляю PHP-FPM в VirtualHost *:8080..."

    # Добавляем FilesMatch после первого </Directory> в секции 8080
    sudo awk -v sock="$PHP_SOCK" '
        /<VirtualHost \*:8080>/ { in_8080=1 }
        /<\/VirtualHost>/ { if (in_8080) in_8080=0 }
        /<\/Directory>/ && in_8080 && !added {
            print
            print "    <FilesMatch \\.php$>"
            print "        SetHandler \"proxy:unix:/run/php/" sock "|fcgi://localhost\""
            print "    </FilesMatch>"
            added=1
            next
        }
        { print }
    ' "$APACHE_CONF" > /tmp/apache_temp.conf

    sudo mv /tmp/apache_temp.conf "$APACHE_CONF"
else
    echo "✅ PHP-FPM уже настроен в VirtualHost *:8080"
fi

# 4. Обновить .env
if [ -f "$SITE_PATH/.env" ]; then
    echo "📝 Обновляю .env..."
    sudo sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" "$SITE_PATH/.env"

    if grep -q "^ASSET_URL=" "$SITE_PATH/.env"; then
        sudo sed -i "s|^ASSET_URL=.*|ASSET_URL=https://$DOMAIN|" "$SITE_PATH/.env"
    else
        echo "ASSET_URL=https://$DOMAIN" | sudo tee -a "$SITE_PATH/.env" > /dev/null
    fi

    # Очистка кеша
    echo "🧹 Очищаю кеш Laravel..."
    if [ -d "$SITE_PATH" ]; then
        cd "$SITE_PATH" || exit 1
        sudo -u www-data php artisan config:clear 2>/dev/null
        sudo -u www-data php artisan cache:clear 2>/dev/null
    else
        echo "⚠️  Директория $SITE_PATH не найдена, пропускаю очистку кеша"
    fi
fi

# 5. Проверка и перезагрузка
echo "✅ Проверяю конфиги..."
sudo nginx -t && sudo apache2ctl configtest

if [ $? -eq 0 ]; then
    echo "🔄 Перезагружаю сервисы..."
    sudo systemctl reload nginx
    sudo systemctl restart apache2
    echo ""
    echo "✅ ================================"
    echo "✅ Готово! $DOMAIN мигрирован на Nginx!"
    echo "✅ ================================"
    echo "🌐 Проверь: https://$DOMAIN"
    echo ""
else
    echo "❌ Ошибка в конфигурации!"
    exit 1
fi
