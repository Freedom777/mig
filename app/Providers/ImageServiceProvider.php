<?php

namespace App\Providers;

use App\Contracts\ImagePathServiceInterface;
use App\Contracts\ImageQueueDispatcherInterface;
use App\Contracts\ImageRepositoryInterface;
use App\Contracts\ImageServiceInterface;
use App\Repositories\ImageRepository;
use App\Services\ImagePathService;
use App\Services\ImageQueueDispatcher;
use App\Services\ImageService;
use Illuminate\Support\ServiceProvider;

class ImageServiceProvider extends ServiceProvider
{
    /**
     * Все биндинги контейнера
     */
    public array $bindings = [
        ImagePathServiceInterface::class => ImagePathService::class,
        ImageRepositoryInterface::class => ImageRepository::class,
        ImageQueueDispatcherInterface::class => ImageQueueDispatcher::class,
        ImageServiceInterface::class => ImageService::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Биндинги уже объявлены в $bindings, но можно добавить дополнительные
        // настройки здесь, если нужно

        // Пример singleton (если понадобится):
        // $this->app->singleton(ImageServiceInterface::class, ImageService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
