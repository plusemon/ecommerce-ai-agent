<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AgentService;

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AgentService::class, function ($app) {
            return new AgentService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
