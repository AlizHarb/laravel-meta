<?php

namespace AlizHarb\Meta;

use Illuminate\Support\ServiceProvider;

class MetaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations/create_metas_table.php.stub' => database_path('migrations/2025_08_25_000000_create_metas_table.php'),
            ], ['meta-migrations', 'migrations']);

            // Publish config file.
            $this->publishes([
                __DIR__ . '/../config/meta.php' => config_path('meta.php'),
            ], ['meta-config', 'config']);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/meta.php', 'meta');
    }
}