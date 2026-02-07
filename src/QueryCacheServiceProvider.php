<?php

namespace webO3\LaravelDbCache;

use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class QueryCacheServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/db-cache.php', 'db-cache');

        // Always register the factory - it checks the enabled flag per-connection
        // at creation time, falling back to the default connection when disabled.
        $this->app->singleton('db.factory', function ($app) {
            return new CachedConnectionFactory($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/db-cache.php' => config_path('db-cache.php'),
            ], 'db-cache-config');
        }

        // Inject db_cache config into the database connection config
        if (config('db-cache.enabled', false)) {
            $connections = Arr::wrap(config('db-cache.connection', 'mysql'));

            foreach ($connections as $connection) {
                config([
                    "database.connections.{$connection}.db_cache" => [
                        'enabled' => true,
                        'driver' => config('db-cache.driver', 'array'),
                        'ttl' => config('db-cache.ttl', 180),
                        'max_size' => config('db-cache.max_size', 1000),
                        'log_enabled' => config('db-cache.log_enabled', false),
                        'redis_connection' => config('db-cache.redis_connection', 'db_cache'),
                    ],
                ]);
            }
        }
    }
}
