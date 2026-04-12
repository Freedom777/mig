<?php

namespace App\Providers;

use App\Services\ApiClient;
use Illuminate\Support\ServiceProvider;
use MatanYadaev\EloquentSpatial\EloquentSpatial;
use MatanYadaev\EloquentSpatial\Enums\Srid;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiClient::class, function () {
            return new ApiClient();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Принудительно HTTPS для всех URL
        \Illuminate\Support\Facades\URL::forceScheme('https');

        // Set default SRID for Geolocations
        EloquentSpatial::setDefaultSrid(Srid::WGS84);
    }
}
