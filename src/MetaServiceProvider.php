<?php

declare(strict_types=1);

namespace Karnoweb\Meta;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Meta\Services\MetaQuery;
use Karnoweb\Meta\Services\MetaService;

class MetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/meta.php', 'meta');

        $this->app->singleton(MetaService::class);
        $this->app->singleton(MetaQuery::class);
        $this->app->singleton(MetaManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/meta.php' => config_path('meta.php'),
            ], 'meta-config');
        }
    }
}
