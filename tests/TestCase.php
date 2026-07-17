<?php

namespace Gumslone\Purl2Cpe\Tests;

use Gumslone\Purl2Cpe\Purl2CpeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [Purl2CpeServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Purl2Cpe' => \Gumslone\Purl2Cpe\Facades\Purl2Cpe::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
