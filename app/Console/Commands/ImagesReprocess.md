# ImagesReprocess Command

Команда для переобработки проблемных изображений.

## Установка

```bash
cp ImagesReprocess.php app/Console/Commands/
```

## Использование

### 1. Переобработать изображения без debug_filename

```bash
php artisan images:reprocess --no-debug
```

**Что делает:**
- Ищет изображения где `faces_checked = 1` НО `debug_filename IS NULL`
- Отправляет их в очередь `faces` для переобработки

**Когда использовать:**
- Face API обработал лица, но не создал debug изображение
- После исправления ошибок в `save_debug_image()`

---

### 2. Переобработать изображения где Face API не отработал

```bash
php artisan images:reprocess --faces-failed
```

**Что делает:**
- Ищет изображения где `faces_checked = 0`
- Отправляет их в очередь `faces`

**Когда использовать:**
- Face API упал при обработке
- После перезапуска Face API сервиса
- После исправления `connectTimeout` проблемы

---

### 3. Переобработать изображения с ошибками

```bash
php artisan images:reprocess --status=error
```

**Что делает:**
- Ищет изображения со `status = 'error'`
- Сбрасывает статус на `process`
- Отправляет в очередь

**Когда использовать:**
- После исправления ошибок в коде
- Массовая переобработка упавших задач

---

### 4. Комбинированные фильтры

```bash
# Изображения с ошибкой И без debug
php artisan images:reprocess --status=error --no-debug

# Изображения Process И faces_checked=0
php artisan images:reprocess --status=process --faces-failed
```

---

### 5. Dry run (показать без выполнения)

```bash
php artisan images:reprocess --no-debug --dry-run
```

**Результат:**
```
Found 42 images to reprocess
DRY RUN MODE - nothing will be queued

+------+------------------+--------+---------------+----------------+
| ID   | Filename         | Status | faces_checked | debug_filename |
+------+------------------+--------+---------------+----------------+
| 123  | IMG_001.jpg      | ok     | Yes           | (null)         |
| 456  | IMG_002.jpg      | ok     | Yes           | (null)         |
| 789  | IMG_003.jpg      | ok     | Yes           | (null)         |
+------+------------------+--------+---------------+----------------+
```

---

### 6. Лимит количества

```bash
# Переобработать только 10 изображений
php artisan images:reprocess --faces-failed --limit=10
```

**Когда использовать:**
- Тестирование после исправления
- Постепенная переобработка большого количества

---

### 7. Выбор очереди

```bash
# Только faces
php artisan images:reprocess --no-debug --queue=faces

# Только metadata
php artisan images:reprocess --status=error --queue=metadata

# Только thumbnails
php artisan images:reprocess --status=error --queue=thumbnails

# Всё (по умолчанию)
php artisan images:reprocess --status=error --queue=all
```

---

## Примеры реальных сценариев

### Сценарий 1: Face API был недоступен

```bash
# 1. Проверяем сколько изображений не обработано
php artisan images:reprocess --faces-failed --dry-run

# 2. Переобрабатываем партиями по 100
php artisan images:reprocess --faces-failed --limit=100 --queue=faces
```

### Сценарий 2: Исправили баг в save_debug_image()

```bash
# 1. Смотрим сколько без debug
php artisan images:reprocess --no-debug --dry-run

# 2. Переобрабатываем все
php artisan images:reprocess --no-debug --queue=faces
```

### Сценарий 3: Массовая ошибка после деплоя

```bash
# 1. Проверяем количество ошибок
php artisan images:reprocess --status=error --dry-run

# 2. Тестируем на 5 изображениях
php artisan images:reprocess --status=error --limit=5

# 3. Если OK - переобрабатываем всё
php artisan images:reprocess --status=error
```

### Сценарий 4: Проблемы с конкретными изображениями

```sql
-- Найти изображения с проблемами
SELECT id, filename, status, faces_checked, debug_filename 
FROM images 
WHERE faces_checked = 1 AND debug_filename IS NULL
LIMIT 10;
```

```bash
# Переобработать их
php artisan images:reprocess --no-debug --limit=10
```

---

## Опции команды

| Опция | Описание | Пример |
|-------|----------|--------|
| `--no-debug` | Только изображения без debug_filename | `--no-debug` |
| `--faces-failed` | Только где faces_checked = 0 | `--faces-failed` |
| `--status=` | Фильтр по статусу (можно несколько) | `--status=error --status=process` |
| `--limit=` | Ограничить количество | `--limit=100` |
| `--dry-run` | Показать без выполнения | `--dry-run` |
| `--queue=` | Выбор очереди (faces/metadata/thumbnails/all) | `--queue=faces` |

---

## Что делает команда

1. **Фильтрует изображения** по заданным критериям
2. **Показывает количество** найденных изображений
3. **Dry run режим** - показывает таблицу без выполнения
4. **Отправляет в очередь** соответствующие джобы:
   - `FaceProcessJob` → очередь `faces`
   - `MetadataProcessJob` → очередь `metadatas`
   - `ThumbnailProcessJob` → очередь `thumbnails`
5. **Сбрасывает статус** с `error` на `process`

---

## Мониторинг

### Проверить сколько изображений требуют переобработки:

```sql
-- Без debug_filename
SELECT COUNT(*) FROM images 
WHERE faces_checked = 1 AND debug_filename IS NULL;

-- Без обработки лиц
SELECT COUNT(*) FROM images 
WHERE faces_checked = 0;

-- С ошибками
SELECT COUNT(*) FROM images 
WHERE status = 'error';
```

### Логи:

```bash
# Laravel логи
tail -f storage/logs/laravel.log | grep "Reprocessing"

# Face API логи
tail -f storage/logs/face-api-error.log

# Queue workers
tail -f storage/logs/worker.log
```

---

## Troubleshooting

### Команда ничего не находит

**Проверка:**
```bash
php artisan images:reprocess --no-debug --dry-run
# "No images found matching the criteria"
```

**Решение:**
```sql
-- Проверить вручную
SELECT COUNT(*) FROM images WHERE faces_checked = 1 AND debug_filename IS NULL;
```

### Изображения не переобрабатываются

**Проверка:**
```bash
# Проверить что воркеры запущены
php artisan queue:work --queue=faces --once

# Проверить логи
tail -f storage/logs/laravel.log
```

### Слишком много изображений

**Решение:**
```bash
# Обрабатывать партиями
php artisan images:reprocess --no-debug --limit=100

# Запустить в цикле
for i in {1..10}; do
  php artisan images:reprocess --no-debug --limit=100
  sleep 60
done
```

---

## Автоматизация

### Cron задача для переобработки ошибок:

```bash
# crontab -e

# Каждый день в 3:00 переобрабатывать ошибки
0 3 * * * cd /var/www/photo && php artisan images:reprocess --status=error --limit=1000

# Каждый час переобрабатывать failed faces (по 50 штук)
0 * * * * cd /var/www/photo && php artisan images:reprocess --faces-failed --limit=50
```

### Supervisor задача:

```ini
[program:photo-reprocess-errors]
command=php /var/www/photo/artisan images:reprocess --status=error --limit=100
directory=/var/www/photo
autostart=false
autorestart=false
startsecs=0
```

```bash
# Запустить вручную
sudo supervisorctl start photo-reprocess-errors
```

---

## Статистика

После выполнения команда показывает:

```
Found 42 images to reprocess
█████████████████████████████ 42/42 [============================] 100%

Reprocessing queued successfully:
  - Faces: 42 jobs
  - Metadata: 42 jobs
  - Thumbnails: 42 jobs
```

---

## Связанные команды

```bash
# Обработка новых изображений
php artisan images:process

# Только Face обработка
php artisan images:faces

# Проверить очереди
php artisan queue:work rabbitmq --queue=faces --once
```
