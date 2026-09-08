<?php

namespace ygpynet\Leaderboard\Ranking\Category;

use ygpynet\Leaderboard\Ranking\CategoryDefinition;
use Illuminate\Database\ConnectionInterface;

/**
 * Shared plumbing for the built-in category definitions: raw SQL fragments
 * must qualify aliased columns with the table prefix manually (e.g.
 * "fla_p.user_id"), since Laravel only auto-prefixes wrapped references.
 */
abstract class Category implements CategoryDefinition
{
    protected function col(ConnectionInterface $db, string $alias, string $column): string
    {
        return $db->getTablePrefix().$alias.'.'.$column;
    }
}
