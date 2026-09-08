<?php

/*
 * This file is part of ygpynet/leaderboard.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

/*
 * Renames stored settings keys and group permissions from the legacy
 * 'huseyinfiliz-leaderboard.' prefix to 'ygpynet-leaderboard.' so sites that
 * enabled the extension before the rename keep their configured values and
 * guest grants. No-op for fresh installs (no legacy rows exist).
 */
return [
    'up' => function ($schema) {
        $connection = $schema->getConnection();

        $connection->table('settings')
            ->where('key', 'like', 'huseyinfiliz-leaderboard.%')
            ->update([
                'key' => $connection->raw("REPLACE(`key`, 'huseyinfiliz-leaderboard.', 'ygpynet-leaderboard.')"),
            ]);

        $connection->table('group_permission')
            ->where('permission', 'like', 'huseyinfiliz-leaderboard.%')
            ->update([
                'permission' => $connection->raw("REPLACE(permission, 'huseyinfiliz-leaderboard.', 'ygpynet-leaderboard.')"),
            ]);
    },

    'down' => function ($schema) {
        $connection = $schema->getConnection();

        $connection->table('settings')
            ->where('key', 'like', 'ygpynet-leaderboard.%')
            ->update([
                'key' => $connection->raw("REPLACE(`key`, 'ygpynet-leaderboard.', 'huseyinfiliz-leaderboard.')"),
            ]);

        $connection->table('group_permission')
            ->where('permission', 'like', 'ygpynet-leaderboard.%')
            ->update([
                'permission' => $connection->raw("REPLACE(permission, 'ygpynet-leaderboard.', 'huseyinfiliz-leaderboard.')"),
            ]);
    },
];
