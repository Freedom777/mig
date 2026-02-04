# Модуль Thumbnail

Генерация миниатюр изображений.

## Джоба

**Класс:** `App\Jobs\ThumbnailProcessJob`  
**Очередь:** `thumbnails`  
**Входные данные:** `['image_id' => int]`

## Что делает

1. Загружает изображение через Intervention Image
2. Применяет метод ресайза (cover, contain, resize, scale)
3. Сохраняет в поддиректорию `{width}x{height}`
4. Обновляет запись в БД

## Алгоритм

```
Image::find($image_id)
         ↓
    Проверка исходного файла
         ↓
    Создание директории (если нет)
         ↓
    Проверка существования thumbnail
         ↓ не существует
    ImageManager::read() → resize → save()
         ↓
    chmod(0644)
         ↓
    Image::update([thumbnail_*])
```

## Структура файлов

```
images/
├── photo.jpg                    # Оригинал
└── 300x200/                     # Директория thumbnail
    └── photo_cover_300x200.jpg  # Thumbnail
```

## Конфигурация

```php
// config/image.php
'thumbnails' => [
    'width' => env('THUMBNAIL_WIDTH', 300),
    'height' => env('THUMBNAIL_HEIGHT', 200),
    'method' => env('THUMBNAIL_METHOD', 'cover'),
    'dir_format' => '{width}x{height}',
    'postfix' => '_{method}_{width}x{height}',
],
```

## Методы ресайза

| Метод | Описание | Когда использовать |
|-------|----------|-------------------|
| `cover` | Заполняет область, обрезает лишнее | Галереи, превью |
| `contain` | Вписывает в область, добавляет padding | Когда важны пропорции |
| `resize` | Принудительный размер | Иконки фиксированного размера |
| `scale` | Масштабирует пропорционально | Уменьшение без обрезки |

### Визуально

```
Оригинал 1600x1200 → cover 300x200

cover:    [████████████]  обрезан по краям
contain:  [▓▓████████▓▓]  с padding
resize:   [████████████]  искажён
scale:    [███████]       300x225 (пропорции сохранены)
```

## Обновляемые поля Image

| Поле | Тип | Пример |
|------|-----|--------|
| `thumbnail_path` | string | `300x200` |
| `thumbnail_filename` | string | `photo_cover_300x200.jpg` |
| `thumbnail_method` | string | `cover` |
| `thumbnail_width` | int | `300` |
| `thumbnail_height` | int | `200` |

## Зависимости

- `intervention/image` — библиотека для обработки изображений
- Imagick PHP extension

## Команды

```bash
# Догенерировать thumbnails для изображений без них
php artisan images:thumbnails
```

## Пропуск существующих

Если thumbnail уже существует на диске — джоба пропускает создание:

```
Thumbnail already exists, skipping {"image_id": 123}
```

## Обработка ошибок

При ошибке:
- Частично созданный файл удаляется
- Ошибка записывается в `Image.last_error`
- Джоба пробрасывает исключение для retry

## Логирование

Успех:
```
Thumbnail created successfully {
    "image_id": 123,
    "target": "/path/to/300x200/photo_cover_300x200.jpg",
    "dimensions": "300x200",
    "method": "cover"
}
```

Ошибка:
```
Thumbnail job failed {"image_id": 123, "error": "..."}
```
