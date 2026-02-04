# Модуль Metadata

Извлечение EXIF и других метаданных из изображений.

## Джоба

**Класс:** `App\Jobs\MetadataProcessJob`  
**Очередь:** `metadatas`  
**Входные данные:** `['image_id' => int]`

## Что делает

1. Запускает ExifTool для извлечения метаданных
2. Сохраняет JSON с метаданными в БД
3. Если есть GPS данные → запускает `GeolocationProcessJob`

## Алгоритм

```
Image::find($image_id)
         ↓
    exiftool -json -n {path}
         ↓
    json_decode()
         ↓
    Image::update(['metadata' => $data])
         ↓
    Geolocation::hasGeodata()?
         ↓ да
    ImageQueueDispatcher::dispatchGeolocation()
```

## Связь с Geolocation

`MetadataProcessJob` автоматически запускает `GeolocationProcessJob` если в метаданных есть GPS координаты.

```
MetadataProcessJob
        ↓
   hasGeodata() → true
        ↓
GeolocationProcessJob
```

## Извлекаемые данные

ExifTool извлекает сотни полей. Основные:

| Категория | Поля |
|-----------|------|
| **Камера** | Make, Model, LensModel |
| **Настройки** | ExposureTime, FNumber, ISO, FocalLength |
| **Дата** | DateTimeOriginal, CreateDate |
| **GPS** | GPSLatitude, GPSLongitude, GPSAltitude |
| **Размеры** | ImageWidth, ImageHeight, Orientation |
| **Цвет** | ColorSpace, WhiteBalance |

## Обновляемые поля Image

| Поле | Тип | Описание |
|------|-----|----------|
| `metadata` | json | Полный JSON с метаданными |

## Зависимости

- **ExifTool** — должен быть установлен в системе

```bash
# Ubuntu/Debian
sudo apt install exiftool

# macOS
brew install exiftool
```

## Проверка GPS данных

```php
// App\Models\Geolocation
public static function hasGeodata(array $metadata): bool
{
    return isset($metadata['GPSLatitude'], $metadata['GPSLongitude'])
        || isset($metadata['GPSPosition']);
}
```

## Команды

```bash
# Догенерировать metadata для изображений без них
php artisan images:metadatas
```

## Логирование

Успех:
```
Metadata extracted successfully {"image_id": 123, "has_gps": true}
```

С GPS:
```
Geolocation job dispatched {"image_id": 123, "status": "success"}
```

Без GPS:
```
No GPS data found in metadata {"image_id": 123}
```

Ошибка ExifTool:
```
ExifTool process failed {"image_id": 123, "error": "..."}
```

## Пример метаданных

```json
{
  "FileName": "photo.jpg",
  "FileSize": "2.5 MB",
  "Make": "Canon",
  "Model": "Canon EOS R5",
  "DateTimeOriginal": "2024:01:15 14:30:00",
  "ExposureTime": "1/250",
  "FNumber": 2.8,
  "ISO": 400,
  "FocalLength": "50.0 mm",
  "GPSLatitude": 55.7558,
  "GPSLongitude": 37.6173,
  "GPSAltitude": "156 m",
  "ImageWidth": 8192,
  "ImageHeight": 5464
}
```
