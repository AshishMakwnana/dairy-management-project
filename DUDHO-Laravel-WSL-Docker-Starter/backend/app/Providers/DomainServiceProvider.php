<?php

namespace App\Providers;

use App\Contracts\HealthService;
use App\Services\SystemHealthService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(HealthService::class, SystemHealthService::class);
    }
}
