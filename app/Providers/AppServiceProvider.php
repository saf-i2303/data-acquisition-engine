<?php

namespace App\Providers;

use App\Services\Contracts\WebsiteMetadataServiceInterface;
use App\Services\WebsiteMetadataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            WebsiteMetadataServiceInterface::class,
            WebsiteMetadataService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}