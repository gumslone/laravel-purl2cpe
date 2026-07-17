<?php

namespace Gumslone\Purl2Cpe;

use Gumslone\Purl2Cpe\Commands\ImportCommand;
use Gumslone\Purl2Cpe\Commands\SyncCommand;
use Illuminate\Support\ServiceProvider;

class Purl2CpeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/purl2cpe.php', 'purl2cpe');

        $this->app->singleton(Purl2Cpe::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/purl2cpe.php' => $this->app->configPath('purl2cpe.php'),
            ], 'purl2cpe-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'purl2cpe-migrations');

            $this->commands([
                ImportCommand::class,
                SyncCommand::class,
            ]);
        }
    }
}
