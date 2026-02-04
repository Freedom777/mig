# Модуль Geolocation

Конвертация GPS координат в адреса через Nominatim API.

## Джоба

**Класс:** `App\Jobs\GeolocationProcessJob`  
**Очередь:** `geolocations`  
**Входные данные:** `['image_id' => int]`

## Что делает

1. Извлекает координаты из `Image.metadata`
2. Ищет существующую точку в БД
3. Если нет — запрашивает Nominatim API
4. Сохраняет адрес и связывает с изображением

## Алгоритм

```
Image::find($image_id)
         ↓
    Geolocation::extractCoordinates(metadata)
         ↓
    ImageGeolocationPoint::where(coordinates)?
         ↓ не найдено
    ImageGeolocationAddress::whereContains(osm_area)?
         ↓ не найдено
    waitForRateLimit() → 2 сек между запросами
         ↓
    Nominatim API → reverse geocoding
         ↓
    ImageGeolocationAddress::create()
         ↓
    ImageGeolocationPoint::create()
         ↓
    Image::update(['image_geolocation_point_id'])
```

## Модели данных

### ImageGeolocationAddress

Кеш адресов от Nominatim.

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int | PK |
| `osm_id` | bigint | OpenStreetMap ID |
| `osm_area` | polygon | Bounding box области |
| `address` | json | Полный ответ Nominatim |

### ImageGeolocationPoint

Точные координаты с привязкой к адресу.

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int | PK |
| `image_geolocation_address_id` | int | FK на адрес |
| `coordinates` | point | Lat/Lon координаты |

### Связи

```
Image
  └── image_geolocation_point_id → ImageGeolocationPoint
                                      └── image_geolocation_address_id → ImageGeolocationAddress
```

## Rate Limiting

**Nominatim требует не более 1 запроса в секунду.**

Реализовано через Cache lock:

```php
private const NOMINATIM_RATE_LIMIT_SECONDS = 2;

private function waitForRateLimit(): void
{
    $lastCallTime = Cache::get('nominatim-api-rate-limit');
    if ($lastCallTime) {
        $waitTime = 2 - (microtime(true) - $lastCallTime);
        if ($waitTime > 0) {
            usleep($waitTime * 1000000);
        }
    }
    Cache::put('nominatim-api-rate-limit', microtime(true), 5);
}
```

**Важно:** В Supervisor для очереди `geolocations` используйте `numprocs=1`!

## Конфигурация

```php
// config/app.php или config/image.php
'geolocation_api_url' => env('GEOLOCATION_API_URL', 
    'https://nominatim.openstreetmap.org/reverse?format=json&lat={latitude}&lon={longitude}'
),
```

## Кеширование

Система кеширует на двух уровнях:

1. **По точке** — если координаты уже есть в `ImageGeolocationPoint`
2. **По области** — если точка попадает в `osm_area` существующего адреса

Это минимизирует запросы к Nominatim.

## Команды

```bash
# Догенерировать geolocation для изображений с GPS но без адреса
php artisan images:geolocations
```

## Supervisor

```ini
[program:photo-geolocation]
command=php artisan queue:work rabbitmq --queue=geolocations --sleep=3 --tries=3
numprocs=1  # ВАЖНО: только 1 worker из-за rate limit
```

## Логирование

Успех:
```
Geolocation processed successfully {"image_id": 123}
Created new geolocation point {"point_id": 456, "coordinates": [55.7558, 37.6173]}
```

Кеш-хит:
```
Using existing address {"address_id": 789}
```

Rate limit:
```
Waiting for Nominatim rate limit {"wait_seconds": 1.234}
```

Ошибка:
```
Nominatim API request failed {"status": 429, "url": "..."}
```
