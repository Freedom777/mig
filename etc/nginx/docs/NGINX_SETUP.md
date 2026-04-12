# Настройка Nginx

Nginx выступает в роли reverse proxy и обрабатывает SSL, статические файлы и проксирует PHP запросы на Apache.

## Установка

```bash
sudo apt update
sudo apt install nginx -y

# Проверка версии
nginx -v
# nginx version: nginx/1.24.0 (Ubuntu)
```

## Структура конфигураций

```
/etc/nginx/
├── nginx.conf                    # Главный конфиг
├── sites-available/              # Доступные конфиги сайтов
│   ├── photo.freedomvibe.net.conf
│   ├── naturfreunde-traunreut.freedomvibe.net.conf
│   └── ...
└── sites-enabled/                # Активные конфиги (симлинки)
    ├── photo.freedomvibe.net.conf -> ../sites-available/photo.freedomvibe.net.conf
    └── ...
```

## Базовая структура конфига сайта

### Простой сайт (только proxy)

```nginx
# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name example.freedomvibe.net;
    return 301 https://$server_name$request_uri;
}

# HTTPS
server {
    listen 443 ssl http2;
    server_name example.freedomvibe.net;

    # SSL
    ssl_certificate /etc/letsencrypt/live/example.freedomvibe.net/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.freedomvibe.net/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # Логи
    access_log /log/sites/example-access.log;
    error_log /log/sites/example-error.log;

    # Всё на Apache
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Port 443;
    }
}
```

### Сайт с оптимизацией статики (photo.freedomvibe.net)

```nginx
# HTTP -> HTTPS redirect
server {
    listen 80;
    server_name photo.freedomvibe.net;
    return 301 https://$server_name$request_uri;
}

# HTTPS
server {
    listen 443 ssl http2;
    server_name photo.freedomvibe.net;

    # SSL
    ssl_certificate /etc/letsencrypt/live/photo.freedomvibe.net/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/photo.freedomvibe.net/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    root /var/www/photo/public;
    index index.php index.html;

    # Логи
    access_log /log/sites/photo-access.log;
    error_log /log/sites/photo-error.log;

    # Статика - Nginx отдаёт напрямую (БЫСТРО!)
    location /images/ {
        alias /var/www/photo/storage/app/public/images/;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location /thumbnails/ {
        alias /var/www/photo/storage/app/public/images/300x200/;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location /debug-images/ {
        alias /var/www/photo/storage/app/public/images/debug/;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # PHP -> Apache
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Port 443;
    }
}
```

### Default конфиг для IP-адреса (phpMyAdmin)

```nginx
server {
    listen 80 default_server;
    server_name _;
    
    # Всё на Apache (для phpMyAdmin по IP)
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

## Активация конфига

```bash
# Создать симлинк
sudo ln -s /etc/nginx/sites-available/SITENAME.conf /etc/nginx/sites-enabled/

# Проверить конфиг
sudo nginx -t

# Перезагрузить Nginx
sudo systemctl reload nginx
```

## Отключение конфига

```bash
# Удалить симлинк
sudo rm /etc/nginx/sites-enabled/SITENAME.conf

# Перезагрузить Nginx
sudo systemctl reload nginx
```

## Полезные команды

```bash
# Проверка конфигурации
sudo nginx -t

# Просмотр всех активных server blocks
sudo nginx -T 2>/dev/null | grep "server_name" | sort -u

# Проверка какие порты слушаются
sudo ss -tlnp | grep nginx

# Логи в реальном времени
sudo tail -f /log/sites/SITENAME-access.log
sudo tail -f /log/sites/SITENAME-error.log

# Перезагрузка без даунтайма
sudo systemctl reload nginx

# Полная перезагрузка
sudo systemctl restart nginx
```

## Настройки производительности

В `/etc/nginx/nginx.conf`:

```nginx
user www-data;
worker_processes auto;  # По числу CPU ядер

events {
    worker_connections 1024;
}

http {
    # Gzip сжатие
    gzip on;
    gzip_vary on;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss;
    
    # Кеширование файловых дескрипторов
    open_file_cache max=1000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    
    # Таймауты
    client_body_timeout 12;
    client_header_timeout 12;
    keepalive_timeout 15;
    send_timeout 10;
    
    # Буферы
    client_body_buffer_size 10K;
    client_header_buffer_size 1k;
    client_max_body_size 8m;
    large_client_header_buffers 4 8k;
}
```

## Безопасность

```nginx
# Скрыть версию Nginx
server_tokens off;

# SSL настройки
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers HIGH:!aNULL:!MD5;
ssl_prefer_server_ciphers on;

# Защита от clickjacking
add_header X-Frame-Options "SAMEORIGIN" always;

# XSS защита
add_header X-XSS-Protection "1; mode=block" always;

# Content type sniffing
add_header X-Content-Type-Options "nosniff" always;

# HSTS (опционально, осторожно!)
# add_header Strict-Transport-Security "max-age=31536000" always;
```

## Troubleshooting

### Nginx не стартует

```bash
# Проверить синтаксис
sudo nginx -t

# Посмотреть логи
sudo journalctl -u nginx -n 50

# Проверить что порт не занят
sudo ss -tlnp | grep :80
sudo ss -tlnp | grep :443
```

### 502 Bad Gateway

Означает что Apache не отвечает на 8080:

```bash
# Проверить что Apache слушает 8080
sudo ss -tlnp | grep :8080

# Проверить Apache
sudo systemctl status apache2
```

### Статика не отдаётся

```bash
# Проверить права на файлы
ls -la /var/www/photo/storage/app/public/images/

# Должно быть:
# drwxrwsr-x www-data web
# -rwxrwxr-x www-data web

# Исправить если нужно
sudo chown -R www-data:web /path/to/images/
sudo chmod -R 775 /path/to/images/
```

## Мониторинг

```bash
# Статус Nginx
sudo systemctl status nginx

# Проверка всех сайтов
for site in photo naturfreunde epsilon art lab promo test traunreut freedomvibe; do
    echo "=== ${site}.freedomvibe.net ==="
    curl -I https://${site}.freedomvibe.net 2>&1 | head -1
done

# Статистика по access логам
sudo cat /log/sites/*-access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -10
```
