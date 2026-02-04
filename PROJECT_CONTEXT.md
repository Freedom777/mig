# Project Context: Image Processing System

> Этот файл содержит краткое описание проекта для быстрого погружения в контекст.

## Что это

Laravel 12 приложение для обработки фотографий. Работает на Hetzner CX32 (32 CPU, 64GB RAM).
Фотографии загружаются через FTP, затем обрабатываются через систему очередей (RabbitMQ).

## Основной flow

```
FTP upload
    ↓
POST /api/image/upload (ApiImageActionController::newUpload)
    ↓
ImageService::processNewUpload()
    ├── ImageRepository::prepareImageData() → собирает метаданные файла
    ├── ImageRepository::updateOrCreate() → создаёт запись в БД
    └── ImageQueueDispatcher::dispatchAll() → ставит 4 джобы в очереди
            ├── ImageProcessJob (queue: images) → MD5, pHash, размеры, поиск дубликатов
            ├── ThumbnailProcessJob (queue: thumbnails) → генерация миниатюр
            ├── MetadataProcessJob (queue: metadatas) → ExifTool → EXIF данные
            │       └── если есть GPS → GeolocationProcessJob
            └── FaceProcessJob (queue: faces) → Face API → распознавание лиц
```

## Ключевые архитектурные решения

### 1. Всё через интерфейсы + DI
```
ImageServiceInterface → ImageService
ImageRepositoryInterface → ImageRepository
ImageQueueDispatcherInterface → ImageQueueDispatcher
ImagePathServiceInterface → ImagePathService
```
Регистрация в `ImageServiceProvider`.

### 2. Джобы принимают только `image_id`
Каждая джоба сама достаёт нужные данные из БД. Это упрощает:
- Постановку в очередь
- Команды
- Тестирование

### 3. Дедупликация очередей
Таблица `queues` с `queue_key` (MD5 от class + data).
- При постановке: если ключ есть — пропускаем
- При завершении: удаляем ключ (`complete()`)

HexCast конвертирует hex ↔ binary автоматически.
Scope `Queue::byKey($hexKey)` для поиска.

### 4. Три режима обработки
```env
IMAGE_PROCESSING_MODE=queue|sync|disabled
IMAGE_PROCESSING_DRY_RUN=true|false   # только логировать
IMAGE_PROCESSING_DEBUG=true|false      # подробные логи
```

## Структура файлов

```
app/
├── Contracts/
│   ├── ImageServiceInterface.php
│   ├── ImageRepositoryInterface.php
│   ├── ImageQueueDispatcherInterface.php
│   └── ImagePathServiceInterface.php
├── Services/
│   ├── ImageService.php          # Главная точка входа
│   ├── ImageQueueDispatcher.php  # Постановка в очереди
│   └── ImagePathService.php      # Работа с путями
├── Repositories/
│   └── ImageRepository.php       # CRUD для Image
├── Jobs/
│   ├── BaseProcessJob.php        # Базовый класс + QueueAbleTrait
│   ├── ImageProcessJob.php
│   ├── ThumbnailProcessJob.php
│   ├── MetadataProcessJob.php
│   ├── GeolocationProcessJob.php
│   └── FaceProcessJob.php
├── Console/Commands/
│   ├── ImagesProcess.php         # Batch обработка новых файлов
│   ├── ImagesThumbnails.php      # Догенерация thumbnails
│   ├── ImagesMetadatas.php       # Догенерация metadata
│   ├── ImagesGeolocations.php    # Догенерация geolocation
│   ├── ImagesFaces.php           # Догенерация faces
│   ├── ImagesFacesCheck.php      # Перепроверка faces
│   ├── ImagesCheck.php           # Проверка целостности
│   ├── ImagesPhashes.php         # Пересчёт pHash
│   └── CleanupUnusedImages.php   # Очистка debug файлов
├── Models/
│   ├── Image.php                 # Главная модель
│   └── Queue.php                 # Дедупликация очередей
├── Traits/
│   └── QueueAbleTrait.php        # pushToQueue(), removeFromQueue()
├── Casts/
│   └── HexCast.php               # hex ↔ binary для БД
└── Providers/
    └── ImageServiceProvider.php  # DI bindings

config/
└── image.php                     # Вся конфигурация модуля
```

## Конфигурация (config/image.php)

```php
'paths' => [
    'disk' => 'private',
    'images' => 'images',
    'debug_subdir' => 'debug',
],
'thumbnails' => [
    'width' => 300,
    'height' => 200,
    'method' => 'cover',  // cover|scale|resize|contain
    'dir_format' => '{width}x{height}',
    'postfix' => '_{method}_{width}x{height}',
],
'processing' => [
    'mode' => 'queue',     // queue|sync|disabled
    'dry_run' => false,
    'debug' => false,
    'phash_distance_threshold' => 5,
],
'face_api' => [
    'url' => 'http://127.0.0.1:5000',
    'threshold' => 0.6,
],
```

## Внешние зависимости

- **RabbitMQ** — очереди
- **ExifTool** — извлечение EXIF
- **Face API** (Python) — распознавание лиц (localhost:5000)
- **Nominatim** — геокодинг (rate limit 1 req/sec)
- **Intervention Image + Imagick** — thumbnails

## Что было исправлено (баги)

1. ✅ Двойной вызов `complete()` в ImageProcessJob
2. ✅ pHash сохранялся как объект вместо hex string
3. ✅ Инвертированный параметр `$updateIfExists` → `$skipIfExists`
4. ✅ Разные имена конфигов (`image.*` vs `images.*`)
5. ✅ `Image::previous()` — неправильная сортировка

## Что было удалено

- `ApiClient.php` — делал HTTP к самому себе
- `*QueuePushApiController.php` (5 шт) — не использовались
- Роуты `/api/*/push`

## TODO (если вернёмся)

- [ ] Тесты для сервисов
- [ ] Обновить HTML документацию
- [ ] Рассмотреть ImagesPhashes, ImagesCheck, CleanupUnusedImages на предмет рефакторинга
