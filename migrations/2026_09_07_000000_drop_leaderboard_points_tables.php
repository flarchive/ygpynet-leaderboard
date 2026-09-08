<?php

/*
 * Point storage moved to ygpynet/point-system — drop the local ledger tables.
 */

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->dropIfExists('leaderboard_points');
        $schema->dropIfExists('leaderboard_user_totals');
    },

    'down' => function (Builder $schema) {
        // No down migration: historical rows live in point_system_transactions
        // once the data has been migrated; recreating empty tables here would
        // only mask the source of truth.
    },
];
