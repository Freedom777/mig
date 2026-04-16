# WebP Migration

Автоматическая конвертация JPG → WebP с удалением оригиналов.

## Обзор

Система автоматически конвертирует все загруженные JPG изображения в формат WebP после завершения всех jobs обработки. WebP обеспечивает меньший размер файлов (обычно 25-35% экономии) при сохранении качества.

## Архитектура

```
Upload JPG
  ↓
ImageService (быстрое извлечение GPS/taken_at через exif_read_data)
  ↓
ImageQueueDispatcher (параллельный dispatch)
  ↓
┌──────────────┬──────────────┬──────────────┐
│              │              │              │
▼              ▼              ▼              ▼
Thumbnail    Metadata       Face       Geolocation
(из JPG)     (из JPG)    (из JPG)        (отдельно)
  │              │              │
  └──────┬───────┴──────┬───────┘
         ↓              ↓
  event(ImageJobCompleted)
         ↓
CheckAndDispatchImageProcessing (Listener)
  - Проверяет: metadata ✅ && faces_checked ✅ && thumbnail ✅
  - Защита от двойного запуска: hash/phash еще NULL
         ↓
  ImageProcessJob (запускается ПОСЛЕДНЕЙ)
    1. Вычисление MD5 hash из JPG
    2. Вычисление pHash из JPG  
    3. Поиск дубликатов
    4. Конвертация JPG → WebP (quality=90)
    5. Обновление filename в БД на .webp
    6. Удаление JPG файла
```

## Почему Event-Driven?

**Проблема с Chain:**
```php
Bus::chain([
    ThumbnailJob,    // 2 сек
    MetadataJob,     // 0.5 сек
    FaceJob,         // 6 сек
    ImageProcessJob  // 3 сек
]) // ИТОГО: 11.5 сек последовательно
```

**Решение — Parallel + Event:**
```php
// Параллельно (6 сек — самая медленная job)
ThumbnailJob::dispatch()->onQueue('thumbnails');
MetadataJob::dispatch()->onQueue('metadatas');
FaceJob::dispatch()->onQueue('faces');

// Каждая job после завершения:
event(new ImageJobCompleted($imageId, 'thumbnail'));

// Listener проверяет готовность и запускает:
ImageProcessJob::dispatch(); // +3 сек
// ИТОГО: ~9 сек вместо 11.5 сек
```

## Event-Driven Компоненты

### Event: ImageJobCompleted

```php
namespace App\Events;

class ImageJobCompleted
{
    public int $imageId;
    public string $jobType; // 'thumbnail', 'metadata', 'face'
    
    public function __construct(int $imageId, string $jobType)
    {
        $this->imageId = $imageId;
        $this->jobType = $jobType;
    }
}
```

**Dispatch:**
```php
// В конце каждой job (после успешного выполнения):
event(new ImageJobCompleted($imageId, 'thumbnail'));
```

### Listener: CheckAndDispatchImageProcessing

```php
namespace App\Listeners;

class CheckAndDispatchImageProcessing
{
    public function handle(ImageJobCompleted $event): void
    {
        $image = Image::find($event->imageId);
        
        // Проверка готовности
        if ($this->isReadyForProcessing($image)) {
            ImageProcessJob::dispatch(['image_id' => $event->imageId])
                ->onQueue('images');
        }
    }
    
    private function isReadyForProcessing(Image $image): bool
    {
        // Защита от двойного запуска
        if ($image->hash || $image->phash) {
            return false;
        }
        
        // Все jobs завершены?
        return $image->metadata !== null
            && $image->faces_checked === true
            && $image->thumbnail_filename !== null;
    }
}
```

### Регистрация в EventServiceProvider

```php
namespace App\Providers;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ImageJobCompleted::class => [
            CheckAndDispatchImageProcessing::class,
        ],
    ];
}
```

## Hash вычисление ДО конвертации

**Важно:** Hash вычисляется из JPG, НЕ из WebP!

### Причины:

1. **MD5** — hash файла, JPG ≠ WebP → разные MD5
2. **pHash** — визуальный hash, JPG ≈ WebP, но лучше из JPG для совместимости

### Алгоритм ImageProcessJob:

```php
// 1. Получаем путь к JPG
$jpgPath = $pathService->getImagePathByObj($image);

// 2. Вычисляем MD5 из JPG
$md5 = md5_file($jpgPath);

// 3. Вычисляем pHash из JPG
$hasher = new ImageHash(new PerceptualHash());
$phashHex = $hasher->hash($jpgPath)->toHex();

// 4. Ищем дубликаты
$duplicateId = Image::where('hash', hex2bin($md5))->value('id');
if (!$duplicateId) {
    $duplicateId = $repo->findSimilarByPhash($phashHex, 5);
}

// 5. Сохраняем hash в БД
$image->update(['hash' => $md5, 'phash' => $phashHex]);

// 6. Конвертируем JPG → WebP
$this->convertToWebp($image, $pathService, $jpgPath);
```

## WebP Конвертация

### Intervention Image v4 API:

```php
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

$manager = new ImageManager(new Driver());
$img = $manager->decodePath($jpgPath); // v4: decodePath(), не read()

$quality = config('image.webp.quality.image', 90);
$webpEncoded = $img->encodeUsingFormat(Format::WEBP, quality: $quality);

file_put_contents($webpPath, (string) $webpEncoded);
```

### Quality настройки:

| Тип | Config Key | Default | Описание |
|-----|-----------|---------|----------|
| Основные изображения | `image.webp.quality.image` | 90 | Финальный WebP |
| Thumbnails | `image.webp.quality.thumbnail` | 85 | Миниатюры |
| Debug (Face API) | `image.webp.quality.debug` | 80 | Debug изображения |

### Удаление JPG:

```php
// Обновляем filename в БД
$image->filename = pathinfo($image->filename, PATHINFO_FILENAME) . '.webp';
$image->save();

// Удаляем оригинальный JPG
unlink($jpgPath);
```

## Thumbnails в WebP

ThumbnailProcessJob создаёт миниатюры сразу в WebP формате:

```php
// Intervention Image v4
$img = $manager->decodePath($imagePath);

// Resize
$resized = $img->cover($width, $height);

// Encode to WebP
$quality = config('image.webp.quality.thumbnail', 85);
$encoded = $resized->encodeUsingFormat(Format::WEBP, quality: $quality);

// Save
file_put_contents($thumbnailPath, (string) $encoded);
```

## Face API Debug Images

Face API сохраняет debug изображения (с рамками вокруг лиц) сразу в WebP:

```python
# server.py
def save_debug_image(img, faces, image_path, webp_quality=80):
    # Draw rectangles around faces
    for (x, y, w, h) in faces:
        cv2.rectangle(img, (x, y), (x+w, y+h), (0, 255, 0), 2)
    
    # Save as WebP
    debug_filename = f"debug_{name_without_ext}.webp"
    img.save(debug_path, format='WEBP', quality=webp_quality)
```

## ImageHash Пакет — Intervention Image v4 Адаптация

**Проблема:** `jenssegers/imagehash` несовместим с Intervention Image v4

### Исправления в /var/www/photo/packages/imagehash/:

#### 1. ImageHash.php

```php
// БЫЛО (v3):
$image = $this->driver->read($image);

// СТАЛО (v4):
$image = $this->driver->decodePath($image);
```

#### 2. PerceptualHash.php

```php
// БЫЛО (v3):
$rgb = $resized->pickColor($x, $y);
$r = $rgb[0];

// СТАЛО (v4):
$colorCollection = $resized->colorsAt($x, $y);
$color = $colorCollection->first();
$r = $color->red()->value();   // Метод value(), не свойство
$g = $color->green()->value();
$b = $color->blue()->value();
```

### Ключевые изменения API v4:

| v3 | v4 | Описание |
|----|-----|----------|
| `read($path)` | `decodePath($path)` | Загрузка изображения |
| `pickColor($x, $y)` | `colorsAt($x, $y)->first()` | Получение цвета пикселя |
| `$color[0]` | `$color->red()->value()` | RGB каналы |
| `toWebp()` | `encodeUsingFormat(Format::WEBP)` | WebP encoding |

## RabbitMQ — Laravel 13 Compatibility

**Проблема:** `vladimir-yuldashev/laravel-queue-rabbitmq` v14.4.0 несовместим с Laravel 13

### Решение — Fork:

Создан форк в `/var/www/photo/packages/laravel-queue-rabbitmq/`

**Добавленные методы в RabbitMQQueue.php:**

```php
public function pendingSize($queue = null): int { return 0; }
public function delayedSize($queue = null): int { return 0; }
public function reservedSize($queue = null): int { return 0; }
public function failedSize($queue = null): int { return 0; }
public function creationTimeOfOldestPendingJob($queue = null): ?int { return null; }
```

**Обновлён Consumer.php:**

```php
// Добавлен третий параметр $reason
public function stop($status = 0, $options = null, $reason = null)
```

**composer.json:**

```json
{
  "repositories": [
    {"type": "path", "url": "./packages/laravel-queue-rabbitmq"}
  ],
  "require": {
    "vladimir-yuldashev/laravel-queue-rabbitmq": "dev-master"
  }
}
```

## Supervisor Configuration

Workers для параллельной обработки:

```ini
[group:photo-workers]
programs=photo-images-worker,photo-thumbnails-worker,photo-metadatas-worker,photo-geolocations-worker,photo-faces-worker
priority=999

[program:photo-images-worker]
command=php /var/www/photo/artisan queue:work rabbitmq --queue=images --sleep=3 --tries=3
numprocs=2  # Для ImageProcessJob

[program:photo-thumbnails-worker]
command=php /var/www/photo/artisan queue:work rabbitmq --queue=thumbnails --sleep=3 --tries=3
numprocs=2  # Параллельная генерация thumbnails

[program:photo-metadatas-worker]
command=php /var/www/photo/artisan queue:work rabbitmq --queue=metadatas --sleep=3 --tries=3
numprocs=1  # exiftool быстрый

[program:photo-faces-worker]
command=php /var/www/photo/artisan queue:work rabbitmq --queue=faces --sleep=3 --tries=3
numprocs=4  # Face API медленный

[program:photo-geolocations-worker]
command=php /var/www/photo/artisan queue:work rabbitmq --queue=geolocations --sleep=3 --tries=3
numprocs=1  # Геолокация быстрая
```

## Производительность

### До (Chain):

```
ThumbnailJob:  2 сек  ────────┐
MetadataJob:  0.5 сек         │ Последовательно
FaceJob:       6 сек          │
ImageProcessJob: 3 сек ───────┘
ИТОГО: 11.5 секунд
```

### После (Parallel + Event):

```
ThumbnailJob:  2 сек  ┐
MetadataJob:  0.5 сек  ├─ Параллельно (6 сек)
FaceJob:       6 сек  ┘
         ↓
ImageProcessJob: 3 сек
ИТОГО: ~9 секунд (-22% времени)
```

## Troubleshooting

### JPG не удаляется

**Проблема:** ImageProcessJob завершается с ошибкой

**Проверка:**
```bash
# Смотрим логи
tail -100 /var/www/photo/storage/logs/laravel.log | grep ImageProcessJob

# Проверяем что все jobs завершились
php artisan tinker
$image = Image::find(123);
$image->metadata; // Должен быть не null
$image->faces_checked; // Должен быть true
$image->thumbnail_filename; // Должен быть не null
```

### ImageProcessJob не запускается

**Проблема:** Listener не срабатывает

**Проверка:**
```bash
# Event зарегистрирован?
php artisan event:list | grep ImageJobCompleted

# Очистить кэш events
php artisan event:cache
php artisan optimize:clear
sudo supervisorctl restart photo-workers:*
```

### pHash ошибка после обновления Intervention Image

**Проблема:** `pickColor()` / `read()` не существует

**Решение:** Проверь что все патчи в `/var/www/photo/packages/imagehash/` применены

## См. также

- [Architecture](architecture.md) — общая архитектура
- [Image Module](modules/image.md) — ImageProcessJob детали
- [Thumbnail Module](modules/thumbnail.md) — ThumbnailProcessJob
- [Configuration](configuration.md) — настройки WebP quality
