# Commands Refactoring - Variant C

Упрощение структуры команд до **3 основных команд**.

---

## Новая структура

### 1. `images:process`
Первичная обработка - сканирование директории и создание Image записей.

**Без изменений** - уже существует.

---

### 2. `images:reprocess` (ОБНОВЛЁН)
Переобработка изображений с ошибками или недостающими данными.

**Заменяет:**
- ❌ `ImagesFaces`
- ❌ `ImagesMetadatas`
- ❌ `ImagesThumbnails`
- ❌ `ImagesGeolocations`
- ❌ `ImagesFacesCheck` (устарела - parent_id удалён)

**Новые фильтры:**
- `--no-metadata` - изображения без metadata
- `--no-thumbnails` - изображения без thumbnails
- `--has-gps` - изображения с GPS но без geolocation

**Новые очереди:**
- `--queue=metadata`
- `--queue=thumbnails`
- `--queue=geolocations`

---

### 3. `images:maintenance` (НОВАЯ)
Утилиты обслуживания - проверка, очистка, pHash.

**Заменяет:**
- ❌ `ImagesCheck`
- ❌ `CleanupUnusedImages`
- ❌ `ImagesPhashes`

**Действия:**
- `check` - проверка файлов
- `cleanup` - очистка неиспользуемых debug
- `phash` - генерация pHash
- `all` - всё вместе (по умолчанию)

---

## Миграционная таблица

| Старая команда | Новая команда | Примечания |
|----------------|---------------|------------|
| `images:process` | `images:process` | Без изменений |
| `images:faces` | `images:reprocess --faces-failed --queue=faces` | Объединено |
| `images:metadatas` | `images:reprocess --no-metadata --queue=metadata` | Объединено |
| `images:thumbnails` | `images:reprocess --no-thumbnails --queue=thumbnails` | Объединено |
| `images:geolocations` | `images:reprocess --has-gps --queue=geolocations` | Объединено |
| `images:faces:check` | **УДАЛЕНА** | parent_id удалён |
| `images:check` | `images:maintenance check` | Объединено |
| `images:cleanup-unused` | `images:maintenance cleanup` | Объединено |
| `images:phashes` | `images:maintenance phash` | Объединено |

---

## Примеры использования

### Переобработка (images:reprocess)

#### Вместо `images:faces`:
```bash
# Было
php artisan images:faces

# Стало
php artisan images:reprocess --faces-failed --queue=faces
```

#### Вместо `images:metadatas`:
```bash
# Было
php artisan images:metadatas

# Стало
php artisan images:reprocess --no-metadata --queue=metadata
```

#### Вместо `images:thumbnails`:
```bash
# Было
php artisan images:thumbnails

# Стало
php artisan images:reprocess --no-thumbnails --queue=thumbnails
```

#### Вместо `images:geolocations`:
```bash
# Было
php artisan images:geolocations

# Стало
php artisan images:reprocess --has-gps --queue=geolocations
```

#### Новые возможности:
```bash
# Переобработать всё для изображений с ошибками
php artisan images:reprocess --status=error

# Только faces для изображений без debug
php artisan images:reprocess --no-debug --queue=faces

# Комбинированные фильтры
php artisan images:reprocess --no-metadata --no-thumbnails --queue=all
```

---

### Обслуживание (images:maintenance)

#### Вместо `images:check`:
```bash
# Было
php artisan images:check

# Стало
php artisan images:maintenance check
```

#### Вместо `images:cleanup-unused`:
```bash
# Было
php artisan images:cleanup-unused --dry-run

# Стало
php artisan images:maintenance cleanup --dry-run
```

#### Вместо `images:phashes`:
```bash
# Было
php artisan images:phashes

# Стало
php artisan images:maintenance phash
```

#### Всё вместе:
```bash
# Запустить все maintenance задачи
php artisan images:maintenance all

# Или просто (all по умолчанию)
php artisan images:maintenance
```

---

## Установка

### 1. Скопировать новые команды

```bash
cp ImagesReprocess.php app/Console/Commands/
cp ImagesMaintenance.php app/Console/Commands/
```

### 2. Удалить старые команды

```bash
cd app/Console/Commands

# Удалить устаревшие
rm ImagesFaces.php
rm ImagesMetadatas.php
rm ImagesThumbnails.php
rm ImagesGeolocations.php
rm ImagesFacesCheck.php
rm ImagesCheck.php
rm CleanupUnusedImages.php
rm ImagesPhashes.php
```

### 3. Обновить документацию/скрипты

Если у вас есть cron задачи или скрипты, обновите их:

```bash
# Старое
0 3 * * * php artisan images:faces
0 4 * * * php artisan images:metadatas

# Новое
0 3 * * * php artisan images:reprocess --faces-failed --queue=faces
0 4 * * * php artisan images:reprocess --no-metadata --queue=metadata
```

---

## Полный список команд после рефакторинга

```bash
# Обработка
php artisan images:process                  # Сканирование директории
php artisan images:reprocess                # Переобработка с фильтрами

# Обслуживание
php artisan images:maintenance              # Проверка + очистка + phash
```

**Всего 3 команды!** Было 10.

---

## images:reprocess - Полное описание

### Фильтры:

| Опция | Описание |
|-------|----------|
| `--no-debug` | faces_checked=1 AND debug_filename IS NULL |
| `--faces-failed` | faces_checked=0 |
| `--no-metadata` | metadata IS NULL |
| `--no-thumbnails` | thumbnail_path IS NULL |
| `--has-gps` | has GPS data but no geolocation |
| `--status=error` | status = error (можно несколько) |
| `--limit=100` | Ограничить количество |
| `--dry-run` | Показать без выполнения |

### Очереди:

| Опция | Описание |
|-------|----------|
| `--queue=faces` | Только faces |
| `--queue=metadata` | Только metadata |
| `--queue=thumbnails` | Только thumbnails |
| `--queue=geolocations` | Только geolocations |
| `--queue=all` | Всё (по умолчанию) |

### Примеры:

```bash
# Переобработать faces для изображений без debug
php artisan images:reprocess --no-debug --queue=faces

# Переобработать metadata
php artisan images:reprocess --no-metadata --queue=metadata

# Переобработать всё для изображений с ошибками (первые 100)
php artisan images:reprocess --status=error --limit=100

# Dry run - показать что будет обработано
php artisan images:reprocess --faces-failed --dry-run
```

---

## images:maintenance - Полное описание

### Действия:

| Действие | Описание |
|----------|----------|
| `check` | Проверка существования файлов |
| `cleanup` | Удаление неиспользуемых debug файлов |
| `phash` | Генерация pHash для изображений |
| `all` | Все действия (по умолчанию) |

### Опции:

| Опция | Описание |
|-------|----------|
| `--dry-run` | Показать без выполнения |
| `--limit=100` | Ограничить (только для phash) |

### Примеры:

```bash
# Проверить файлы
php artisan images:maintenance check

# Очистить неиспользуемые debug (dry run)
php artisan images:maintenance cleanup --dry-run

# Сгенерировать pHash (первые 1000)
php artisan images:maintenance phash --limit=1000

# Выполнить всё
php artisan images:maintenance all
```

---

## Что удалено и почему

### ImagesFacesCheck
**Причина:** Использовала parent_id в таблице faces, который удалён.

**Код:**
```php
// Старая логика с parent_id
$faces = $image->faces;
foreach ($faces as $face) {
    $children = $face->children;  // ← parent_id
    // ...
}
```

**Замена:** Используйте `images:reprocess --no-debug --queue=faces`

---

### ImagesFaces, ImagesMetadatas, ImagesThumbnails, ImagesGeolocations
**Причина:** Дублирование логики. Все делали одно и то же, но для разных фильтров.

**Было:**
```php
// ImagesFaces.php
$query = Image::where('faces_checked', 0);

// ImagesMetadatas.php
$query = Image::whereNull('metadata');

// ImagesThumbnails.php
$query = Image::whereNull('thumbnail_path');
```

**Стало:**
```bash
php artisan images:reprocess --faces-failed
php artisan images:reprocess --no-metadata
php artisan images:reprocess --no-thumbnails
```

**Одна команда с разными фильтрами!**

---

### ImagesCheck, CleanupUnusedImages, ImagesPhashes
**Причина:** Утилиты обслуживания логично объединить в одну команду.

**Было:** 3 отдельные команды

**Стало:**
```bash
php artisan images:maintenance check
php artisan images:maintenance cleanup
php artisan images:maintenance phash
```

---

## Преимущества новой структуры

### ✅ Меньше команд
**Было:** 10 команд
**Стало:** 3 команды

### ✅ Гибкость
```bash
# Комбинированные фильтры
php artisan images:reprocess --no-metadata --no-thumbnails --status=error

# Выборочные очереди
php artisan images:reprocess --status=error --queue=faces
```

### ✅ Консистентность
Все команды используют одинаковые опции:
- `--dry-run`
- `--limit`
- `--queue`

### ✅ Меньше дублирования кода
Один класс вместо четырёх (Images* → ImagesReprocess)

### ✅ Проще поддерживать
Меньше файлов, меньше мест для изменений

---

## Обратная совместимость

Если нужно сохранить старые команды для обратной совместимости, создайте алиасы:

```php
// app/Console/Commands/ImagesFaces.php (алиас)
class ImagesFaces extends Command
{
    protected $signature = 'images:faces';
    protected $description = '[DEPRECATED] Use: images:reprocess --faces-failed --queue=faces';

    public function handle(): int
    {
        $this->warn('⚠️  This command is deprecated!');
        $this->line('Use instead: php artisan images:reprocess --faces-failed --queue=faces');
        
        return $this->call('images:reprocess', [
            '--faces-failed' => true,
            '--queue' => 'faces',
        ]);
    }
}
```

---

## Тестирование миграции

### 1. Проверить что новые команды работают:

```bash
# Dry run
php artisan images:reprocess --faces-failed --dry-run
php artisan images:maintenance check --dry-run
```

### 2. Сравнить результаты:

```bash
# Старая команда (если ещё есть)
php artisan images:faces --dry-run

# Новая команда
php artisan images:reprocess --faces-failed --dry-run

# Результаты должны быть идентичными
```

### 3. Запустить на небольшой выборке:

```bash
php artisan images:reprocess --faces-failed --limit=10
```

---

## Поддержка

Если возникли проблемы при миграции:

1. Проверьте что все Jobs существуют:
   - FaceProcessJob
   - MetadataProcessJob
   - ThumbnailProcessJob
   - GeolocationProcessJob

2. Проверьте что ImagePathService работает:
   ```bash
   php artisan tinker
   >>> app(App\Services\ImagePathService::class)
   ```

3. Проверьте логи:
   ```bash
   tail -f storage/logs/laravel.log
   ```
