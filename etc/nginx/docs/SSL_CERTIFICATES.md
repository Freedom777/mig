# Управление SSL сертификатами

Все SSL сертификаты получены через Let's Encrypt с помощью Certbot.

## Список активных сертификатов

```bash
sudo certbot certificates

# Вывод покажет:
# - Certificate Name
# - Domains
# - Expiry Date
# - Certificate Path
# - Private Key Path
```

## Текущие сертификаты

| Сертификат | Домены | Путь |
|-----------|--------|------|
| art.freedomvibe.net | art.freedomvibe.net, www.art.freedomvibe.net | /etc/letsencrypt/live/art.freedomvibe.net/ |
| epsilon.freedomvibe.net | epsilon.freedomvibe.net, www.epsilon.freedomvibe.net | /etc/letsencrypt/live/epsilon.freedomvibe.net/ |
| freedomvibe.net | freedomvibe.net | /etc/letsencrypt/live/freedomvibe.net/ |
| www.freedomvibe.net | www.freedomvibe.net | /etc/letsencrypt/live/www.freedomvibe.net/ |
| lab.freedomvibe.net | lab.freedomvibe.net, www.lab.freedomvibe.net | /etc/letsencrypt/live/lab.freedomvibe.net/ |
| naturfreunde-traunreut | naturfreunde-traunreut.freedomvibe.net, www.naturfreunde-traunreut.freedomvibe.net | /etc/letsencrypt/live/naturfreunde-traunreut.freedomvibe.net/ |
| photo.freedomvibe.net | photo.freedomvibe.net | /etc/letsencrypt/live/photo.freedomvibe.net/ |
| pma.freedomvibe.net | pma.freedomvibe.net | /etc/letsencrypt/live/pma.freedomvibe.net/ |
| promo.freedomvibe.net | promo.freedomvibe.net, www.promo.freedomvibe.net | /etc/letsencrypt/live/promo.freedomvibe.net/ |
| test.freedomvibe.net | test.freedomvibe.net, www.test.freedomvibe.net | /etc/letsencrypt/live/test.freedomvibe.net/ |
| traunreut.freedomvibe.net | traunreut.freedomvibe.net | /etc/letsencrypt/live/traunreut.freedomvibe.net/ |

## Получение нового сертификата

### Standalone метод (рекомендуется)

Используется когда веб-сервер можно остановить на время:

```bash
# Остановить Nginx
sudo systemctl stop nginx

# Получить сертификат
sudo certbot certonly --standalone -d example.freedomvibe.net -d www.example.freedomvibe.net

# Запустить Nginx
sudo systemctl start nginx
```

### Webroot метод

Используется когда сервер должен продолжать работать:

```bash
# Сертификат получается через временные файлы в webroot
sudo certbot certonly --webroot -w /var/www/example -d example.freedomvibe.net
```

**Примечание:** Для webroot метода Nginx должен отдавать файлы из `.well-known/acme-challenge/`

### Важно про DNS

- Убедись что **A запись** указывает на сервер
- **AAAA запись (IPv6) НЕ используется** - удалена для избежания проблем с certbot
- DNS должен быть разрешён **ДО** запуска certbot

## Автоматическое обновление

Certbot автоматически настраивает обновление через systemd timer:

```bash
# Проверить статус таймера
sudo systemctl status certbot.timer

# Проверить когда последний раз обновлялись сертификаты
sudo journalctl -u certbot -n 50

# Ручное обновление (dry-run)
sudo certbot renew --dry-run

# Ручное обновление
sudo certbot renew
```

Сертификаты обновляются автоматически **за 30 дней до истечения**.

## Проверка сертификата

### Через OpenSSL

```bash
# Локально
sudo openssl x509 -in /etc/letsencrypt/live/example.freedomvibe.net/fullchain.pem -noout -text

# Дата истечения
sudo openssl x509 -in /etc/letsencrypt/live/example.freedomvibe.net/fullchain.pem -noout -dates

# Какой сертификат отдаёт Nginx
openssl s_client -connect example.freedomvibe.net:443 -servername example.freedomvibe.net < /dev/null 2>/dev/null | openssl x509 -noout -text
```

### Через браузер

1. Открой сайт в браузере
2. Кликни на замок в адресной строке
3. "Certificate" → Проверь:
   - Issued to
   - Issued by
   - Valid until

### Online инструменты

- https://www.ssllabs.com/ssltest/ - полный анализ SSL
- https://crt.sh/ - история сертификатов

## Отзыв сертификата

```bash
sudo certbot revoke --cert-path /etc/letsencrypt/live/example.freedomvibe.net/fullchain.pem
```

## Удаление сертификата

```bash
# Удалить сертификат и все файлы
sudo certbot delete --cert-name example.freedomvibe.net
```

## Rate Limits Let's Encrypt

**Важно знать:**

- **50 сертификатов** на registered domain в неделю
- **5 неудачных попыток** авторизации в час (блокирует домен на 1 час)
- **300 новых регистраций аккаунтов** с одного IP в 3 часа

Если превысил лимит - **жди час/день/неделю!**

## Troubleshooting

### DNS problem: NXDOMAIN

**Проблема:** DNS не резолвится

```bash
# Проверь DNS
dig +short example.freedomvibe.net

# Должен вернуть IP сервера
```

### Invalid response 404

**Проблема:** Certbot не может получить доступ к файлам проверки

**Решение:** Используй standalone метод (останови Nginx)

### Too many failed authorizations

**Проблема:** Превышен rate limit (5 попыток в час)

**Решение:** Подожди 1 час и попробуй снова

### Certificate matches wrong domain

**Проблема:** Nginx отдаёт неправильный сертификат (default)

**Решение:** 
```bash
# Проверь порядок конфигов
ls /etc/nginx/sites-enabled/

# Первый конфиг становится default для неопознанных доменов
# Отключи ненужный default
sudo rm /etc/nginx/sites-enabled/default
sudo systemctl reload nginx
```

## Структура директорий Let's Encrypt

```
/etc/letsencrypt/
├── live/                          # Симлинки на текущие сертификаты
│   └── example.freedomvibe.net/
│       ├── fullchain.pem         # Сертификат + промежуточный
│       ├── privkey.pem           # Приватный ключ
│       ├── chain.pem             # Промежуточный сертификат
│       └── cert.pem              # Только сертификат
├── archive/                       # Все версии сертификатов
├── renewal/                       # Конфиги для автообновления
└── accounts/                      # Аккаунт Let's Encrypt
```

## Backup сертификатов

```bash
# Создать backup
sudo tar -czf letsencrypt-backup-$(date +%Y%m%d).tar.gz /etc/letsencrypt/

# Восстановить
sudo tar -xzf letsencrypt-backup-YYYYMMDD.tar.gz -C /
```

**ВАЖНО:** Делай backup регулярно! Потеря приватных ключей = нужно перевыпускать все сертификаты!

## Конвертация сертификатов

### PEM в PFX (для Windows/IIS)

```bash
sudo openssl pkcs12 -export \
  -out certificate.pfx \
  -inkey /etc/letsencrypt/live/example.freedomvibe.net/privkey.pem \
  -in /etc/letsencrypt/live/example.freedomvibe.net/fullchain.pem
```

## Мониторинг истечения

```bash
# Скрипт для проверки всех сертификатов
for cert in /etc/letsencrypt/live/*/fullchain.pem; do
    domain=$(basename $(dirname $cert))
    expiry=$(openssl x509 -in $cert -noout -enddate | cut -d= -f2)
    echo "$domain: expires $expiry"
done
```

## Best Practices

✅ **Автоматическое обновление** включено и работает  
✅ **Backup** сертификатов регулярно  
✅ **Мониторинг** истечения (через systemd timer)  
✅ **Используй fullchain.pem** в Nginx (не cert.pem)  
✅ **Не коммить** приватные ключи в git  
✅ **Тестируй** обновление с `--dry-run` перед продакшеном

## Полезные команды

```bash
# Список всех сертификатов с датами истечения
sudo certbot certificates

# Принудительное обновление конкретного сертификата
sudo certbot renew --cert-name example.freedomvibe.net --force-renewal

# Расширить сертификат (добавить домен)
sudo certbot certonly --cert-name example.freedomvibe.net \
  -d example.freedomvibe.net -d www.example.freedomvibe.net -d new.example.freedomvibe.net

# Проверка конфигурации Nginx перед обновлением
sudo certbot renew --pre-hook "nginx -t" --post-hook "systemctl reload nginx"
```
