<?php

/*
 * This file is part of huseyinfiliz/leaderboard.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

return [
    // Grants the new 'huseyinfiliz-leaderboard.viewLeaderboard' permission to every
    // existing group (including Guests) so upgrading doesn't suddenly hide the
    // leaderboard from anyone. Admins can uncheck specific groups afterwards.
    'up' => function ($schema) {
        $connection = $schema->getConnection();

        $groupIds = $connection->table('groups')->pluck('id');

        $rows = [];
        foreach ($groupIds as $groupId) {
            $rows[] = [
                'group_id' => $groupId,
                'permission' => 'huseyinfiliz-leaderboard.viewLeaderboard',
            ];
        }

        if (!empty($rows)) {
            $connection->table('group_permission')->insert($rows);
        }
    },

    'down' => function ($schema) {
        $schema->getConnection()->table('group_permission')
            ->where('permission', 'huseyinfiliz-leaderboard.viewLeaderboard')
            ->delete();
    },
];
