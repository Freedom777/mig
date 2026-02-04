# Архитектура

## Обзор

Система построена на принципах SOLID с использованием Dependency Injection.
Все компоненты связаны через интерфейсы, что упрощает тестирование и замену реализаций.

## Диаграмма компонентов

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Entry Points                                │
│  ┌────────────────────────┐    ┌──────────────────────────────────┐ │
│  │ ApiImageActionController│    │ ImagesProcess (artisan command) │ │
│  └───────────┬────────────┘    └───────────────┬──────────────────┘ │
│              │                                 │                    │
│              └────────────┬────────────────────┘                    │
│                           ↓                                         │
│               ┌───────────────────────┐                             │
│               │     ImageService      │ ← Главная точка входа       │
│               └───────────┬───────────┘                             │
│                           │                                         │
│          ┌────────────────┼────────────────┐                        │
│          ↓                ↓                ↓                        │
│  ┌───────────────┐ ┌─────────────┐ ┌─────────────────┐              │
│  │ImageRepository│ │QueueDispatch│ │ImagePathService │              │
│  └───────────────┘ └──────┬──────┘ └─────────────────┘              │
│                           │                                         │
│          ┌───────┬────────┼────────┬───────────┐                    │
│          ↓       ↓        ↓        ↓           ↓                    │
│       Image  Thumbnail Metadata Geolocation  Face                   │
│        Job      Job      Job       Job        Job                   │
└─────────────────────────────────────────────────────────────────────┘
```

## Слои приложения

### 1. Entry Points (Точки входа)

| Компонент | Назначение |
|-----------|------------|
| `ApiImageActionController` | HTTP API для загрузки изображений |
| `ImagesProcess` | Artisan команда для batch-обработки |
| Другие команды | Догенерация отдельных типов данных |

### 2. Service Layer (Сервисы)

| Сервис | Интерфейс | Назначение |
|--------|-----------|------------|
| `ImageService` | `ImageServiceInterface` | Координация всего процесса |
| `ImageQueueDispatcher` | `ImageQueueDispatcherInterface` | Постановка джобов в очереди |
| `ImagePathService` | `ImagePathServiceInterface` | Работа с путями файлов |

### 3. Repository Layer (Репозитории)

| Репозиторий | Интерфейс | Назначение |
|-------------|-----------|------------|
| `ImageRepository` | `ImageRepositoryInterface` | CRUD операции с Image |

### 4. Job Layer (Джобы)

Все джобы наследуются от `BaseProcessJob` и принимают только `image_id`.

| Джоба | Очередь | Что делает |
|-------|---------|------------|
| `ImageProcessJob` | images | MD5, pHash, размеры, дубликаты |
| `ThumbnailProcessJob` | thumbnails | Генерация миниатюр |
| `MetadataProcessJob` | metadatas | Извлечение EXIF |
| `GeolocationProcessJob` | geolocations | GPS → адрес |
| `FaceProcessJob` | faces | Распознавание лиц |

## Связи между модулями

```
                    ┌──────────────┐
                    │   newUpload  │
                    └──────┬───────┘
                           │
              ┌────────────┼────────────┬──────────────┐
              ↓            ↓            ↓              ↓
         ┌────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐
         │ Image  │  │Thumbnail │  │ Metadata │  │  Face   │
         │  Job   │  │   Job    │  │   Job    │  │   Job   │
         └────────┘  └──────────┘  └────┬─────┘  └─────────┘
                                        │
                                        ↓ (если есть GPS)
                                  ┌───────────┐
                                  │Geolocation│
                                  │    Job    │
                                  └───────────┘
```

**Важно:** `GeolocationProcessJob` вызывается из `MetadataProcessJob`, а не напрямую из `dispatchAll()`.

## Дедупликация очередей

Таблица `queues` предотвращает дублирование задач:

```
┌─────────────────────────────────────────────────┐
│                  pushToQueue()                  │
│                       ↓                         │
│            MD5(class + taskData)                │
│                       ↓                         │
│        ┌─────────────────────────────┐          │
│        │  Ключ существует в queues?  │          │
│        └─────────────┬───────────────┘          │
│              ↓ нет           ↓ да               │
│         ┌────────┐      ┌────────┐              │
│         │ CREATE │      │ SKIP   │              │
│         │ + dispatch    │ return │              │
│         └────────┘      │'exists'│              │
│              ↓          └────────┘              │
│         ┌────────┐                              │
│         │ Worker │                              │
│         │ handle │                              │
│         └────┬───┘                              │
│              ↓                                  │
│         complete()                              │
│              ↓                                  │
│        DELETE from queues                       │
└─────────────────────────────────────────────────┘
```

## DI Container

Все биндинги регистрируются в `ImageServiceProvider`:

```php
public array $bindings = [
    ImagePathServiceInterface::class => ImagePathService::class,
    ImageRepositoryInterface::class => ImageRepository::class,
    ImageQueueDispatcherInterface::class => ImageQueueDispatcher::class,
    ImageServiceInterface::class => ImageService::class,
];
```

## Структура файлов

```
app/
├── Contracts/           # Интерфейсы
│   ├── ImageServiceInterface.php
│   ├── ImageRepositoryInterface.php
│   ├── ImageQueueDispatcherInterface.php
│   └── ImagePathServiceInterface.php
├── Services/            # Реализации сервисов
│   ├── ImageService.php
│   ├── ImageQueueDispatcher.php
│   └── ImagePathService.php
├── Repositories/        # Репозитории
│   └── ImageRepository.php
├── Jobs/                # Джобы
│   ├── BaseProcessJob.php
│   ├── ImageProcessJob.php
│   ├── ThumbnailProcessJob.php
│   ├── MetadataProcessJob.php
│   ├── GeolocationProcessJob.php
│   └── FaceProcessJob.php
├── Console/Commands/    # Artisan команды
├── Models/              # Eloquent модели
├── Traits/              # Трейты
├── Casts/               # Custom casts
└── Providers/           # Service providers
    └── ImageServiceProvider.php

config/
└── image.php            # Конфигурация модуля
```
