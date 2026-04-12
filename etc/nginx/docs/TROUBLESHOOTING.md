# Troubleshooting

Решения частых проблем при работе с архитектурой Nginx → Apache.

## Общие проблемы

### Сайт не открывается

**Симптомы:** Timeout, connection refused

**Диагностика:**
```bash
# Проверить что Nginx работает
sudo systemctl status nginx
sudo ss -tlnp | grep :443

# Проверить что Apache работает
sudo systemctl status apache2
sudo ss -tlnp | grep :8080

# Проверить DNS
dig +short example.freedomvibe.net

# Проверить firewall
sudo ufw status
# Должны быть открыты: 80, 443
```

**Решение:**
```bash
# Запустить сервисы
sudo systemctl start nginx
sudo systemctl start apache2

# Открыть порты в firewall
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

---

## Nginx проблемы

### 502 Bad Gateway

**Причина:** Apache не отвечает на 8080

**Диагностика:**
```bash
# Проверить Apache
sudo systemctl status apache2

# Проверить что слушает 8080
sudo ss -tlnp | grep :8080

# Попробовать напрямую
curl http://127.0.0.1:8080 -H "Host: example.freedomvibe.net"
```

**Решение:**
```bash
# Перезапустить Apache
sudo systemctl restart apache2

# Проверить логи
sudo tail -50 /var/log/apache2/error.log
```

### 504 Gateway Timeout

**Причина:** PHP скрипт выполняется слишком долго

**Решение в Nginx:**
```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_read_timeout 300s;  # Увеличить таймаут
    proxy_connect_timeout 300s;
}
```

**Решение в PHP-FPM:**
```ini
# /etc/php/8.5/fpm/pool.d/www.conf
request_terminate_timeout = 300
```

### Mixed Content (HTTP на HTTPS странице)

**Причина:** Laravel генерирует HTTP URL вместо HTTPS

**Решение:**
```bash
# В .env
APP_URL=https://example.freedomvibe.net
ASSET_URL=https://example.freedomvibe.net

# Очистить кеш
php artisan config:clear
php artisan cache:clear
```

### SSL Certificate Errors

**"Certificate not valid for this domain"**

**Диагностика:**
```bash
# Какой сертификат отдаётся
openssl s_client -connect example.freedomvibe.net:443 -servername example.freedomvibe.net < /dev/null 2>/dev/null | openssl x509 -noout -text | grep -A1 "Subject Alternative"
```

**Решение:**
```bash
# Проверить что в Nginx правильный путь к сертификату
sudo nginx -T 2>/dev/null | grep -A2 "server_name example.freedomvibe.net"

# Получить правильный сертификат
sudo certbot certonly --standalone -d example.freedomvibe.net
```

### Nginx не стартует

**"Address already in use"**

**Диагностика:**
```bash
# Что занимает порт 80
sudo ss -tlnp | grep :80

# Или 443
sudo ss -tlnp | grep :443
```

**Решение:**
```bash
# Если Apache занимает 80/443
# Проверить что Apache слушает только 8080
grep "Listen" /etc/apache2/ports.conf
# Должно быть: Listen 8080

# Перезапустить Apache
sudo systemctl restart apache2
```

---

## Apache проблемы

### PHP код отдаётся как текст

**Причина:** Нет `<FilesMatch \.php$>` в VirtualHost

**Решение:**
```apache
<VirtualHost *:8080>
    ServerName example.freedomvibe.net
    DocumentRoot /var/www/example
    
    # ДОБАВИТЬ:
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.5-fpm-example.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

### 503 Service Unavailable

**Причина:** PHP-FPM сокет не существует или не работает

**Диагностика:**
```bash
# Проверить что сокет существует
ls -la /run/php/ | grep example

# Проверить PHP-FPM
sudo systemctl status php8.5-fpm

# Логи
sudo tail -50 /var/log/php8.5-fpm.log
```

**Решение:**
```bash
# Перезапустить PHP-FPM
sudo systemctl restart php8.5-fpm

# Или создать пул если не существует
# (см. документацию PHP-FPM)
```

### Apache показывает список файлов вместо index.php

**Причина:** `Options Indexes` включён

**Решение:**
```apache
<Directory /var/www/example>
    Options FollowSymLinks  # БЕЗ Indexes!
    AllowOverride All
    Require all granted
</Directory>
```

### Бесконечный редирект (Redirect Loop)

**Причина:** Apache редиректит HTTP→HTTPS, а Nginx тоже

**Решение:** Убрать редирект из Apache VirtualHost *:8080:
```apache
<VirtualHost *:8080>
    ServerName example.freedomvibe.net
    # RewriteEngine on  ← ЗАКОММЕНТИРОВАТЬ
    # RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>
```

### Default VirtualHost перехватывает все домены

**Причина:** В `000-default.conf` нет ServerName

**Решение:**
```apache
<VirtualHost *:8080>
    ServerName 91.98.79.139  # IP сервера
    DocumentRoot /var/www/html
</VirtualHost>
```

---

## PHP-FPM проблемы

### PHP-FPM не стартует

**Диагностика:**
```bash
sudo systemctl status php8.5-fpm
sudo journalctl -u php8.5-fpm -n 50
```

**Частые причины:**
- Синтаксическая ошибка в конфиге пула
- Порт/сокет уже занят
- Недостаточно прав на сокет

**Решение:**
```bash
# Проверить синтаксис
sudo php-fpm8.5 -t

# Права на директорию сокетов
sudo chown -R www-data:www-data /run/php/
```

### Memory Limit Exceeded

**Симптомы:** 500 ошибка, "Allowed memory size exhausted" в логах

**Решение:**
```bash
# В php.ini
sudo nano /etc/php/8.5/fpm/php.ini

# Увеличить
memory_limit = 256M

# Перезапустить
sudo systemctl restart php8.5-fpm
```

---

## SSL проблемы

### Let's Encrypt: Too many failed authorizations

**Причина:** Превышен rate limit (5 попыток в час)

**Решение:** Подожди 1 час, потом:
```bash
# Используй standalone метод
sudo systemctl stop nginx
sudo certbot certonly --standalone -d example.freedomvibe.net
sudo systemctl start nginx
```

### Certbot: DNS problem NXDOMAIN

**Причина:** DNS не указывает на сервер

**Диагностика:**
```bash
dig +short example.freedomvibe.net
# Должен вернуть: 91.98.79.139
```

**Решение:** Исправь A запись в DNS (Hetzner)

### Certbot: Invalid response 404

**Причина:** Nginx/Apache не отдаёт файлы проверки

**Решение:** Используй standalone:
```bash
sudo systemctl stop nginx
sudo certbot certonly --standalone -d example.freedomvibe.net
sudo systemctl start nginx
```

---

## Performance проблемы

### Сайт загружается медленно

**Диагностика:**
```bash
# Проверить время ответа
time curl -I https://example.freedomvibe.net

# Проверить нагрузку
htop

# Проверить MySQL
sudo systemctl status mysql
```

**Решение:**
```bash
# Включить gzip в Nginx
# (уже должен быть включён)

# Проверить кеш браузера (Cache-Control заголовки)
curl -I https://example.freedomvibe.net/images/test.jpg | grep Cache

# Оптимизировать MySQL/Laravel кеш
```

### Высокое использование памяти

**Диагностика:**
```bash
free -h
ps aux --sort=-%mem | head -10
```

**Решение:**
```bash
# Уменьшить Apache MaxRequestWorkers
sudo nano /etc/apache2/mods-enabled/mpm_prefork.conf
# MaxRequestWorkers 15

# Уменьшить PHP-FPM процессы
sudo nano /etc/php/8.5/fpm/pool.d/www.conf
# pm.max_children = 5

sudo systemctl restart apache2
sudo systemctl restart php8.5-fpm
```

---

## Laravel проблемы

### 500 Internal Server Error

**Диагностика:**
```bash
# Laravel логи
tail -50 /var/www/example/storage/logs/laravel.log

# Apache логи
sudo tail -50 /log/sites/example_error.log

# PHP-FPM логи
sudo tail -50 /var/log/php8.5-fpm.log
```

**Частые причины:**
- Ошибка в коде
- .env не настроен
- Нет прав на storage/bootstrap/cache
- APP_KEY не сгенерирован

**Решение:**
```bash
# Права
sudo chown -R www-data:web /var/www/example/storage
sudo chmod -R 775 /var/www/example/storage

# Очистить кеш
php artisan config:clear
php artisan cache:clear

# Сгенерировать APP_KEY
php artisan key:generate
```

### Permission Denied

**Симптомы:** Ошибки записи в storage/logs или cache

**Решение:**
```bash
# Владелец
sudo chown -R www-data:web /var/www/example/

# Права на директории
sudo chmod -R 775 /var/www/example/storage
sudo chmod -R 775 /var/www/example/bootstrap/cache

# Setgid на storage
sudo chmod g+s /var/www/example/storage
```

---

## Права доступа

### www-data не может писать файлы

**Диагностика:**
```bash
# Проверить владельца
ls -la /var/www/example/

# Попробовать от www-data
sudo -u www-data touch /var/www/example/storage/test.txt
```

**Решение:**
```bash
# Исправить владельца
sudo chown -R www-data:web /var/www/example/

# Setgid на директории
sudo chmod g+s /var/www/example/storage

# Права
sudo chmod 775 /var/www/example/storage
```

---

## Логи и отладка

### Где искать логи

```bash
# Nginx
/log/sites/SITENAME-access.log
/log/sites/SITENAME-error.log
/var/log/nginx/error.log

# Apache
/log/sites/SITENAME_error.log
/var/log/apache2/error.log

# PHP-FPM
/var/log/php8.5-fpm.log

# Laravel
/var/www/SITENAME/storage/logs/laravel.log

# MySQL
/var/log/mysql/error.log

# System
sudo journalctl -u nginx -n 50
sudo journalctl -u apache2 -n 50
```

### Включить debug режим

**Nginx:**
```nginx
# В http блоке
error_log /var/log/nginx/error.log debug;
```

**Laravel:**
```bash
# .env
APP_DEBUG=true
LOG_LEVEL=debug
```

⚠️ **НЕ оставляй APP_DEBUG=true на продакшене!**

---

## Полезные команды для диагностики

```bash
# Какие порты слушаются
sudo ss -tlnp

# Все процессы веб-сервера
ps aux | grep -E 'nginx|apache|php-fpm'

# Память по процессам
ps aux --sort=-%mem | head -20

# Проверка всех конфигов
sudo nginx -t
sudo apache2ctl configtest

# Логи в реальном времени
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/apache2/error.log

# Тест доступности
curl -I https://example.freedomvibe.net

# Проверка SSL
openssl s_client -connect example.freedomvibe.net:443 -servername example.freedomvibe.net

# DNS резолвинг
dig +short example.freedomvibe.net
nslookup example.freedomvibe.net
```

---

## Экстренное восстановление

### Всё сломалось - быстрое восстановление

```bash
# 1. Перезагрузить все сервисы
sudo systemctl restart nginx
sudo systemctl restart apache2
sudo systemctl restart php8.5-fpm
sudo systemctl restart mysql

# 2. Проверить что всё запустилось
sudo systemctl status nginx
sudo systemctl status apache2
sudo systemctl status php8.5-fpm

# 3. Проверить порты
sudo ss -tlnp | grep -E ':80|:443|:8080'

# 4. Проверить логи
sudo tail -50 /var/log/nginx/error.log
sudo tail -50 /var/log/apache2/error.log

# 5. Тест сайта
curl -I https://photo.freedomvibe.net
```

### Откатить конфиг Nginx

```bash
# Восстановить из backup
sudo cp /path/to/backup/nginx-site.conf /etc/nginx/sites-available/
sudo nginx -t
sudo systemctl reload nginx
```

### Откатить конфиг Apache

```bash
# Восстановить из backup
sudo cp /path/to/backup/apache-site.conf /etc/apache2/sites-available/
sudo apache2ctl configtest
sudo systemctl restart apache2
```
