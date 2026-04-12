# Nginx + Apache Reverse Proxy Architecture

Документация по архитектуре веб-сервера с Nginx в качестве reverse proxy и Apache для обработки PHP.

## Архитектура

```
Internet (80/443)
    ↓
Nginx (фронтенд)
    ↓
    ├─→ /images/*.jpg → Nginx отдаёт напрямую (БЫСТРО!)
    ├─→ /thumbnails/*.jpg → Nginx отдаёт напрямую
    ├─→ /debug-images/*.jpg → Nginx отдаёт напрямую
    └─→ /*.php → proxy на Apache:8080
           ↓
        Apache (бэкенд)
           ↓
        PHP-FPM (сокеты)
```

## Преимущества

✅ **Производительность:** Статические файлы отдаются Nginx напрямую (в 5-10 раз быстрее)  
✅ **SSL:** Обрабатывается только в Nginx (один сертификат, одна точка шифрования)  
✅ **Масштабируемость:** Apache обрабатывает только PHP (меньше нагрузка)  
✅ **Безопасность:** Nginx как фронтенд фильтрует запросы  
✅ **Гибкость:** Легко добавлять кеширование, rate limiting, gzip

## Порты

- **Nginx:** 80 (HTTP), 443 (HTTPS) - доступен из интернета
- **Apache:** 8080 (HTTP), 8443 (HTTPS закомментирован) - только localhost
- **PHP-FPM:** Unix-сокеты в `/run/php/`

## Структура документации

1. [Настройка Nginx](NGINX_SETUP.md) - конфигурация фронтенда
2. [Настройка Apache](APACHE_SETUP.md) - конфигурация бэкенда
3. [Управление SSL](SSL_CERTIFICATES.md) - Let's Encrypt сертификаты
4. [Решение проблем](TROUBLESHOOTING.md) - частые проблемы и решения

## Быстрый старт

### Проверка статуса

```bash
# Nginx
sudo systemctl status nginx
sudo ss -tlnp | grep :80
sudo ss -tlnp | grep :443

# Apache
sudo systemctl status apache2
sudo ss -tlnp | grep :8080

# PHP-FPM
sudo systemctl status php8.5-fpm
ls -la /run/php/
```

### Перезагрузка сервисов

```bash
# После изменения конфигов Nginx
sudo nginx -t
sudo systemctl reload nginx

# После изменения конфигов Apache
sudo apache2ctl configtest
sudo systemctl restart apache2

# После изменения PHP-FPM пулов
sudo systemctl restart php8.5-fpm
```

## Список сайтов

Все сайты мигрированы на архитектуру Nginx → Apache:

1. **photo.freedomvibe.net** - фото галерея (с оптимизацией статики)
2. **naturfreunde-traunreut.freedomvibe.net** - сайт клуба
3. **epsilon.freedomvibe.net** - проект
4. **art.freedomvibe.net**
5. **lab.freedomvibe.net**
6. **promo.freedomvibe.net**
7. **test.freedomvibe.net**
8. **traunreut.freedomvibe.net**
9. **freedomvibe.net** - главный домен

## Логи

### Nginx
```
/log/sites/SITENAME-access.log
/log/sites/SITENAME-error.log
```

### Apache
```
/log/sites/SITENAME_error.log
/log/sites/SITENAME_access.log
```

### PHP-FPM
```
/var/log/php8.5-fpm.log
```

## Мониторинг

```bash
# Проверка работы всех сайтов
curl -I https://photo.freedomvibe.net
curl -I https://naturfreunde-traunreut.freedomvibe.net
# ... и т.д.

# Проверка памяти
free -h
ps aux --sort=-%mem | head -10

# Проверка нагрузки
htop
```

## Backup конфигураций

```bash
# Nginx
sudo tar -czf nginx-configs-$(date +%Y%m%d).tar.gz /etc/nginx/sites-available/

# Apache
sudo tar -czf apache-configs-$(date +%Y%m%d).tar.gz /etc/apache2/sites-available/

# SSL сертификаты (не забыть!)
sudo tar -czf letsencrypt-$(date +%Y%m%d).tar.gz /etc/letsencrypt/
```

## Автор

Документация создана 2026-04-12  
Сервер: Hetzner CX32 (Ubuntu 24.04)
