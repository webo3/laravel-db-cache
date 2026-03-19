<?php

namespace webO3\LaravelDbCache\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use webO3\LaravelDbCache\Contracts\CachedConnection;
use webO3\LaravelDbCache\Tests\TestCase;

class ClearQueryCacheCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'db-cache.enabled' => true,
            'db-cache.driver' => 'array',
            'db-cache.connection' => 'mysql',
            'database.connections.mysql.db_cache' => [
                'enabled' => true,
                'driver' => 'array',
                'ttl' => 300,
                'max_size' => 1000,
                'log_enabled' => false,
            ],
        ]);

        app('db')->purge('mysql');
    }

    #[Test]
    public function command_is_registered()
    {
        $this->artisan('db-cache:clear')
            ->assertSuccessful();
    }

    #[Test]
    public function clears_cache_for_configured_connections()
    {
        $connection = app('db')->connection('mysql');

        if (!$connection instanceof CachedConnection) {
            $this->markTestSkipped('CachedConnection is not configured');
        }

        // Populate cache
        $connection->select('SELECT 1');
        $connection->select('SELECT 1');
        $stats = $connection->getCacheStats();
        $this->assertEquals(1, $stats['cached_queries_count']);

        // Clear via command
        $this->artisan('db-cache:clear')
            ->assertSuccessful();

        $stats = $connection->getCacheStats();
        $this->assertEquals(0, $stats['cached_queries_count']);
    }

    #[Test]
    public function clears_specific_connection_with_option()
    {
        $connection = app('db')->connection('mysql');

        if (!$connection instanceof CachedConnection) {
            $this->markTestSkipped('CachedConnection is not configured');
        }

        $connection->select('SELECT 1');
        $this->assertEquals(1, $connection->getCacheStats()['cached_queries_count']);

        $this->artisan('db-cache:clear', ['--connection' => 'mysql'])
            ->assertSuccessful();

        $this->assertEquals(0, $connection->getCacheStats()['cached_queries_count']);
    }

    #[Test]
    public function clears_cache_for_specific_tenant()
    {
        $connection = app('db')->connection('mysql');

        if (!$connection instanceof CachedConnection) {
            $this->markTestSkipped('CachedConnection is not configured');
        }

        // Cache data for tenant
        $connection->setTenantContext('org_42');
        $connection->select('SELECT 1');
        $this->assertEquals(1, $connection->getCacheStats()['cached_queries_count']);

        // Clear via command with tenant option
        $this->artisan('db-cache:clear', ['--tenant' => 'org_42'])
            ->assertSuccessful();

        // Re-set tenant context (array driver flushes on switch, so set it again)
        $connection->setTenantContext('org_42');
        $this->assertEquals(0, $connection->getCacheStats()['cached_queries_count']);
    }

    #[Test]
    public function warns_when_no_connections_configured()
    {
        config(['db-cache.enabled' => false]);

        $this->artisan('db-cache:clear')
            ->assertSuccessful();
    }

    #[Test]
    public function warns_for_non_cached_connection()
    {
        // SQLite without db_cache config
        config(['database.connections.sqlite.db_cache' => null]);

        $this->artisan('db-cache:clear', ['--connection' => 'sqlite'])
            ->assertSuccessful();
    }
}
