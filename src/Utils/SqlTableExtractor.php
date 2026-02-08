<?php

namespace webO3\LaravelDbCache\Utils;

/**
 * Utility class to extract table names from SQL queries
 */
class SqlTableExtractor
{
    private const PATTERN = '/(?:'
        . 'FROM'
        . '|(?:(?:INNER|LEFT|RIGHT|CROSS|OUTER)\s+)?JOIN'
        . '|UPDATE'
        . '|(?:INSERT|REPLACE)\s+INTO'
        . '|DELETE\s+FROM'
        . '|TRUNCATE(?:\s+TABLE)?'
        . '|ALTER\s+TABLE'
        . '|DROP\s+TABLE(?:\s+IF\s+EXISTS)?'
        . ')\s+[`"\[]?([a-zA-Z0-9_]+)[`"\]]?/i';

    /**
     * Per-request cache of extracted tables keyed by SQL string
     */
    private static array $cache = [];

    /**
     * Extract table names from SQL query
     *
     * Results are cached per-request so repeated extraction of the same
     * query (e.g. during invalidation + stats) is free.
     *
     * @param string $sql
     * @return array
     */
    public static function extract(string $sql): array
    {
        if (isset(self::$cache[$sql])) {
            return self::$cache[$sql];
        }

        preg_match_all(self::PATTERN, $sql, $matches);

        $result = array_values(array_unique($matches[1]));
        self::$cache[$sql] = $result;

        return $result;
    }

    /**
     * Clear the per-request cache (useful for testing/benchmarking)
     */
    public static function resetCache(): void
    {
        self::$cache = [];
    }
}
