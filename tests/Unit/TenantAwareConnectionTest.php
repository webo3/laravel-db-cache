<?php

namespace webO3\LaravelDbCache\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use webO3\LaravelDbCache\Contracts\CachedConnection;
use webO3\LaravelDbCache\Tests\TestCase;

/**
 * Tests for tenant-aware caching at the connection level.
 *
 * Validates:
 * - setTenantContext() exists and works on cached connections
 * - Caching works normally without tenant context (backwards compatible)
 * - Caching works after setTenantContext() is called
 */
class TenantAwareConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get a cached MySQL connection with specific config
     */
    private function getCachedConnection(string $driver = 'array')
    {
        config([
            'db-cache.enabled' => true,
            'db-cache.driver' => $driver,
            'database.connections.mysql.db_cache.enabled' => true,
            'database.connections.mysql.db_cache.driver' => $driver,
            'database.connections.mysql.db_cache.ttl' => 300,
            'database.connections.mysql.db_cache.max_size' => 1000,
            'database.connections.mysql.db_cache.log_enabled' => false,
        ]);

        app('db')->purge('mysql');
        $connection = app('db')->connection('mysql');

        if (!$connection instanceof CachedConnection) {
            $this->markTestSkipped('CachedConnection is not configured');
        }

        $connection->clearQueryCache();

        return $connection;
    }

    // ===================================
    // setTenantContext ON CONNECTION
    // ===================================

    #[Test]
    public function set_tenant_context_method_exists_on_connection()
    {
        $connection = $this->getCachedConnection();

        $this->assertTrue(
            method_exists($connection, 'setTenantContext'),
            'CachedConnection should have setTenantContext method'
        );
    }

    #[Test]
    public function caching_works_after_setting_tenant_context()
    {
        $connection = $this->getCachedConnection();

        $connection->setTenantContext('org_42');

        $connection->select('SELECT 1');
        $connection->select('SELECT 1');

        $stats = $connection->getCacheStats();
        $this->assertEquals(1, $stats['cached_queries_count']);
        $this->assertEquals(1, $stats['total_cache_hits']);
    }

    #[Test]
    public function caching_works_without_tenant_context()
    {
        $connection = $this->getCachedConnection();

        // Caching should work immediately without tenant context (main connection)
        $connection->select('SELECT 1');
        $connection->select('SELECT 1');

        $stats = $connection->getCacheStats();
        $this->assertEquals(1, $stats['cached_queries_count']);
        $this->assertEquals(1, $stats['total_cache_hits']);
    }

    #[Test]
    public function tenant_switch_flushes_cache_on_connection()
    {
        $connection = $this->getCachedConnection();

        $connection->setTenantContext('org_1');
        $connection->select('SELECT 1');

        $stats = $connection->getCacheStats();
        $this->assertEquals(1, $stats['cached_queries_count']);

        // Switch tenant — array driver flushes cache
        $connection->setTenantContext('org_2');

        $stats = $connection->getCacheStats();
        $this->assertEquals(0, $stats['cached_queries_count']);
    }

    #[Test]
    public function queries_and_mutations_work_with_tenant_context()
    {
        $connection = $this->getCachedConnection();
        $connection->setTenantContext('org_99');

        // Create temp table
        $connection->statement('CREATE TEMPORARY TABLE test_tenant_conn (id INT PRIMARY KEY, name VARCHAR(255))');
        $connection->insert('INSERT INTO test_tenant_conn (id, name) VALUES (?, ?)', [1, 'test']);

        // SELECT should be cached
        $result1 = $connection->select('SELECT * FROM test_tenant_conn');
        $result2 = $connection->select('SELECT * FROM test_tenant_conn');
        $this->assertCount(1, $result1);
        $this->assertEquals($result1, $result2);

        $stats = $connection->getCacheStats();
        $this->assertEquals(1, $stats['cached_queries_count']);
        $this->assertEquals(1, $stats['total_cache_hits']);

        // Mutation should invalidate cache
        $connection->insert('INSERT INTO test_tenant_conn (id, name) VALUES (?, ?)', [2, 'test2']);

        $stats = $connection->getCacheStats();
        $this->assertEquals(0, $stats['cached_queries_count']);

        $connection->statement('DROP TEMPORARY TABLE IF EXISTS test_tenant_conn');
    }
}
