<?php

namespace webO3\LaravelDbCache\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use webO3\LaravelDbCache\Drivers\ArrayQueryCacheDriver;
use webO3\LaravelDbCache\Drivers\NullQueryCacheDriver;

/**
 * Tests for tenant-aware cache isolation at the driver level.
 *
 * Validates:
 * - ArrayQueryCacheDriver flushes cache on tenant switch
 * - ArrayQueryCacheDriver does not flush when same tenant is set again
 * - NullQueryCacheDriver accepts setTenantContext without error
 */
class TenantAwareCacheTest extends TestCase
{
    private ArrayQueryCacheDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new ArrayQueryCacheDriver(['max_size' => 1000, 'log_enabled' => false]);
        $this->driver->flush();
    }

    protected function tearDown(): void
    {
        $this->driver->flush();
        parent::tearDown();
    }

    // ===================================
    // ARRAY DRIVER TENANT ISOLATION
    // ===================================

    #[Test]
    public function set_tenant_context_flushes_cache_on_tenant_switch()
    {
        $this->driver->put('key1', ['data1'], 'SELECT * FROM users', microtime(true));
        $this->driver->put('key2', ['data2'], 'SELECT * FROM posts', microtime(true));

        $this->assertEquals(2, $this->driver->getStats()['cached_queries_count']);

        $this->driver->setTenantContext('tenant_1');

        // Cache should be flushed after switching tenant
        $this->assertEquals(0, $this->driver->getStats()['cached_queries_count']);
        $this->assertNull($this->driver->get('key1'));
        $this->assertNull($this->driver->get('key2'));
    }

    #[Test]
    public function set_tenant_context_does_not_flush_when_same_tenant()
    {
        $this->driver->setTenantContext('tenant_1');

        $this->driver->put('key1', ['data1'], 'SELECT * FROM users', microtime(true));
        $this->driver->put('key2', ['data2'], 'SELECT * FROM posts', microtime(true));

        $this->assertEquals(2, $this->driver->getStats()['cached_queries_count']);

        // Setting the same tenant again should NOT flush
        $this->driver->setTenantContext('tenant_1');

        $this->assertEquals(2, $this->driver->getStats()['cached_queries_count']);
        $this->assertNotNull($this->driver->get('key1'));
        $this->assertNotNull($this->driver->get('key2'));
    }

    #[Test]
    public function switching_tenants_isolates_cache_data()
    {
        // Tenant A caches data
        $this->driver->setTenantContext('tenant_a');
        $this->driver->put('key1', ['tenant_a_data'], 'SELECT * FROM users', microtime(true));

        $this->assertEquals(1, $this->driver->getStats()['cached_queries_count']);

        // Switch to Tenant B — cache should be flushed
        $this->driver->setTenantContext('tenant_b');
        $this->assertEquals(0, $this->driver->getStats()['cached_queries_count']);

        // Tenant B caches different data
        $this->driver->put('key1', ['tenant_b_data'], 'SELECT * FROM users', microtime(true));

        $cached = $this->driver->get('key1');
        $this->assertEquals(['tenant_b_data'], $cached['result']);
    }

    #[Test]
    public function set_tenant_context_clears_table_index()
    {
        $this->driver->put('key1', ['data'], 'SELECT * FROM users WHERE id = 1', microtime(true));

        // Verify table index is populated (invalidation should work)
        $invalidated = $this->driver->invalidateTables(['users'], 'INSERT INTO users VALUES (1)');
        $this->assertEquals(1, $invalidated);

        // Re-add and switch tenant
        $this->driver->put('key1', ['data'], 'SELECT * FROM users WHERE id = 1', microtime(true));
        $this->driver->setTenantContext('new_tenant');

        // Table index should be cleared — invalidation should find nothing
        $invalidated = $this->driver->invalidateTables(['users'], 'INSERT INTO users VALUES (1)');
        $this->assertEquals(0, $invalidated);
    }

    #[Test]
    public function multiple_tenant_switches_work_correctly()
    {
        $tenants = ['org_1', 'org_2', 'org_3', 'org_1'];

        foreach ($tenants as $i => $tenant) {
            $this->driver->setTenantContext($tenant);
            $this->driver->put("key_{$i}", ["data_{$i}"], "SELECT {$i}", microtime(true));

            $this->assertEquals(1, $this->driver->getStats()['cached_queries_count'],
                "Expected 1 cached query after switching to {$tenant}");
        }
    }

    // ===================================
    // NULL DRIVER TENANT SUPPORT
    // ===================================

    #[Test]
    public function null_driver_accepts_tenant_context_without_error()
    {
        $nullDriver = new NullQueryCacheDriver();

        // Should not throw
        $nullDriver->setTenantContext('tenant_1');
        $nullDriver->setTenantContext('tenant_2');

        $this->assertEquals(0, $nullDriver->getStats()['cached_queries_count']);
    }
}
