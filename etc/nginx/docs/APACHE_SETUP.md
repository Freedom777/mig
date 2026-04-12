# Настройка Apache

Apache работает в режиме бэкенда, обрабатывая только PHP через PHP-FPM на порту 8080.

## Порты

Apache переведён с портов 80/443 на порты 8080/8443:

- **8080** - HTTP (активно используется)
- **8443** - HTTPS (закомментировано, не используется - SSL обрабатывает Nginx)

## Структура конфигураций

```
/etc/apache2/
├── apache2.conf              # Главный конфиг
├── ports.conf                # Listen 8080
├── sites-available/          # Доступные конфиги
│   ├── photo.freedomvibe.net.conf
│   ├── 000-default.conf      # Default для IP (phpMyAdmin)
│   └── ...
└── sites-enabled/            # Активные (симлинки)
```

## ports.conf

```apache
Listen 8080

<IfModule ssl_module>
    Listen 8443
</IfModule>
```

## Базовая структура VirtualHost

### Laravel сайт (с PHP-FPM)

```apache
<VirtualHost *:8080>
    ServerName example.freedomvibe.net
    DocumentRoot /var/www/example/public
    
    <Directory /var/www/example/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # PHP-FPM через Unix socket
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.5-fpm-example.sock|fcgi://localhost"
    </FilesMatch>
    
    ErrorLog /log/sites/example_error.log
    CustomLog /log/sites/example_access.log combined
</VirtualHost>
```

### Простой PHP сайт (freedomvibe.net)

```apache
# Редирект www -> non-www
<VirtualHost *:8080>
    ServerName www.freedomvibe.net
    Redirect permanent / http://freedomvibe.net/
</VirtualHost>

# Основной сайт
<VirtualHost *:8080>
    ServerName freedomvibe.net
    DocumentRoot /var/www/freedomvibe.net
    DirectoryIndex index.php index.html
    
    <Directory /var/www/freedomvibe.net>
        AllowOverride All
        Options FollowSymLinks
        Require all granted
    </Directory>
    
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.5-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    ErrorLog ${APACHE_LOG_DIR}/freedomvibe.net-error.log
    CustomLog ${APACHE_LOG_DIR}/freedomvibe.net-access.log combined
</VirtualHost>
```

### Default конфиг (phpMyAdmin по IP)

```apache
<VirtualHost *:8080>
    ServerName 91.98.79.139
    DocumentRoot /var/www/freedomvibe.net
    
    <Directory /var/www/freedomvibe.net>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Закомментированный HTTPS VirtualHost

В каждом конфиге есть закомментированная секция VirtualHost *:8443 - она НЕ используется, т.к. SSL обрабатывает Nginx:

```apache
#<VirtualHost *:8443>
#    ServerName example.freedomvibe.net
#    DocumentRoot /var/www/example/public
#    
#    <FilesMatch \.php$>
#        SetHandler "proxy:unix:/run/php/php8.5-fpm-example.sock|fcgi://localhost"
#    </FilesMatch>
#    
#    SSLEngine on
#    SSLCertificateFile /etc/letsencrypt/live/example.freedomvibe.net/fullchain.pem
#    SSLCertificateKeyFile /etc/letsencrypt/live/example.freedomvibe.net/privkey.pem
#</VirtualHost>
```

Эти секции можно полностью удалить или оставить закомментированными на будущее.

## PHP-FPM конфигурация

### Типы подключений

**Unix Socket (используется):**
```apache
SetHandler "proxy:unix:/run/php/php8.5-fpm-SITENAME.sock|fcgi://localhost"
```

**TCP Socket (альтернатива, не используется):**
```apache
SetHandler "proxy:fcgi://127.0.0.1:9000"
```

### Проверка доступных сокетов

```bash
ls -la /run/php/

# Вывод:
# php8.5-fpm-photo.sock
# php8.5-fpm-naturfreunde-traunreut.sock
# php8.5-fpm-epsilon.sock
# php8.5-fpm.sock (default)
# и т.д.
```

## MPM Prefork настройки

В `/etc/apache2/mods-enabled/mpm_prefork.conf`:

```apache
<IfModule mpm_prefork_module>
    StartServers             2
    MinSpareServers          2
    MaxSpareServers          5
    MaxRequestWorkers        15
    MaxConnectionsPerChild   0
</IfModule>
```

Эти настройки оптимизированы для экономии памяти (Apache только для PHP, статику отдаёт Nginx).

## Полезные команды

```bash
# Проверка конфигурации
sudo apache2ctl configtest

# Просмотр всех VirtualHost
sudo apache2ctl -S

# Проверка загруженных модулей
apache2ctl -M

# Включить/выключить сайт
sudo a2ensite SITENAME.conf
sudo a2dissite SITENAME.conf

# Включить/выключить модуль
sudo a2enmod rewrite
sudo a2dismod STATUS

# Перезагрузка
sudo systemctl restart apache2

# Проверка логов
sudo tail -f /var/log/apache2/error.log
sudo tail -f /log/sites/SITENAME_error.log
```

## Необходимые модули

```bash
# Включить необходимые модули
sudo a2enmod proxy
sudo a2enmod proxy_fcgi
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Перезагрузить Apache
sudo systemctl restart apache2
```

## Важные заметки

### 1. Редиректы HTTP → HTTPS

**ВАЖНО:** Редиректы из Apache VirtualHost *:8080 **УБРАНЫ**!

Nginx сам делает редирект HTTP → HTTPS. Если оставить редирект в Apache, получится бесконечный редирект.

**НЕПРАВИЛЬНО (вызовет цикл):**
```apache
<VirtualHost *:8080>
    ServerName example.freedomvibe.net
    # RewriteEngine on  ← УБРАТЬ!
    # RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>
```

**ПРАВИЛЬНО:**
```apache
<VirtualHost *:8080>
    ServerName example.freedomvibe.net
    DocumentRoot /var/www/example/public
    # Никаких редиректов!
</VirtualHost>
```

### 2. PHP-FPM обязателен для VirtualHost *:8080

Каждый VirtualHost *:8080 **ДОЛЖЕН** иметь секцию `<FilesMatch \.php$>`, иначе Apache будет отдавать PHP код как текст!

### 3. Права на файлы

```bash
# Все файлы Laravel/PHP сайтов
sudo chown -R www-data:web /var/www/SITENAME/

# Права на директории
sudo chmod 775 /var/www/SITENAME/storage
sudo chmod 775 /var/www/SITENAME/bootstrap/cache
```

## Troubleshooting

### Apache показывает список файлов вместо index.php

**Проблема:** В `<Directory>` есть `Options Indexes`

**Решение:**
```apache
<Directory /var/www/example>
    Options FollowSymLinks  # БЕЗ Indexes!
    # ИЛИ
    Options -Indexes FollowSymLinks
</Directory>
```

### PHP код отдаётся как текст

**Проблема:** Нет `<FilesMatch \.php$>` или неправильный сокет

**Решение:** Проверь что есть:
```apache
<FilesMatch \.php$>
    SetHandler "proxy:unix:/run/php/php8.5-fpm-CORRECT-SOCK.sock|fcgi://localhost"
</FilesMatch>
```

И что сокет существует:
```bash
ls -la /run/php/php8.5-fpm-CORRECT-SOCK.sock
```

### 503 Service Unavailable

**Проблема:** PHP-FPM сокет не существует или не работает

**Решение:**
```bash
# Проверить PHP-FPM
sudo systemctl status php8.5-fpm

# Проверить сокет
ls -la /run/php/

# Перезапустить PHP-FPM
sudo systemctl restart php8.5-fpm
```

### Default VirtualHost перехватывает все запросы

**Проблема:** В `000-default.conf` нет `ServerName`

**Решение:** Добавить `ServerName` в default:
```apache
<VirtualHost *:8080>
    ServerName 91.98.79.139  # IP-адрес сервера
    # ...
</VirtualHost>
```

## Миграция сайта на новую архитектуру

При добавлении нового сайта:

1. **Создать VirtualHost *:8080** с PHP-FPM
2. **Убрать редиректы** на HTTPS из Apache
3. **Создать Nginx конфиг** с SSL и proxy
4. **Обновить .env** Laravel (APP_URL, ASSET_URL)
5. **Перезагрузить сервисы**

Используй скрипт `migrate_site_to_nginx.sh` для автоматизации!

## Мониторинг

```bash
# Статус Apache
sudo systemctl status apache2

# Какие порты слушает
sudo ss -tlnp | grep apache2

# Количество процессов
ps aux | grep apache2 | wc -l

# Память Apache
ps aux | grep apache2 | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Логи ошибок
sudo tail -f /var/log/apache2/error.log
```
