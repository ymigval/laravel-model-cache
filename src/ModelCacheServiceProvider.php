<?php

namespace YMigVal\LaravelModelCache;

use Illuminate\Support\ServiceProvider;
use Ymigval\LaravelModelCache\Console\Commands\ClearModelCacheCommand;

class ModelCacheServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/model-cache.php' => config_path('model-cache.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearModelCacheCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/model-cache.php', 'model-cache'
        );
    }
}
