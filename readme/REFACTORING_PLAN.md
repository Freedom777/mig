# План рефакторинга Image Processing System

## Статус: В процессе
**Последнее обновление:** сессия рефакторинга

---

## ✅ Выполнено

### Шаг 1-5: Основная архитектура
- [x] `ImageRepositoryInterface` + `ImageRepository`
- [x] `ImageQueueDispatcherInterface` + `ImageQueueDispatcher`
- [x] `ImageServiceInterface` + `ImageService`
- [x] `ImagePathServiceInterface` + `ImagePathService`
- [x] `ImageServiceProvider` (регистрация DI)
- [x] Рефакторинг `ApiImageActionController`
- [x] Рефакторинг `ImagesProcess` (console command)
- [x] Рефакторинг `ImageProcessJob`
- [x] Унифицированный `config/image.php`
- [x] Очистка модели `Image.php` от бизнес-логики

### Исправленные баги
- [x] Двойной вызов `complete()` в `ImageProcessJob`
- [x] phash сохранялся как объект вместо hex string
- [x] Инвертированный параметр `$updateIfExists` → `$skipIfExists`
- [x] Разные имена конфигов (`image.*` vs `images.*`)
- [x] `Image::previous()` — неправильная сортировка (asc → desc)

---

## ✅ Выполнено: QueueAbleTrait + Queue scope

### Что было сделано
1. **Добавлен scope `byKey()` в модель `Queue`** — инкапсулирует hex→binary конвертацию
2. **Рефакторинг `QueueAbleTrait`:**
   - Вынесен `generateQueueKey()` для консистентности
   - `removeFromQueue()` теперь использует `Queue::byKey()`
   - Добавлен `existsInQueue()` для проверки наличия в очереди
   - Добавлены type hints

### Почему НЕ баг с hex2bin
HexCast работает только через Eloquent (`create`, `update`), но НЕ в `where()`.
Поэтому в `where()` нужен `hex2bin()` вручную — это правильно.
Scope `byKey()` инкапсулирует эту логику.

---

## 🔲 TODO (будущее): QueueAbleTrait → QueueService

Если понадобится полноценный DI и тестирование очередей:

1. **Создать `QueueServiceInterface`**
```php
interface QueueServiceInterface
{
    public function push(string $jobClass, string $queue, array $data): QueueResult;
    public function remove(string $jobClass, array $data): bool;
    public function exists(string $jobClass, array $data): bool;
}
```

2. **Создать `QueueResult` DTO**
```php
class QueueResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,  // 'queued', 'exists', 'error'
        public readonly ?string $message = null,
    ) {}
}
```

3. **Убрать JsonResponse из трейта** — возвращать DTO вместо HTTP response

---

## 🔲 TODO: Рефакторинг остальных джобов

### Джобы для проверки
- [ ] `ThumbnailProcessJob`
- [ ] `MetadataProcessJob`
- [ ] `GeolocationProcessJob`
- [ ] `FaceProcessJob`

### Что проверить в каждой джобе
1. **Двойной `complete()`** — как в ImageProcessJob
2. **Статические вызовы** — заменить на DI
3. **Обработка ошибок** — консистентность
4. **Lock механизмы** — правильность таймаутов

### Потенциальные интерфейсы
```
ThumbnailProcessJob
  └── ImagePathServiceInterface (уже есть)
  └── ThumbnailServiceInterface (?)

MetadataProcessJob
  └── MetadataExtractorInterface (ExifTool wrapper)

GeolocationProcessJob
  └── GeocodingServiceInterface (Nominatim wrapper)

FaceProcessJob
  └── FaceRecognitionServiceInterface (Face API wrapper)
```

---

## 🔲 TODO: BaseProcessJob

### Текущая структура (предположительно)
```php
abstract class BaseProcessJob implements ShouldQueue
{
    use QueueAbleTrait;  // ← убрать после рефакторинга
    
    protected array $taskData;
    
    abstract public function handle();
    
    protected function complete(): void
    {
        // Удаление из таблицы queues
    }
}
```

### План
1. Убрать `QueueAbleTrait`
2. Внедрить `QueueServiceInterface` через конструктор или `handle()`
3. Стандартизировать `complete()` — вызывать только в `finally` блоке `handle()`

---

## 🔲 TODO: Тесты

### Unit тесты для сервисов
- [ ] `ImageRepositoryTest`
- [ ] `ImageQueueDispatcherTest`
- [ ] `ImageServiceTest`
- [ ] `ImagePathServiceTest`
- [ ] `QueueServiceTest`

### Feature тесты
- [ ] `ApiImageActionControllerTest`
- [ ] `ImagesProcessCommandTest`

### Пример теста
```php
public function test_process_new_upload_creates_image_and_queues_jobs()
{
    // Arrange
    $mockRepo = Mockery::mock(ImageRepositoryInterface::class);
    $mockRepo->shouldReceive('exists')->andReturn(false);
    $mockRepo->shouldReceive('prepareImageData')->andReturn([...]);
    $mockRepo->shouldReceive('updateOrCreate')->andReturn(new Image(['id' => 1]));

    $mockDispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
    $mockDispatcher->shouldReceive('dispatchAll')->once()->andReturn([
        'image' => 'success',
        'thumbnail' => 'success',
    ]);

    $service = new ImageService($mockRepo, $mockDispatcher);

    // Act
    $result = $service->processNewUpload('private', 'images', 'test.jpg');

    // Assert
    $this->assertTrue($result['success']);
    $this->assertNotNull($result['image']);
}
```

---

## 📋 Приоритеты

### Высокий приоритет
1. **QueueAbleTrait → QueueService** — баг с hex2bin критичен
2. **Проверить остальные джобы** — могут быть аналогичные баги

### Средний приоритет
3. **BaseProcessJob рефакторинг**
4. **Тесты для новых сервисов**

### Низкий приоритет
5. **Дополнительные сервисы** (Metadata, Geolocation, Face)
6. **Документация API**

---

## 📁 Файлы для запроса у разработчика

Для продолжения рефакторинга нужны:
- [ ] `BaseProcessJob.php`
- [ ] `ThumbnailProcessJob.php`
- [ ] `MetadataProcessJob.php`
- [ ] `GeolocationProcessJob.php`
- [ ] `FaceProcessJob.php`
- [ ] Миграция таблицы `queues` (для понимания типа поля `queue_key`)

---

## 🗒️ Заметки

### Конфигурация (унифицированная)
Все настройки теперь в `config/image.php`:
- `image.paths.disk`
- `image.paths.images`
- `image.paths.debug_subdir`
- `image.thumbnails.width`
- `image.thumbnails.height`
- `image.thumbnails.method`
- `image.thumbnails.dir_format`
- `image.thumbnails.postfix`
- `image.processing.phash_distance_threshold`
- `image.face_api.url`
- `image.face_api.threshold`

### Провайдер
Зарегистрировать в `bootstrap/providers.php`:
```php
App\Providers\ImageServiceProvider::class,
```

### Биндинги
```php
ImagePathServiceInterface::class => ImagePathService::class,
ImageRepositoryInterface::class => ImageRepository::class,
ImageQueueDispatcherInterface::class => ImageQueueDispatcher::class,
ImageServiceInterface::class => ImageService::class,
// TODO: QueueServiceInterface::class => QueueService::class,
```
