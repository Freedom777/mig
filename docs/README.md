# Image Processing System

Система обработки изображений на базе Laravel 13 с поддержкой очередей RabbitMQ и event-driven архитектурой.

## Возможности

- 🖼️ **Обработка изображений** — MD5, perceptual hash, поиск дубликатов
- 🎨 **WebP конвертация** — автоматическая конвертация JPG → WebP с удалением оригиналов
- 📐 **Thumbnails** — автоматическая генерация миниатюр в WebP
- 📋 **Metadata** — извлечение EXIF данных через ExifTool
- 📍 **Geolocation** — конвертация GPS координат в адреса (Nominatim)
- 👤 **Face Recognition** — распознавание и группировка лиц
- ⚡ **Параллельная обработка** — jobs выполняются одновременно в разных очередях

## Быстрый старт

```bash
# Обработать все изображения в директории
php artisan images:process private images

# Пропустить уже существующие
php artisan images:process private images --skip-existing

# Dry-run режим (только посмотреть что будет)
IMAGE_PROCESSING_DRY_RUN=true php artisan images:process private images
```

## Документация

| Раздел | Описание |
|--------|----------|
| [Архитектура](architecture.md) | Структура системы и связи между компонентами |
| [WebP Migration](webp-migration.md) | Event-driven обработка и WebP конвертация |
| [Конфигурация](configuration.md) | Настройка режимов обработки |
| [База данных](database.md) | Структура таблиц и связи |
| **Модули** | |
| [Image](modules/image.md) | Хеши, размеры, поиск дубликатов, WebP |
| [Thumbnail](modules/thumbnail.md) | Генерация миниатюр |
| [Metadata](modules/metadata.md) | Извлечение EXIF |
| [Geolocation](modules/geolocation.md) | GPS → адрес |
| [Face](modules/face.md) | Распознавание лиц |
| **Справочники** | |
| [Команды](commands/README.md) | Artisan команды |
| [Сервисы](services/README.md) | API сервисов |
| [Troubleshooting](troubleshooting.md) | Решение проблем |

## Архитектура (кратко)

```
POST /api/image/upload (JPG)
         ↓
    ImageService
         ↓
  ImageQueueDispatcher (параллельный dispatch)
         ↓
    ┌────┴────┬────────────┬─────────────┐
    ↓         ↓            ↓             ↓
Thumbnail  Metadata      Face      Geolocation
  Job        Job          Job          Job
    │          │            │
    └──────┬───┴────────┬───┘
           ↓            ↓
    event(ImageJobCompleted)
           ↓
CheckAndDispatchImageProcessing (Listener)
  - Проверяет: metadata ✅ && faces_checked ✅ && thumbnail ✅
           ↓
    ImageProcessJob
  - Hash (MD5 + pHash из JPG)
  - WebP конвертация
  - Удаление JPG
```

**Event-driven:** Jobs выполняются параллельно, ImageProcessJob запускается автоматически когда все завершены.

## Требования

- PHP 8.3+
- Laravel 13
- RabbitMQ
- ExifTool
- Imagick
- Intervention Image v4
- Face API (Python, localhost:5000)

## Конфигурация

```env
# Режим обработки
IMAGE_PROCESSING_MODE=queue      # queue|sync|disabled
IMAGE_PROCESSING_DRY_RUN=false   # true = только логировать
IMAGE_PROCESSING_DEBUG=false     # true = подробные логи

# WebP качество
WEBP_QUALITY_IMAGE=90            # Основные изображения
WEBP_QUALITY_THUMBNAIL=85        # Миниатюры
WEBP_QUALITY_DEBUG=80            # Debug изображения (Face API)

# Thumbnails
THUMBNAIL_WIDTH=300
THUMBNAIL_HEIGHT=200
THUMBNAIL_METHOD=cover

# Face API
FACE_API_URL=http://127.0.0.1:5000
FACE_RECOGNITION_THRESHOLD=0.6
```

## Лицензия

Proprietary
