# Рекомендации по улучшению кода приложения

## 1. Добавить cast для metadata в Image.php

```php
// app/Models/Image.php

class Image extends Model
{
    protected $casts = [
        'metadata' => 'array',
        'faces_checked' => 'boolean',
        'created_at_file' => 'datetime',
        'updated_at_file' => 'datetime',
    ];
    
    // ... остальной код
}
```

**Преимущества:**
- `$image->metadata` сразу возвращает array
- Не нужен `json_encode()` при создании
- Не нужен `json_decode()` при чтении

---

## 2. Добавить метод для получения пути к существующему thumbnail

Текущий `getDefaultThumbnailPath()` генерирует путь из конфига. Если нужен метод который возвращает путь к **существующему** thumbnail (или null), добавьте:

```php
// app/Services/ImagePathService.php

/**
 * Получить путь к существующему thumbnail (из полей модели)
 * Возвращает null если thumbnail не сгенерирован
 */
public function getExistingThumbnailPath(Image $image): ?string
{
    if (!$image->thumbnail_path || !$image->thumbnail_filename) {
        return null;
    }

    return Storage::disk($image->disk)->path(
        $image->path . '/' . $image->thumbnail_path . '/' . $image->thumbnail_filename
    );
}
```

---

## 3. Добавить проверку image_id в BaseProcessJob

Для защиты от некорректных данных:

```php
// app/Jobs/BaseProcessJob.php

public function __construct(array $taskData)
{
    if (!isset($taskData['image_id'])) {
        throw new \InvalidArgumentException('taskData must contain image_id');
    }
    
    $this->taskData = $taskData;
}
```

---

## 4. Использовать HexCast вместо mutators

Вместо ручных `setHashAttribute`/`getHashAttribute` можно использовать custom cast:

```php
// app/Casts/HexCast.php
namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class HexCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        return $value ? bin2hex($value) : null;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value ? hex2bin($value) : null;
    }
}

// app/Models/Image.php
protected $casts = [
    'hash' => HexCast::class,
    'phash' => HexCast::class,
    'metadata' => 'array',
];
```

Это позволит убрать методы `setHashAttribute`/`getHashAttribute` из модели.
