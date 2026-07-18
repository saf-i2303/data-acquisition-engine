<?php

namespace App\Providers;

use App\Services\Contracts\DomainIntelligenceServiceInterface;
use App\Services\Contracts\LocationFinderServiceInterface;
use App\Services\Contracts\WebsiteMetadataServiceInterface;
use App\Services\DomainIntelligenceService;
use App\Services\LocationFinderService;
use App\Services\WebsiteMetadataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            WebsiteMetadataServiceInterface::class,
            WebsiteMetadataService::class
        );

        $this->app->bind(
            DomainIntelligenceServiceInterface::class,
            DomainIntelligenceService::class
        );

        $this->app->bind(
            LocationFinderServiceInterface::class,
            LocationFinderService::class
        );
    }

    public function boot(): void
    {
        //
    }
}