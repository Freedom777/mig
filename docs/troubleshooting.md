# Troubleshooting

## Джобы не выполняются

### Симптомы

- Изображения ставятся в очередь, но не обрабатываются
- Статус всегда "in queue"

### Решение

```bash
# Проверить статус workers
sudo supervisorctl status

# Посмотреть логи
tail -f storage/logs/laravel.log
tail -f /var/log/supervisor/photo-*.log

# Проверить очередь вручную
php artisan queue:work rabbitmq --queue=images --once
```

### Проверить RabbitMQ

```bash
# Статус
sudo systemctl status rabbitmq-server

# Список очередей
sudo rabbitmqctl list_queues
```

---

## Дубликаты не находятся

### Симптомы

- Одинаковые изображения не помечаются как дубликаты
- `parent_id` всегда `null`

### Решение

1. **Проверить threshold**

```php
// config/image.php
'processing' => [
    'phash_distance_threshold' => 5,  // Попробовать 8-10
],
```

2. **Проверить поле phash**

```bash
php artisan tinker
>>> Image::whereNotNull('phash')->count()
```

3. **Пересчитать pHash**

```bash
php artisan images:phashes
```

---

## Face API не отвечает

### Симптомы

- `FaceProcessJob` падает с таймаутом
- Ошибка "Face API failed: HTTP 500"

### Решение

```bash
# Проверить статус
systemctl status face-api

# Health check
curl http://127.0.0.1:5000/health

# Логи
tail -f /var/log/face-api/error.log

# Перезапустить
sudo systemctl restart face-api
```

### Проверить память

Face API требует много RAM:

```bash
free -h
htop
```

---

## Thumbnails не создаются

### Симптомы

- `thumbnail_path` остаётся `null`
- Ошибка "Source image not found"

### Решение

1. **Проверить права**

```bash
ls -la storage/app/private/images/
# Должно быть drwxr-xr-x

# Исправить
chmod -R 755 storage/app/private/images/
```

2. **Проверить Imagick**

```bash
php -m | grep imagick
# Должно вывести: imagick
```

3. **Проверить путь**

```bash
php artisan tinker
>>> $image = Image::find(123);
>>> app(ImagePathServiceInterface::class)->getImagePathByObj($image)
>>> file_exists($path)
```

---

## Geolocation падает с rate limit

### Симптомы

- Ошибка "HTTP 429 Too Many Requests"
- "Failed to get address from Nominatim API"

### Решение

1. **Уменьшить workers**

```ini
# /etc/supervisor/conf.d/photo-geolocation.conf
numprocs=1  # ОБЯЗАТЕЛЬНО 1!
```

2. **Увеличить интервал**

```php
// GeolocationProcessJob.php
private const NOMINATIM_RATE_LIMIT_SECONDS = 3;  // Было 2
```

3. **Перезапустить**

```bash
sudo supervisorctl restart photo-geolocation:*
```

---

## Lock timeout

### Симптомы

- "Could not acquire lock for X processing"
- Джоба уходит в release

### Решение

1. **Проверить зависшие locks**

```bash
php artisan tinker
>>> Cache::forget('face-processing:123')
```

2. **Очистить все locks**

```bash
php artisan cache:clear
```

3. **Увеличить timeout (если нужно)**

```php
// В джобе
$lock = Cache::lock($lockKey, 600);  // 10 минут
```

---

## Ошибки HexCast

### Симптомы

- "Argument must be of type string"
- Странные значения в `hash`/`phash`

### Решение

1. **Проверить формат**

```bash
php artisan tinker
>>> $image = Image::find(123);
>>> $image->getRawOriginal('hash')  // binary
>>> $image->hash                     // hex string
```

2. **Если данные повреждены**

```php
// Пересчитать для конкретного изображения
$path = $pathService->getImagePathByObj($image);
$md5 = md5_file($path);
$image->update(['hash' => $md5]);
```

---

## Debug файлы занимают много места

### Симптомы

- Директория `debug/` растёт
- Много неиспользуемых файлов

### Решение

```bash
# Посмотреть размер
du -sh storage/app/private/images/debug/

# Dry-run — посмотреть что удалится
php artisan images:cleanup-unused --dry-run

# Удалить
php artisan images:cleanup-unused
```

---

## Полезные команды

### Проверка целостности

```bash
php artisan images:check
```

### Статистика очередей

```bash
sudo rabbitmqctl list_queues name messages consumers
```

### Мониторинг в реальном времени

```bash
# Логи Laravel
tail -f storage/logs/laravel.log | grep -E "(ERROR|WARNING|Image|Face|Thumbnail)"

# Supervisor
sudo supervisorctl tail -f photo-images:00
```

### Сброс состояния

```bash
# Очистить очереди
php artisan queue:clear rabbitmq --queue=images
php artisan queue:clear rabbitmq --queue=thumbnails

# Очистить кеш
php artisan cache:clear

# Очистить таблицу дедупликации
php artisan tinker
>>> DB::table('queues')->truncate()
```

---

## Логирование

### Включить debug mode

```env
IMAGE_PROCESSING_DEBUG=true
```

### Смотреть только image-related

```bash
tail -f storage/logs/laravel.log | grep -E "\[DRY-RUN\]|Image|Face|Thumbnail|Metadata|Geolocation"
```

### Структурированные логи (если настроено)

```bash
cat storage/logs/laravel.log | jq 'select(.context.image_id == 123)'
```
