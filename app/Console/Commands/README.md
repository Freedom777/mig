# Commands Reference

Все artisan команды для работы с системой обработки изображений.

---

## Processing Commands

### `images:process`

Обработка новых изображений из директории.

**Использование:**
```bash
php artisan images:process {disk?} {source?} [--skip-existing]
```

**Параметры:**
- `disk` - Storage disk (по умолчанию из config)
- `source` - Директория с изображениями
- `--skip-existing` - Пропустить уже существующие в БД

**Примеры:**
```bash
# Обработать все JPG в images/
php artisan images:process private images

# Пропустить существующие
php artisan images:process private images --skip-existing
```

**Что делает:**
1. Сканирует директорию рекурсивно
2. Находит JPG/JPEG файлы
3. Вызывает `ImageService::processNewUpload()`
4. Dispatch'ит 4 jobs параллельно:
   - ThumbnailProcessJob
   - MetadataProcessJob
   - FaceProcessJob
   - ImageProcessJob (hash + WebP)

---

## Recovery Commands

### `images:recover`

Универсальное восстановление после ошибок.

**Использование:**
```bash
php artisan images:recover [--dry-run] [--limit=N] [--verbose]
```

**Параметры:**
- `--dry-run` - Показать что будет сделано без изменений
- `--limit=N` - Ограничить количество проверяемых изображений
- `--verbose` - Подробный вывод для каждого изображения

**Что делает:**
Автоматически обрабатывает все проблемные ситуации:

| Ситуация | Действие |
|----------|----------|
| WebP ✅ + JPG ✅ | Удаляет orphaned JPG |
| WebP ❌ + JPG ✅ | Восстанавливает: filename→.jpg, hash=NULL, dispatch ImageProcessJob |
| WebP ❌ + JPG ❌ | Помечает: last_error + status=recheck |

**Примеры:**
```bash
# Dry-run для проверки
php artisan images:recover --dry-run --verbose

# Восстановить все проблемы
php artisan images:recover

# Проверить первые 100
php artisan images:recover --limit=100
```

**Статистика:**
```
📊 Recovery statistics:
┌────────────────────────┬───────┐
│ Action                 │ Count │
├────────────────────────┼───────┤
│ Already OK             │ 1234  │
│ Orphaned JPG deleted   │ 45    │
│ Restored from JPG      │ 12    │
│ Marked as broken       │ 3     │
│ Errors                 │ 0     │
└────────────────────────┴───────┘
```

---

## Reprocessing Commands

### `images:reprocess`

Переобработка изображений с ошибками или отсутствующими данными.

**Использование:**
```bash
php artisan images:reprocess [OPTIONS]
```

**Фильтры:**
- `--no-debug` - Без debug_filename (faces_checked=1)
- `--faces-failed` - faces_checked = 0
- `--no-metadata` - metadata IS NULL
- `--no-thumbnails` - thumbnail_path IS NULL
- `--has-gps` - Есть GPS но нет geolocation
- `--no-webp` - filename всё ещё .jpg *(ImageProcessJob failed)*
- `--no-hash` - hash IS NULL *(ImageProcessJob failed)*
- `--orphaned-jpg` - filename .webp но JPG существует
- `--status=recheck` - Конкретный статус

**Очереди:**
- `--queue=image` - Только ImageProcessJob (hash + WebP)
- `--queue=faces` - Только FaceProcessJob
- `--queue=metadata` - Только MetadataProcessJob
- `--queue=thumbnails` - Только ThumbnailProcessJob
- `--queue=geolocations` - Только GeolocationProcessJob
- `--queue=all` - ВСЕ jobs (по умолчанию)

**Опции:**
- `--limit=N` - Ограничить количество
- `--dry-run` - Показать без выполнения

**Примеры:**
```bash
# Failed faces recognition
php artisan images:reprocess --faces-failed --queue=faces

# Failed metadata extraction
php artisan images:reprocess --no-metadata --queue=metadata

# Failed ImageProcessJob (WebP конвертация)
php artisan images:reprocess --no-webp --queue=image

# Failed hash computation
php artisan images:reprocess --no-hash --queue=image

# Найти orphaned JPG
php artisan images:reprocess --orphaned-jpg --dry-run

# Статус recheck (максимум 100)
php artisan images:reprocess --status=recheck --limit=100

# Всё заново
php artisan images:reprocess --no-metadata --no-thumbnails --queue=all
```

---

### `images:reprocess:smart`

Умная переобработка с мониторингом очереди RabbitMQ.

**Использование:**
```bash
php artisan images:reprocess:smart [OPTIONS]
```

**Параметры:**
- `--batch-size=20` - Размер партии
- `--max-queue-size=50` - Максимальный размер очереди
- `--check-interval=10` - Интервал проверки (секунды)
- `--queue=faces` - Какую очередь обрабатывать
- `--filter=faces-failed` - Фильтр изображений
- `--max-batches=N` - Максимум партий

**Фильтры:**
- `faces-failed` - faces_checked = 0
- `no-debug` - Без debug_filename
- `no-metadata` - Без metadata
- `no-thumbnails` - Без thumbnails
- `no-webp` - filename всё ещё .jpg
- `no-hash` - hash IS NULL
- `has-gps` - Есть GPS но нет geolocation

**Как работает:**
1. Проверяет размер очереди RabbitMQ
2. Если очередь < max → добавляет партию
3. Если очередь >= max → ждёт
4. Повторяет пока не обработает все

**Примеры:**
```bash
# Smart reprocessing faces (партии по 50)
php artisan images:reprocess:smart \
  --filter=faces-failed \
  --queue=faces \
  --batch-size=50 \
  --max-queue-size=100

# Smart WebP конвертация
php artisan images:reprocess:smart \
  --filter=no-webp \
  --queue=images \
  --batch-size=20
```

**Требования:**
- RabbitMQ Management Plugin
- `RABBITMQ_API_PORT=15672` в .env

---

## Maintenance Commands

### `images:maintenance`

Обслуживание: проверка файлов, очистка, генерация pHash.

**Использование:**
```bash
php artisan images:maintenance {action} [OPTIONS]
```

**Actions:**
- `check` - Проверить существование файлов
- `cleanup` - Очистить неиспользуемые debug файлы
- `phash` - Сгенерировать pHash для изображений без него
- `all` - Всё вместе

**Опции:**
- `--dry-run` - Показать без выполнения
- `--limit=N` - Ограничить количество (только для phash)

**Примеры:**
```bash
# Проверить все файлы
php artisan images:maintenance check

# Очистить debug файлы (dry-run)
php artisan images:maintenance cleanup --dry-run

# Сгенерировать pHash
php artisan images:maintenance phash --limit=100

# Всё вместе
php artisan images:maintenance all
```

**Output (check):**
```
✅ Check completed:
  - Missing images: 0
  - Missing debug: 2
  - Missing thumbnails: 1
  - Orphaned JPG files: 45

⚠️  Found 45 orphaned JPG files!
Run: php artisan images:recover
```

---

## Legacy Commands (DEPRECATED)

### ~~`images:convert-to-webp`~~ ❌ УДАЛЕНА

**Причина:** Устарела после внедрения event-driven архитектуры.

**Используйте вместо:**
```bash
# Для новых изображений
php artisan images:process private images

# Для переобработки
php artisan images:reprocess --no-webp --queue=image

# Для очистки
php artisan images:recover
```

См. [ImagesToWebp_DEPRECATED.md](ImagesToWebp_DEPRECATED.md)

---

## Workflow Examples

### **Новая загрузка изображений:**
```bash
php artisan images:process private images --skip-existing
```

### **После сбоя обработки:**
```bash
# 1. Проверить что сломалось
php artisan images:maintenance check

# 2. Восстановить автоматически
php artisan images:recover

# 3. Переобработать конкретные проблемы
php artisan images:reprocess --no-webp --queue=image
```

### **Массовая переобработка:**
```bash
# Smart reprocessing с контролем очереди
php artisan images:reprocess:smart \
  --filter=faces-failed \
  --queue=faces \
  --batch-size=50 \
  --max-queue-size=200
```

### **Регулярное обслуживание:**
```bash
# Еженедельно
php artisan images:maintenance check
php artisan images:recover --dry-run

# Ежемесячно
php artisan images:maintenance cleanup
```

---

## Troubleshooting

### **ImageProcessJob failed:**
```bash
# Проверить сколько
SELECT COUNT(*) FROM images WHERE filename LIKE '%.jpg';

# Переобработать
php artisan images:reprocess --no-webp --queue=image --limit=100
```

### **Orphaned JPG файлы:**
```bash
# Найти
php artisan images:maintenance check

# Удалить
php artisan images:recover
```

### **Missing files:**
```bash
# Найти сломанные
SELECT * FROM images 
WHERE status = 'recheck' 
AND last_error LIKE '%missing%';

# Вручную проверить
php artisan images:recover --verbose --limit=10
```

### **Очередь переполнена:**
```bash
# Проверить размер
php artisan queue:monitor rabbitmq:images

# Smart reprocessing
php artisan images:reprocess:smart \
  --max-queue-size=50 \
  --check-interval=30
```

---

## См. также:

- [WebP Migration](../webp-migration.md)
- [Architecture](../architecture.md)
- [Troubleshooting](../troubleshooting.md)
- [Configuration](../configuration.md)
