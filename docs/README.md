# Image Processing System

Система обработки изображений на базе Laravel 12 с поддержкой очередей RabbitMQ.

## Возможности

- 🖼️ **Обработка изображений** — MD5, perceptual hash, поиск дубликатов
- 📐 **Thumbnails** — автоматическая генерация миниатюр
- 📋 **Metadata** — извлечение EXIF данных через ExifTool
- 📍 **Geolocation** — конвертация GPS координат в адреса (Nominatim)
- 👤 **Face Recognition** — распознавание и группировка лиц

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
| [Конфигурация](configuration.md) | Настройка режимов обработки |
| **Модули** | |
| [Image](modules/image.md) | Хеши, размеры, поиск дубликатов |
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
POST /api/image/upload
         ↓
    ImageService
         ↓
  ImageQueueDispatcher
         ↓
    ┌────┴────┬────────────┬─────────────┐
    ↓         ↓            ↓             ↓
  Image   Thumbnail   Metadata        Face
   Job       Job         Job           Job
    │                     │
    │                     ↓
    │               Geolocation
    │                   Job
    ↓
 Дубликаты
```

## Требования

- PHP 8.2+
- Laravel 12
- RabbitMQ
- ExifTool
- Imagick
- Face API (Python, localhost:5000)

## Конфигурация

```env
# Режим обработки
IMAGE_PROCESSING_MODE=queue      # queue|sync|disabled
IMAGE_PROCESSING_DRY_RUN=false   # true = только логировать
IMAGE_PROCESSING_DEBUG=false     # true = подробные логи

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
