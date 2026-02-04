# Тесты

## Запуск

```bash
# Все тесты
php artisan test

# Или через PHPUnit напрямую
./vendor/bin/phpunit

# Только Unit тесты
./vendor/bin/phpunit --testsuite=Unit

# Только Integration тесты
./vendor/bin/phpunit --testsuite=Integration

# Конкретный файл
./vendor/bin/phpunit tests/Unit/ImageServiceTest.php

# Конкретный тест
./vendor/bin/phpunit --filter=it_creates_image_and_dispatches_all_jobs
```

## Структура

```
tests/
├── TestCase.php                    # Базовый класс с SQLite setup
├── Unit/
│   ├── ImageServiceTest.php        # Тесты ImageService
│   ├── ImageQueueDispatcherTest.php # Тесты диспетчера очередей
│   ├── ImagePathServiceTest.php    # Тесты работы с путями
│   ├── ImageModelTest.php          # Тесты модели Image
│   └── QueueAbleTraitTest.php      # Тесты дедупликации очередей
├── Integration/
│   └── ImageRepositoryTest.php     # Тесты репозитория (с БД)
├── Feature/
│   └── CommandsTest.php            # Тесты artisan команд
└── database_testing_connection.php # Конфиг SQLite подключения
```

## Конфигурация

### Добавить в config/database.php

```php
'connections' => [
    // ... existing connections ...
    
    'sqlite_testing' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ],
],
```

### phpunit.xml

Файл `phpunit.xml` уже настроен в корне проекта.

## Особенности

### SQLite in-memory

- Каждый тест запускается с чистой БД
- `setUp()` создаёт все таблицы
- `tearDown()` удаляет все таблицы
- Быстро, изолированно

### Моки

Используется Mockery для мокирования зависимостей:

```php
$dispatcher = Mockery::mock(ImageQueueDispatcherInterface::class);
$dispatcher->shouldReceive('dispatchAll')->once()->andReturn([...]);
$this->app->instance(ImageQueueDispatcherInterface::class, $dispatcher);
```

### Хелперы в TestCase

```php
// Создать тестовое изображение
$image = $this->createTestImage([
    'filename' => 'custom.jpg',
    'metadata' => ['Make' => 'Canon'],
]);

// Создать несколько
$images = $this->createTestImages(5);
```

## Покрытие

| Компонент | Покрытие |
|-----------|----------|
| ImageService | ✅ |
| ImageQueueDispatcher | ✅ |
| ImagePathService | ✅ |
| ImageRepository | ✅ |
| Image Model | ✅ |
| QueueAbleTrait | ✅ |
| Commands | ✅ |

## Что тестируется

### ImageService
- Создание изображения и dispatch всех джобов
- Пропуск существующих с `--skip-existing`
- Обработка ошибок при insert

### ImageQueueDispatcher
- Режимы: queue, sync, disabled
- Dry-run логирование
- Debug логирование
- Fluent interface (chaining)
- Использование PathService для thumbnails

### ImagePathService
- Генерация путей к изображениям
- Форматирование имён thumbnails
- Работа с debug директорией

### ImageRepository
- CRUD операции
- Проверка существования
- Поиск по pHash
- Soft deletes

### Commands
- Выборка нужных изображений
- Корректный dispatch
- Подсчёт queued/skipped/errors
- Обработка исключений

### QueueAbleTrait
- Дедупликация (pushToQueue возвращает 'exists')
- Удаление из очереди (removeFromQueue)
- Генерация queue_key
