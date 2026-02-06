# Структура базы данных

## Обзор

База данных MySQL 8.0, кодировка `utf8mb4_unicode_ci`.

### Таблицы приложения

| Таблица | Назначение |
|---------|------------|
| `images` | Основная таблица изображений |
| `faces` | Распознанные лица |
| `image_geolocation_points` | GPS координаты |
| `image_geolocation_addresses` | Кеш адресов Nominatim |
| `queues` | Дедупликация очередей |

### Стандартные таблицы Laravel

| Таблица | Назначение |
|---------|------------|
| `users` | Пользователи (+ 2FA поля) |
| `sessions` | Сессии (database driver) |
| `cache` | Кеш (database driver) |
| `cache_locks` | Локи кеша |
| `jobs` | Очередь задач (database driver) |
| `job_batches` | Батчи задач |
| `failed_jobs` | Упавшие задачи |
| `migrations` | История миграций |
| `password_reset_tokens` | Токены сброса пароля |
| `personal_access_tokens` | API токены (Sanctum) |

---

## Таблицы приложения

### images

Основная таблица с информацией об изображениях.

```sql
CREATE TABLE `images` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `image_geolocation_point_id` bigint UNSIGNED DEFAULT NULL,
  `disk` varchar(255) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `debug_filename` varchar(255) DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `size` int DEFAULT NULL,
  `hash` binary(16) DEFAULT NULL,
  `phash` binary(8) DEFAULT NULL,
  `created_at_file` datetime DEFAULT NULL,
  `updated_at_file` datetime DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `faces_checked` tinyint(1) NOT NULL DEFAULT '0',
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `thumbnail_filename` varchar(255) DEFAULT NULL,
  `thumbnail_method` varchar(255) DEFAULT NULL,
  `thumbnail_width` varchar(255) DEFAULT NULL,
  `thumbnail_height` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` enum('process','not_photo','recheck','ok') NOT NULL DEFAULT 'process',
  `last_error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

#### Поля

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint | PK |
| `parent_id` | bigint | FK на оригинал (если дубликат) |
| `image_geolocation_point_id` | bigint | FK на GPS точку |
| `disk` | varchar(255) | Storage disk (private, public) |
| `path` | varchar(255) | Путь к директории |
| `filename` | varchar(255) | Имя файла |
| `debug_filename` | varchar(255) | Имя debug-файла с рамками лиц |
| `width` | int | Ширина в пикселях |
| `height` | int | Высота в пикселях |
| `size` | int | Размер файла в байтах |
| `hash` | binary(16) | MD5 хеш файла |
| `phash` | binary(8) | Perceptual hash (64 bit) |
| `created_at_file` | datetime | Дата создания файла |
| `updated_at_file` | datetime | Дата изменения файла |
| `metadata` | json | EXIF и другие метаданные |
| `faces_checked` | tinyint(1) | Флаг проверки лиц |
| `thumbnail_path` | varchar(255) | Поддиректория thumbnail |
| `thumbnail_filename` | varchar(255) | Имя файла thumbnail |
| `thumbnail_method` | varchar(255) | Метод ресайза (cover, contain, etc) |
| `thumbnail_width` | varchar(255) | Ширина thumbnail |
| `thumbnail_height` | varchar(255) | Высота thumbnail |
| `status` | enum | Статус обработки |
| `last_error` | varchar(255) | Последняя ошибка |
| `created_at` | timestamp | Создано в БД |
| `updated_at` | timestamp | Обновлено в БД |

#### Статусы

| Статус | Описание |
|--------|----------|
| `process` | В обработке (default) |
| `not_photo` | Не фотография |
| `recheck` | Требует перепроверки |
| `ok` | Обработано успешно |

#### Индексы

| Индекс | Поля | Назначение |
|--------|------|------------|
| PRIMARY | `id` | PK |
| `disk_path_filename_index` | `disk`, `path`, `filename` | Быстрый поиск по пути |
| `faces_checked_index` | `faces_checked` | Фильтр необработанных |
| `hash_index` | `hash` | Поиск дубликатов по MD5 |
| `phash` | `phash` | Поиск похожих по pHash |

#### Связи

```
images.parent_id → images.id (self-reference, дубликаты)
images.image_geolocation_point_id → image_geolocation_points.id
```

---

### faces

Распознанные лица на изображениях.

```sql
CREATE TABLE `faces` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` bigint UNSIGNED DEFAULT NULL,
  `image_id` bigint UNSIGNED DEFAULT NULL,
  `face_index` tinyint UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `encoding` json DEFAULT NULL,
  `status` enum('process','unknown','not_face','ok') NOT NULL DEFAULT 'process',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`image_id`) REFERENCES `images` (`id`)
);
```

#### Поля

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint | PK |
| `parent_id` | bigint | FK на "мастер" лицо (группировка) |
| `image_id` | bigint | FK на изображение |
| `face_index` | tinyint | Индекс лица на изображении (0, 1, 2...) |
| `name` | varchar(255) | Имя человека (ручная разметка) |
| `encoding` | json | 128-мерный вектор лица |
| `status` | enum | Статус распознавания |
| `deleted_at` | timestamp | Soft delete |

#### Статусы

| Статус | Описание |
|--------|----------|
| `process` | В обработке (default) |
| `unknown` | Лицо не опознано |
| `not_face` | Ложное срабатывание |
| `ok` | Успешно распознано |

#### Группировка лиц

```
Face #1 (parent_id=null, status=ok)  ← "Мастер" запись
  ├── Face #5 (parent_id=1)          ← То же лицо на другом фото
  └── Face #9 (parent_id=1)          ← То же лицо на третьем фото
```

---

### image_geolocation_points

GPS координаты изображений.

```sql
CREATE TABLE `image_geolocation_points` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_geolocation_address_id` bigint UNSIGNED DEFAULT NULL,
  `coordinates` point NOT NULL,
  PRIMARY KEY (`id`)
);
```

#### Поля

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint | PK |
| `image_geolocation_address_id` | bigint | FK на адрес |
| `coordinates` | point | GPS координаты (SPATIAL) |

#### Использование POINT

```php
use MatanYadaev\EloquentSpatial\Objects\Point;

$point = new Point($latitude, $longitude);
```

---

### image_geolocation_addresses

Кеш обратного геокодинга от Nominatim.

```sql
CREATE TABLE `image_geolocation_addresses` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `osm_id` bigint NOT NULL,
  `osm_area` polygon,
  PRIMARY KEY (`id`)
);
```

#### Поля

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint | PK |
| `osm_id` | bigint | OpenStreetMap ID |
| `osm_area` | polygon | Bounding box области (SPATIAL) |

#### Назначение

Кеширует ответы Nominatim. Если новая точка попадает в `osm_area` существующего адреса — не делаем повторный запрос к API.

---

### queues

Дедупликация очередей (предотвращение повторной постановки задач).

```sql
CREATE TABLE `queues` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue_key` binary(16) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `queues_queue_key_unique` (`queue_key`)
);
```

#### Поля

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | bigint | PK |
| `queue_key` | binary(16) | MD5 от (job_class + job_data) |
| `created_at` | timestamp | Время постановки |

#### Алгоритм

```php
// При постановке в очередь
$key = md5($jobClass . json_encode($data));

// Если ключ уже есть — возвращаем 'exists'
// Иначе — создаём запись + dispatch
```

---

## Стандартные таблицы Laravel

### users

Пользователи с поддержкой двухфакторной аутентификации (Laravel Fortify).

| Поле | Описание |
|------|----------|
| `id` | PK |
| `name` | Имя |
| `email` | Email (unique) |
| `email_verified_at` | Дата подтверждения email |
| `password` | Хеш пароля |
| `two_factor_secret` | Секрет 2FA |
| `two_factor_recovery_codes` | Коды восстановления 2FA |
| `two_factor_confirmed_at` | Дата подтверждения 2FA |
| `remember_token` | Токен "запомнить меня" |

### sessions

Сессии (database driver).

| Поле | Описание |
|------|----------|
| `id` | Session ID (PK) |
| `user_id` | FK на пользователя |
| `ip_address` | IP адрес |
| `user_agent` | User agent браузера |
| `payload` | Данные сессии |
| `last_activity` | Последняя активность |

### cache / cache_locks

Кеш и локи (database driver).

### jobs

Очередь задач (database driver). При использовании RabbitMQ эта таблица не используется для основных задач.

| Поле | Описание |
|------|----------|
| `id` | PK |
| `queue` | Имя очереди |
| `payload` | Сериализованная задача |
| `attempts` | Количество попыток |
| `reserved_at` | Время резервирования |
| `available_at` | Доступна с |
| `created_at` | Создана |

### job_batches

Батчи задач (Laravel Job Batching).

### failed_jobs

Упавшие задачи для анализа и retry.

| Поле | Описание |
|------|----------|
| `id` | PK |
| `uuid` | UUID (unique) |
| `connection` | Подключение |
| `queue` | Очередь |
| `payload` | Данные задачи |
| `exception` | Текст исключения |
| `failed_at` | Время падения |

### migrations

История миграций Laravel.

### password_reset_tokens

Токены сброса пароля.

### personal_access_tokens

API токены (Laravel Sanctum).

---

## ER-диаграмма

```
┌─────────────────────────────────────────────────────────────────┐
│                           images                                │
├─────────────────────────────────────────────────────────────────┤
│ id                                                              │
│ parent_id ──────────────────────────────────┐ (self-reference)  │
│ image_geolocation_point_id ─────────────────│────────────┐      │
│ disk, path, filename                        │            │      │
│ width, height, size                         │            │      │
│ hash, phash                                 │            │      │
│ metadata (JSON)                             │            │      │
│ thumbnail_*, faces_checked, status          │            │      │
└─────────────────────────────────────────────│────────────│──────┘
                                              │            │
                     ┌────────────────────────┘            │
                     ↓                                     │
┌─────────────────────────────┐                           │
│           faces             │                           │
├─────────────────────────────┤                           │
│ id                          │                           │
│ parent_id (self-reference)  │                           │
│ image_id ───────────────────│→ images.id                │
│ face_index                  │                           │
│ name, encoding (JSON)       │                           │
│ status                      │                           │
└─────────────────────────────┘                           │
                                                          │
                     ┌────────────────────────────────────┘
                     ↓
┌─────────────────────────────────────┐
│     image_geolocation_points        │
├─────────────────────────────────────┤
│ id                                  │
│ image_geolocation_address_id ───────│→ image_geolocation_addresses.id
│ coordinates (POINT)                 │
└─────────────────────────────────────┘
                     │
                     ↓
┌─────────────────────────────────────┐
│   image_geolocation_addresses       │
├─────────────────────────────────────┤
│ id                                  │
│ osm_id                              │
│ osm_area (POLYGON)                  │
└─────────────────────────────────────┘


┌─────────────────────────────────────┐
│            queues                   │
├─────────────────────────────────────┤
│ id                                  │
│ queue_key (UNIQUE, MD5)             │
│ created_at                          │
└─────────────────────────────────────┘
```

---

## Заметки

### Бинарные поля

`hash` и `phash` хранятся как `binary`, но в PHP работаем с hex-строками через `HexCast`:

```php
// В модели Image
protected $casts = [
    'hash' => HexCast::class,
    'phash' => HexCast::class,
];

// Использование
$image->hash = 'a1b2c3...';  // hex string
// В БД: 0xA1B2C3... (binary)
```

### SPATIAL поля

`coordinates` и `osm_area` используют MySQL Spatial типы. Работа через пакет `matanyadaev/laravel-eloquent-spatial`:

```php
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

$point = new Point(55.7558, 37.6173);
```

### JSON поля

`metadata` и `encoding` — JSON. Laravel автоматически кастит в array.
