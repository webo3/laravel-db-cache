<?php

namespace webO3\LaravelDbCache\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use webO3\LaravelDbCache\Contracts\CachedConnection;

class ClearQueryCacheCommand extends Command
{
    protected $signature = 'db-cache:clear
                            {--connection= : Specific connection to clear (default: all cached connections)}
                            {--tenant= : Clear cache for a specific tenant ID (redis driver only)}';

    protected $description = 'Clear the database query cache';

    public function handle(): int
    {
        $connectionNames = $this->getConnectionNames();

        if (empty($connectionNames)) {
            $this->components->warn('No cached connections configured. Is DB_QUERY_CACHE_ENABLED=true?');
            return self::SUCCESS;
        }

        foreach ($connectionNames as $name) {
            $this->clearConnection($name);
        }

        return self::SUCCESS;
    }

    private function clearConnection(string $name): void
    {
        try {
            $connection = app('db')->connection($name);
        } catch (\Exception $e) {
            $this->components->error("Connection [{$name}] not found.");
            return;
        }

        if (!$connection instanceof CachedConnection) {
            $this->components->warn("Connection [{$name}] is not a cached connection, skipping.");
            return;
        }

        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $connection->setTenantContext($tenantId);
            $connection->clearQueryCache();
            $this->components->info("Cleared query cache for connection [{$name}], tenant [{$tenantId}].");
        } else {
            $connection->clearQueryCache();
            $this->components->info("Cleared query cache for connection [{$name}].");
        }
    }

    private function getConnectionNames(): array
    {
        $specific = $this->option('connection');

        if ($specific) {
            return is_string($specific) ? array_map('trim', explode(',', $specific)) : Arr::wrap($specific);
        }

        if (!config('db-cache.enabled', false)) {
            return [];
        }

        $raw = config('db-cache.connection', 'mysql');

        return is_string($raw) ? array_map('trim', explode(',', $raw)) : Arr::wrap($raw);
    }
}
