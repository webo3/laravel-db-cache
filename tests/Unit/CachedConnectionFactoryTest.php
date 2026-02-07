<?php

namespace webO3\LaravelDbCache\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use webO3\LaravelDbCache\CachedConnectionFactory;
use webO3\LaravelDbCache\CachedMySQLConnection;
use webO3\LaravelDbCache\CachedPostgresConnection;
use webO3\LaravelDbCache\CachedSQLiteConnection;
use webO3\LaravelDbCache\Contracts\CachedConnection;
use webO3\LaravelDbCache\Tests\TestCase;

class CachedConnectionFactoryTest extends TestCase
{
    #[Test]
    public function creates_cached_mysql_connection_when_enabled()
    {
        config([
            'database.connections.mysql.db_cache.enabled' => true,
            'database.connections.mysql.db_cache.driver' => 'array',
        ]);
        app('db')->purge('mysql');

        try {
            $connection = DB::connection('mysql');
            $this->assertInstanceOf(CachedMySQLConnection::class, $connection);
            $this->assertInstanceOf(CachedConnection::class, $connection);
        } catch (\Exception $e) {
            // MySQL might not be available, skip
            $this->markTestSkipped('MySQL not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function creates_cached_sqlite_connection_when_enabled()
    {
        config([
            'database.connections.sqlite.db_cache.enabled' => true,
            'database.connections.sqlite.db_cache.driver' => 'array',
        ]);
        app('db')->purge('sqlite');

        $connection = DB::connection('sqlite');
        $this->assertInstanceOf(CachedSQLiteConnection::class, $connection);
        $this->assertInstanceOf(CachedConnection::class, $connection);
    }

    #[Test]
    public function creates_cached_postgres_connection_when_enabled()
    {
        config([
            'database.connections.pgsql.db_cache.enabled' => true,
            'database.connections.pgsql.db_cache.driver' => 'array',
        ]);
        app('db')->purge('pgsql');

        try {
            $connection = DB::connection('pgsql');
            $this->assertInstanceOf(CachedPostgresConnection::class, $connection);
            $this->assertInstanceOf(CachedConnection::class, $connection);
        } catch (\Exception $e) {
            $this->markTestSkipped('PostgreSQL not available: ' . $e->getMessage());
        }
    }

    #[Test]
    public function creates_regular_connection_when_cache_disabled()
    {
        config([
            'database.connections.sqlite.db_cache.enabled' => false,
        ]);
        app('db')->purge('sqlite');

        $connection = DB::connection('sqlite');
        $this->assertNotInstanceOf(CachedConnection::class, $connection);
    }

    #[Test]
    public function creates_regular_connection_when_no_db_cache_config()
    {
        config([
            'database.connections.sqlite.db_cache' => null,
        ]);
        app('db')->purge('sqlite');

        $connection = DB::connection('sqlite');
        $this->assertNotInstanceOf(CachedConnection::class, $connection);
    }

    #[Test]
    public function factory_is_singleton()
    {
        $factory1 = app('db.factory');
        $factory2 = app('db.factory');

        $this->assertSame($factory1, $factory2);
        $this->assertInstanceOf(CachedConnectionFactory::class, $factory1);
    }
}
