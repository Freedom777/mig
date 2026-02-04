# Сервисы

## Обзор

| Сервис | Интерфейс | Назначение |
|--------|-----------|------------|
| `ImageService` | `ImageServiceInterface` | Координация обработки |
| `ImageQueueDispatcher` | `ImageQueueDispatcherInterface` | Постановка в очереди |
| `ImagePathService` | `ImagePathServiceInterface` | Работа с путями |
| `ImageRepository` | `ImageRepositoryInterface` | CRUD для Image |

---

## ImageService

Главная точка входа для обработки изображений.

### Интерфейс

```php
interface ImageServiceInterface
{
    public function processNewUpload(
        string $disk,
        string $path,
        string $filename,
        bool $skipIfExists = false
    ): array;

    public function queueForProcessing(Image $image): array;
}
```

### Методы

#### processNewUpload()

Полный цикл обработки нового изображения.

```php
$result = $imageService->processNewUpload(
    disk: 'private',
    path: 'images/2024',
    filename: 'photo.jpg',
    skipIfExists: true
);

// Возвращает:
[
    'success' => true,
    'image' => Image {...},
    'message' => 'Image uploaded and processing started',
    'queue_statuses' => [
        'image' => 'success',
        'thumbnail' => 'success',
        'metadata' => 'success',
        'face' => 'success',
    ]
]
```

#### queueForProcessing()

Переобработка существующего изображения (только ImageProcessJob).

```php
$result = $imageService->queueForProcessing($image);

// Возвращает:
[
    'image_id' => 123,
    'status' => 'success'
]
```

---

## ImageQueueDispatcher

Управление очередями с поддержкой режимов.

### Интерфейс

```php
interface ImageQueueDispatcherInterface
{
    // Режимы
    public function getMode(): string;
    public function setMode(string $mode): self;
    public function isDryRun(): bool;
    public function setDryRun(bool $dryRun): self;
    public function isDebug(): bool;
    public function setDebug(bool $debug): self;

    // Dispatch методы
    public function dispatchAll(Image $image): array;
    public function dispatchImageProcess(Image $image): string;
    public function dispatchThumbnail(Image $image): string;
    public function dispatchMetadata(Image $image): string;
    public function dispatchGeolocation(Image $image): string;
    public function dispatchFace(Image $image): string;
}
```

### Режимы

```php
// Через конфиг (ENV)
IMAGE_PROCESSING_MODE=queue
IMAGE_PROCESSING_DRY_RUN=false
IMAGE_PROCESSING_DEBUG=false

// Программно
$dispatcher
    ->setMode('sync')
    ->setDryRun(true)
    ->setDebug(true)
    ->dispatchAll($image);
```

### Возвращаемые статусы

| Статус | Описание |
|--------|----------|
| `success` | Джоба добавлена в очередь |
| `exists` | Джоба уже в очереди (дедупликация) |
| `completed` | Джоба выполнена (sync режим) |
| `dry-run` | Пропущено (dry-run режим) |
| `skipped` | Пропущено (disabled режим) |
| `error` | Ошибка |

### Примеры

```php
// Поставить все джобы в очередь
$statuses = $dispatcher->dispatchAll($image);

// Только thumbnail
$status = $dispatcher->dispatchThumbnail($image);

// Синхронно выполнить face detection
$status = $dispatcher
    ->setMode('sync')
    ->dispatchFace($image);

// Dry-run с подробными логами
$statuses = $dispatcher
    ->setDryRun(true)
    ->setDebug(true)
    ->dispatchAll($image);
```

---

## ImagePathService

Генерация путей к файлам.

### Интерфейс

```php
interface ImagePathServiceInterface
{
    // Пути к изображениям
    public function getImagePathByObj(Image $image): string;
    public function getImagePathByParams(string $disk, string $path, string $filename): string;
    
    // Debug изображения
    public function getDebugImagePath(Image $image): ?string;
    public function getImageDebugSubdir(): string;
    
    // Thumbnails
    public function getThumbnailSubdir(int $width, int $height): string;
    public function getThumbnailFilename(string $filename, string $method, int $width, int $height): string;
    public function getDefaultThumbnailPath(Image $image): ?string;
    
    // URLs
    public function getThumbnailUrl(Image $image): ?string;
    public function getImageUrl(Image $image): ?string;
}
```

### Примеры

```php
$pathService = app(ImagePathServiceInterface::class);

// Полный путь к оригиналу
$path = $pathService->getImagePathByObj($image);
// /var/www/storage/app/private/images/photo.jpg

// Поддиректория для thumbnails
$subdir = $pathService->getThumbnailSubdir(300, 200);
// 300x200

// Имя файла thumbnail
$filename = $pathService->getThumbnailFilename('photo.jpg', 'cover', 300, 200);
// photo_cover_300x200.jpg

// Полный путь к debug
$debugPath = $pathService->getDebugImagePath($image);
// /var/www/storage/app/private/images/debug/photo_debug.jpg
```

---

## ImageRepository

CRUD операции с моделью Image.

### Интерфейс

```php
interface ImageRepositoryInterface
{
    public function prepareImageData(string $disk, string $path, string $filename): array;
    public function exists(string $disk, string $path, string $filename): bool;
    public function updateOrCreate(array $data): ?Image;
    public function find(int $id): ?Image;
    public function findOrFail(int $id): Image;
    public function findSimilarByPhash(string $hexHash, int $maxDistance = 5): ?int;
}
```

### Методы

#### prepareImageData()

Собирает метаданные файла для создания записи.

```php
$data = $repository->prepareImageData('private', 'images', 'photo.jpg');

// Возвращает:
[
    'source_disk' => 'private',
    'source_path' => 'images',
    'source_filename' => 'photo.jpg',
    'size' => 2456789,
    'created_at_file' => Carbon,
    'updated_at_file' => Carbon,
]
```

#### findSimilarByPhash()

Поиск визуально похожего изображения.

```php
$duplicateId = $repository->findSimilarByPhash(
    hexHash: 'a1b2c3d4e5f6g7h8',
    maxDistance: 5
);
```

Использует `BIT_COUNT(phash XOR ?)` для вычисления Hamming distance.

---

## Регистрация (DI)

Все сервисы регистрируются в `ImageServiceProvider`:

```php
// app/Providers/ImageServiceProvider.php

public array $bindings = [
    ImagePathServiceInterface::class => ImagePathService::class,
    ImageRepositoryInterface::class => ImageRepository::class,
    ImageQueueDispatcherInterface::class => ImageQueueDispatcher::class,
    ImageServiceInterface::class => ImageService::class,
];
```

Провайдер добавляется в `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ImageServiceProvider::class,
];
```

---

## Тестирование

Все сервисы легко мокаются благодаря интерфейсам:

```php
use App\Contracts\ImageQueueDispatcherInterface;
use Mockery;

public function test_process_skips_in_dry_run()
{
    $mockDispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
    $mockDispatcher->shouldReceive('dispatchAll')
        ->once()
        ->andReturn([
            'image' => 'dry-run',
            'thumbnail' => 'dry-run',
            'metadata' => 'dry-run',
            'face' => 'dry-run',
        ]);

    $this->app->instance(ImageQueueDispatcherInterface::class, $mockDispatcher);

    // Test...
}
```
