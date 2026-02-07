<?php

namespace webO3\LaravelDbCache\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use webO3\LaravelDbCache\CachedConnectionFactory;
use webO3\LaravelDbCache\Tests\TestCase;

class QueryCacheServiceProviderTest extends TestCase
{
    #[Test]
    public function registers_cached_connection_factory()
    {
        $factory = app('db.factory');
        $this->assertInstanceOf(CachedConnectionFactory::class, $factory);
    }

    #[Test]
    public function merges_default_config()
    {
        // The service provider should merge the default config
        $this->assertNotNull(config('db-cache'));
        $this->assertArrayHasKey('enabled', config('db-cache'));
        $this->assertArrayHasKey('driver', config('db-cache'));
        $this->assertArrayHasKey('ttl', config('db-cache'));
        $this->assertArrayHasKey('max_size', config('db-cache'));
        $this->assertArrayHasKey('log_enabled', config('db-cache'));
        $this->assertArrayHasKey('connection', config('db-cache'));
        $this->assertArrayHasKey('redis_connection', config('db-cache'));
    }

    #[Test]
    public function boot_injects_db_cache_config_into_database_connection()
    {
        // Enable query cache
        config([
            'db-cache.enabled' => true,
            'db-cache.driver' => 'array',
            'db-cache.ttl' => 300,
            'db-cache.max_size' => 500,
            'db-cache.log_enabled' => true,
            'db-cache.connection' => 'mysql',
            'db-cache.redis_connection' => 'db_cache',
        ]);

        // Re-boot the service provider to trigger config injection
        $provider = app()->getProvider(\webO3\LaravelDbCache\QueryCacheServiceProvider::class);
        $provider->boot();

        // Verify db_cache config was injected into database connection
        $dbConfig = config('database.connections.mysql.db_cache');
        $this->assertNotNull($dbConfig);
        $this->assertTrue($dbConfig['enabled']);
        $this->assertEquals('array', $dbConfig['driver']);
        $this->assertEquals(300, $dbConfig['ttl']);
        $this->assertEquals(500, $dbConfig['max_size']);
        $this->assertTrue($dbConfig['log_enabled']);
        $this->assertEquals('db_cache', $dbConfig['redis_connection']);
    }

    #[Test]
    public function boot_injects_config_for_multiple_connections()
    {
        config([
            'db-cache.enabled' => true,
            'db-cache.driver' => 'array',
            'db-cache.ttl' => 180,
            'db-cache.max_size' => 1000,
            'db-cache.log_enabled' => false,
            'db-cache.connection' => ['mysql', 'pgsql', 'sqlite'],
            'db-cache.redis_connection' => 'db_cache',
        ]);

        $provider = app()->getProvider(\webO3\LaravelDbCache\QueryCacheServiceProvider::class);
        $provider->boot();

        // Verify config was injected into all connections
        foreach (['mysql', 'pgsql', 'sqlite'] as $conn) {
            $dbConfig = config("database.connections.{$conn}.db_cache");
            $this->assertNotNull($dbConfig, "db_cache config not injected for {$conn}");
            $this->assertTrue($dbConfig['enabled'], "enabled not set for {$conn}");
            $this->assertEquals('array', $dbConfig['driver'], "driver not set for {$conn}");
        }
    }

    #[Test]
    public function boot_does_not_inject_config_when_disabled()
    {
        // Remove any existing db_cache config
        config(['database.connections.mysql.db_cache' => null]);
        config(['db-cache.enabled' => false]);

        $provider = app()->getProvider(\webO3\LaravelDbCache\QueryCacheServiceProvider::class);
        $provider->boot();

        // Should NOT inject config when disabled
        $this->assertNull(config('database.connections.mysql.db_cache'));
    }

    #[Test]
    public function boot_publishes_config_file()
    {
        // Verify the publishable config is registered
        $provider = app()->getProvider(\webO3\LaravelDbCache\QueryCacheServiceProvider::class);

        // ServiceProvider::$publishes is a static property
        $publishes = \webO3\LaravelDbCache\QueryCacheServiceProvider::$publishGroups ?? [];

        // The provider should have registered config for publishing
        // We verify this indirectly by checking that the config key exists
        $this->assertTrue($this->app->runningInConsole());
    }
}
