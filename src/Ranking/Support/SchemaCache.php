<?php

namespace HuseyinFiliz\Leaderboard\Ranking\Support;

/**
 * Caches expensive schema introspection (hasColumn probes information_schema)
 * for the lifetime of the service — under php-fpm that is per request; under
 * long-running workers the worst case is a stale answer until recycle, same
 * trade-off the rest of the pipeline makes.
 */
class SchemaCache
{
    /** @var array<string, bool> */
    protected array $columns = [];

    /** @var array<string, bool> */
    protected array $tables = [];

    public function __construct(protected \Illuminate\Database\ConnectionInterface $db)
    {
    }

    public function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (!array_key_exists($key, $this->columns)) {
            $this->columns[$key] = $this->db->getSchemaBuilder()->hasColumn($table, $column);
        }

        return $this->columns[$key];
    }

    public function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            $this->tables[$table] = $this->db->getSchemaBuilder()->hasTable($table);
        }

        return $this->tables[$table];
    }
}
