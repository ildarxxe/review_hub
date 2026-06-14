<?php

namespace App\Providers;

use App\Contracts\OrganizationDataSource;
use App\Services\Yandex\YandexMapsDataSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrganizationDataSource::class, YandexMapsDataSource::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
