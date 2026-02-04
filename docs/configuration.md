# Конфигурация

Вся конфигурация модуля находится в `config/image.php`.

## Режимы обработки

### IMAGE_PROCESSING_MODE

Основной режим выполнения джобов.

| Значение | Описание |
|----------|----------|
| `queue` | Джобы отправляются в очередь RabbitMQ (production) |
| `sync` | Джобы выполняются немедленно (тестирование) |
| `disabled` | Обработка отключена (maintenance) |

```env
IMAGE_PROCESSING_MODE=queue
```

### IMAGE_PROCESSING_DRY_RUN

Режим "сухого запуска" — только логирование без выполнения.

```env
IMAGE_PROCESSING_DRY_RUN=true
```

Пример лога:
```
[DRY-RUN] Would queue Image job {"image_id": 123, "queue": "images"}
[DRY-RUN] Would queue Thumbnail job {"image_id": 123, "queue": "thumbnails"}
```

### IMAGE_PROCESSING_DEBUG

Подробное логирование для отладки.

```env
IMAGE_PROCESSING_DEBUG=true
```

Что логируется:
- Параметры каждой джобы
- Время выполнения (для sync режима)
- Stack trace при ошибках
- Статусы постановки в очередь

## Комбинации режимов

| mode | dry_run | debug | Результат |
|------|---------|-------|-----------|
| `queue` | `false` | `false` | Production — обычная работа |
| `queue` | `false` | `true` | Production с мониторингом |
| `queue` | `true` | `false` | Посмотреть что будет обработано |
| `queue` | `true` | `true` | Подробный dry-run |
| `sync` | `false` | `false` | Немедленное выполнение |
| `sync` | `false` | `true` | Немедленное + профилирование |
| `disabled` | `*` | `*` | Всё пропускается |

## Параметры путей

```php
'paths' => [
    'disk' => env('IMAGE_DISK', 'private'),
    'images' => env('IMAGE_PATH', 'images'),
    'debug_subdir' => env('IMAGE_DEBUG_SUBDIR', 'debug'),
],
```

| Параметр | Описание | По умолчанию |
|----------|----------|--------------|
| `disk` | Storage disk | `private` |
| `images` | Базовая директория | `images` |
| `debug_subdir` | Поддиректория для debug-изображений | `debug` |

## Параметры Thumbnails

```php
'thumbnails' => [
    'width' => env('THUMBNAIL_WIDTH', 300),
    'height' => env('THUMBNAIL_HEIGHT', 200),
    'method' => env('THUMBNAIL_METHOD', 'cover'),
    'dir_format' => env('THUMBNAIL_DIR_FORMAT', '{width}x{height}'),
    'postfix' => env('THUMBNAIL_POSTFIX', '_{method}_{width}x{height}'),
],
```

| Параметр | Описание | По умолчанию |
|----------|----------|--------------|
| `width` | Ширина thumbnail | `300` |
| `height` | Высота thumbnail | `200` |
| `method` | Метод ресайза | `cover` |
| `dir_format` | Формат имени директории | `{width}x{height}` |
| `postfix` | Постфикс имени файла | `_{method}_{width}x{height}` |

### Методы ресайза

| Метод | Описание |
|-------|----------|
| `cover` | Заполнить область, обрезать лишнее |
| `contain` | Вписать в область, добавить padding |
| `resize` | Принудительный размер (искажает пропорции) |
| `scale` | Масштабировать пропорционально |

## Параметры обработки

```php
'processing' => [
    'mode' => env('IMAGE_PROCESSING_MODE', 'queue'),
    'dry_run' => env('IMAGE_PROCESSING_DRY_RUN', false),
    'debug' => env('IMAGE_PROCESSING_DEBUG', false),
    'phash_distance_threshold' => env('PHASH_DISTANCE_THRESHOLD', 5),
],
```

| Параметр | Описание | По умолчанию |
|----------|----------|--------------|
| `phash_distance_threshold` | Порог схожести pHash (меньше = строже) | `5` |

## Параметры Face API

```php
'face_api' => [
    'url' => env('FACE_API_URL', 'http://127.0.0.1:5000'),
    'threshold' => env('FACE_RECOGNITION_THRESHOLD', 0.6),
],
```

| Параметр | Описание | По умолчанию |
|----------|----------|--------------|
| `url` | URL Face API сервиса | `http://127.0.0.1:5000` |
| `threshold` | Порог распознавания лиц (меньше = строже) | `0.6` |

## Полный пример .env

```env
# Storage
IMAGE_DISK=private
IMAGE_PATH=images
IMAGE_DEBUG_SUBDIR=debug

# Processing mode
IMAGE_PROCESSING_MODE=queue
IMAGE_PROCESSING_DRY_RUN=false
IMAGE_PROCESSING_DEBUG=false

# Thumbnails
THUMBNAIL_WIDTH=300
THUMBNAIL_HEIGHT=200
THUMBNAIL_METHOD=cover
THUMBNAIL_DIR_FORMAT={width}x{height}
THUMBNAIL_POSTFIX=_{method}_{width}x{height}

# Processing
PHASH_DISTANCE_THRESHOLD=5

# Face API
FACE_API_URL=http://127.0.0.1:5000
FACE_RECOGNITION_THRESHOLD=0.6
```

## Программный override

Можно временно изменить режим через код:

```php
$dispatcher = app(ImageQueueDispatcherInterface::class);

// Включить debug для одного вызова
$dispatcher
    ->setDebug(true)
    ->dispatchAll($image);

// Dry-run для тестирования
$dispatcher
    ->setDryRun(true)
    ->setDebug(true)
    ->dispatchAll($image);

// Синхронное выполнение
$dispatcher
    ->setMode('sync')
    ->dispatchThumbnail($image);
```
