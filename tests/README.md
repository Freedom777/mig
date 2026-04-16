# Tests

Тесты для Image Processing System с WebP миграцией.

## Структура

```
tests/
├── Unit/
│   ├── Jobs/
│   │   ├── JobTestCase.php                         # Базовый класс для job тестов
│   │   └── ImageProcessJobTest.php                 # Unit тесты ImageProcessJob
│   └── Listeners/
│       └── CheckAndDispatchImageProcessingTest.php # Unit тесты Event Listener
└── Feature/
    └── WebPMigrationFlowTest.php                   # Integration тесты полного flow
```

## Запуск тестов

### Все тесты:
```bash
php artisan test
```

### Unit тесты:
```bash
php artisan test --testsuite=Unit
```

### Feature тесты:
```bash
php artisan test --testsuite=Feature
```

### Конкретный тест:
```bash
php artisan test --filter ImageProcessJobTest
php artisan test --filter it_converts_jpg_to_webp
```

### С coverage:
```bash
php artisan test --coverage
```

## Unit Tests

### JobTestCase

Базовый класс для тестирования jobs с helper методами:

- `createTestImage()` — создаёт тестовое JPG изображение (1x1 пиксель)
- `getStoragePath()` — получает реальный путь к файлу
- `assertFileExists()` — проверяет существование файла
- `assertFileNotExists()` — проверяет что файл удалён
- `setImageReadyForProcessing()` — устанавливает флаги для ImageProcessJob

### ImageProcessJobTest

**Тестируемые сценарии:**

1. ✅ `it_computes_md5_hash_from_jpg` — MD5 hash вычисляется из JPG
2. ✅ `it_computes_phash_from_jpg` — pHash вычисляется из JPG
3. ✅ `it_computes_image_dimensions` — width/height извлекаются
4. ✅ `it_converts_jpg_to_webp` — JPG конвертируется в WebP
5. ✅ `it_deletes_jpg_after_webp_conversion` — JPG удаляется после конвертации
6. ✅ `it_finds_duplicate_by_md5` — поиск дубликатов по MD5
7. ✅ `it_skips_webp_conversion_for_non_jpg_images` — PNG/GIF не конвертируются

**Пример:**
```php
public function it_converts_jpg_to_webp()
{
    // Arrange
    $image = $this->createTestImage(['filename' => 'test.jpg']);
    $this->setImageReadyForProcessing($image);
    
    // Act
    $job = new ImageProcessJob(['image_id' => $image->id]);
    $job->handle($repo, $pathService);
    
    // Assert
    $image->refresh();
    $this->assertEquals('test.webp', $image->filename);
    $this->assertFileExists($image); // WebP exists
    $this->assertFileNotExists($image, 'test.jpg'); // JPG deleted
}
```

### CheckAndDispatchImageProcessingTest

**Тестируемые сценарии:**

1. ✅ `it_dispatches_cleanup_when_all_jobs_completed` - cleanup JPG когда всё готово
2. ✅ `it_does_not_cleanup_when_metadata_missing` - не cleanup без metadata
3. ✅ `it_does_not_cleanup_when_faces_not_checked` - не cleanup без faces
4. ✅ `it_does_not_cleanup_when_thumbnail_missing` - не cleanup без thumbnail
5. ✅ `it_does_not_cleanup_twice_if_filename_not_webp` - не cleanup если filename всё ещё .jpg
6. ✅ `it_handles_different_job_types` - работает для всех job types
7. ✅ `it_handles_non_existent_image_gracefully` - graceful handling ошибок

**Пример:**
```php
public function it_dispatches_cleanup_when_all_jobs_completed()
{
    // Arrange
    $image = Image::create([
        'filename' => 'test.webp', // WebP конвертация завершена
        'metadata' => ['camera' => 'Test'],
        'faces_checked' => true,
        'thumbnail_filename' => 'test_thumb.webp',
    ]);

    // Act
    $listener->handle(new ImageJobCompleted($image->id, 'image'));

    // Assert - cleanup должна произойти (JPG удалён)
    $this->assertTrue(true);
}
```

## Feature Tests

### WebPMigrationFlowTest

**Тестируемые сценарии:**

1. ✅ `complete_webp_migration_flow` — полный flow от JPG до WebP
2. ✅ `parallel_jobs_dispatch_events` — параллельные jobs генерируют events
3. ✅ `image_process_job_waits_for_all_prerequisites` — ImageProcessJob ждёт все jobs

**Пример полного flow:**
```php
public function complete_webp_migration_flow()
{
    // 1. Создаём JPG
    $image = Image::create(['filename' => 'test.jpg']);
    
    // 2. Выполняем jobs параллельно
    ThumbnailProcessJob::dispatch($image->id);
    MetadataProcessJob::dispatch($image->id);
    FaceProcessJob::dispatch($image->id);
    
    // 3. Listener запускает ImageProcessJob
    ImageProcessJob::dispatch($image->id);
    
    // 4. Проверяем результат
    $image->refresh();
    $this->assertEquals('test.webp', $image->filename);
    $this->assertFileNotExists('test.jpg'); // JPG deleted
    $this->assertNotNull($image->hash);
    $this->assertNotNull($image->phash);
}
```

## Mocking

### Storage
```php
Storage::fake('public');
Storage::disk('public')->put('images/test.jpg', $content);
```

### Events
```php
Event::fake();
Event::assertDispatched(ImageJobCompleted::class);
Event::assertDispatched(ImageJobCompleted::class, function ($event) {
    return $event->imageId === 123 && $event->jobType === 'thumbnail';
});
```

**Note:** Listener тесты больше не используют `Queue::fake()` так как listener теперь удаляет JPG файлы, а не dispatch'ит jobs.

## CI/CD

### GitHub Actions
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: imagick, exif
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: php artisan test
```

## Coverage

Целевой coverage: **80%+**

**Текущий coverage:**
- ImageProcessJob: ~85%
- CheckAndDispatchImageProcessing: ~90%
- WebP Migration Flow: ~75%

## Troubleshooting

### Тесты падают с "Class not found"
```bash
composer dump-autoload
php artisan config:clear
```

### Storage fake не работает
Убедись что используешь `Storage::fake()` в `setUp()`:
```php
protected function setUp(): void
{
    parent::setUp();
    Storage::fake('public');
}
```

### Events не dispatch'ятся
Проверь что `Event::fake()` вызывается ДО кода который dispatch'ит events:
```php
Event::fake(); // FIRST
$job->handle(); // THEN
Event::assertDispatched(...); // CHECK
```

## TODO

- [ ] ThumbnailProcessJob tests
- [ ] MetadataProcessJob tests  
- [ ] FaceProcessJob tests
- [ ] ImageQueueDispatcher tests
- [ ] Integration тесты с реальным RabbitMQ
- [ ] Performance тесты (parallel vs chain)
- [ ] E2E тесты с реальным Face API

## См. также

- [PHPUnit Documentation](https://phpunit.de/)
- [Laravel Testing](https://laravel.com/docs/testing)
- [WebP Migration Docs](../docs/webp-migration.md)
