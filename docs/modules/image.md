# Модуль Image

Обработка базовых характеристик изображения: хеши, размеры, поиск дубликатов.

## Джоба

**Класс:** `App\Jobs\ImageProcessJob`  
**Очередь:** `images`  
**Входные данные:** `['image_id' => int]`

## Что делает

1. **MD5 hash** — быстрая проверка точных дубликатов
2. **Perceptual hash (pHash)** — поиск визуально похожих изображений
3. **Размеры** — width, height
4. **Поиск дубликатов** — сначала по MD5, затем по pHash

## Алгоритм

```
Image::find($image_id)
         ↓
    md5_file() → hash
         ↓
    Поиск по MD5 → дубликат найден?
         ↓ нет
    ImageHash (PerceptualHash) → phash
         ↓
    Поиск по pHash (BIT_COUNT) → дубликат найден?
         ↓
    getimagesize() → width, height
         ↓
    Image::update([hash, phash, width, height, parent_id])
```

## Поиск дубликатов

### По MD5 (точное совпадение)

```php
Image::where('hash', hex2bin($md5))->value('id');
```

### По pHash (визуальное сходство)

```php
// Hamming distance через BIT_COUNT
$distance = BIT_COUNT(phash XOR $searchPhash)

// Если distance < threshold → дубликат
```

**Порог:** `config('image.processing.phash_distance_threshold')` (по умолчанию 5)

## Конфигурация

```php
// config/image.php
'processing' => [
    'phash_distance_threshold' => env('PHASH_DISTANCE_THRESHOLD', 5),
],
```

| Значение | Строгость | Описание |
|----------|-----------|----------|
| 0-2 | Очень строго | Почти идентичные |
| 3-5 | Умеренно | Незначительные различия |
| 6-10 | Мягко | Похожие изображения |
| >10 | Слишком мягко | Много ложных срабатываний |

## Обновляемые поля Image

| Поле | Тип | Описание |
|------|-----|----------|
| `hash` | binary(16) | MD5 hash (через HexCast) |
| `phash` | binary(8) | Perceptual hash (через HexCast) |
| `width` | int | Ширина в пикселях |
| `height` | int | Высота в пикселях |
| `parent_id` | int/null | ID оригинала (если дубликат) |

## Зависимости

- `jenssegers/imagehash` — библиотека для pHash

## Команда

```bash
# Пересчитать все pHash (синхронно, без очереди)
php artisan images:phashes
```

## Логирование

```
Image processed successfully {"image_id": 123, "has_duplicate": false}
```

При ошибке:
```
ImageProcessJob failed {"image_id": 123, "error": "..."}
```
