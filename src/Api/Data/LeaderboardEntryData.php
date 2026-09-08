<?php

namespace ygpynet\Leaderboard\Api\Data;

use Flarum\User\User;

class LeaderboardEntryData
{
    public function __construct(public int $id, public int $score, public int $rank, public ?User $user = null)
    {
    }
}
