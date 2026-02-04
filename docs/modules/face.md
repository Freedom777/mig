# Модуль Face

Распознавание и группировка лиц на изображениях.

## Джоба

**Класс:** `App\Jobs\FaceProcessJob`  
**Очередь:** `faces`  
**Входные данные:** `['image_id' => int]`

## Что делает

1. Отправляет изображение в Face API
2. Получает encodings (векторы лиц)
3. Сравнивает с существующими лицами в БД
4. Группирует похожие лица (parent_id)
5. Сохраняет debug-изображение с рамками

## Алгоритм

```
Image::find($image_id)
         ↓
    Face API: /encode
         ↓
    Получаем encodings[]
         ↓
    Для каждого encoding:
    │
    ├── Face::where(parent_id=null, status=ok)
    │         ↓
    │   Face API: /compare
    │         ↓
    │   distance < threshold?
    │         ↓ да
    │   parent_id = matched_face_id
    │
    └── Face::create()
         ↓
    Image::update([debug_filename, faces_checked=1])
```

## Face API

Отдельный Python-сервис на localhost:5000.

### Endpoints

| Endpoint | Метод | Описание |
|----------|-------|----------|
| `/encode` | POST | Найти лица и вернуть encodings |
| `/compare` | POST | Сравнить encoding с кандидатами |
| `/health` | GET | Health check |

### /encode Request

```
POST /encode
Content-Type: multipart/form-data

image: <file>
original_disk: private
original_path: /path/to/image.jpg
image_debug_subdir: debug
```

### /encode Response

```json
{
  "encodings": [[0.123, -0.456, ...], ...],
  "debug_image_path": "/path/to/debug/image_debug.jpg"
}
```

### /compare Request

```json
{
  "encoding": [0.123, -0.456, ...],
  "candidates": [[...], [...], ...]
}
```

### /compare Response

```json
{
  "distances": [0.32, 0.78, 0.45, ...]
}
```

## Модель Face

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int | PK |
| `image_id` | int | FK на Image |
| `face_index` | int | Индекс лица на изображении |
| `encoding` | blob | 128-мерный вектор |
| `parent_id` | int/null | FK на "мастер" Face |
| `status` | enum | pending/ok/rejected |

## Группировка лиц

```
Face #1 (parent_id=null, status=ok)  ← "Мастер" для Джона
  ├── Face #5 (parent_id=1)          ← Джон на другом фото
  └── Face #9 (parent_id=1)          ← Джон на третьем фото

Face #2 (parent_id=null, status=ok)  ← "Мастер" для Анны
  └── Face #7 (parent_id=2)          ← Анна на другом фото
```

## Конфигурация

```php
// config/image.php
'face_api' => [
    'url' => env('FACE_API_URL', 'http://127.0.0.1:5000'),
    'threshold' => env('FACE_RECOGNITION_THRESHOLD', 0.6),
],
```

| Параметр | Описание | По умолчанию |
|----------|----------|--------------|
| `url` | URL Face API | `http://127.0.0.1:5000` |
| `threshold` | Порог схожести (меньше = строже) | `0.6` |

### Threshold

| Значение | Описание |
|----------|----------|
| 0.4 | Очень строго — только явные совпадения |
| 0.6 | Рекомендуемое значение |
| 0.8 | Мягко — больше false positives |

## Обновляемые поля Image

| Поле | Тип | Описание |
|------|-----|----------|
| `faces_checked` | bool | Флаг обработки |
| `debug_filename` | string | Имя debug-изображения |

## Debug изображения

Face API генерирует изображение с рамками вокруг лиц:

```
images/
├── photo.jpg
└── debug/
    └── photo_debug.jpg  ← с рамками вокруг лиц
```

## Команды

```bash
# Обработать все изображения без faces_checked
php artisan images:faces

# Переобработать изображения с status=recheck
php artisan images:faces:check
```

## Supervisor

```ini
[program:photo-faces]
command=php artisan queue:work rabbitmq --queue=faces --sleep=3 --tries=3 --timeout=360
numprocs=8  # CPU-intensive, можно много workers
```

## Lock

Используется 6-минутный lock для предотвращения параллельной обработки:

```php
$lock = Cache::lock('face-processing:' . $imageId, 360);
```

## Логирование

Успех:
```
Face processing completed {"image_id": 123, "faces_found": 3}
```

Найдено совпадение:
```
Face match found {"matched_id": 45, "distance": 0.42}
```

API ошибка:
```
Face API failed {"image_id": 123, "error": "HTTP 500: ..."}
```
