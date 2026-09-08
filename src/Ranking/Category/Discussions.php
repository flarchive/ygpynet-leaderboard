<?php

namespace HuseyinFiliz\Leaderboard\Ranking\Category;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Discussions board: threads started (hidden ones excluded).
 */
class Discussions extends Category
{
    public function key(): string
    {
        return 'discussions';
    }

    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder
    {
        $query = $db->table('discussions as d')
            ->whereNull('d.hidden_at')
            ->selectRaw($this->col($db, 'd', 'user_id').' as user_id, COUNT(*) as score')
            ->groupBy('d.user_id');

        if ($periodStart !== null) {
            $query->where('d.created_at', '>=', $periodStart);
        }

        return $query;
    }

    public function userColumn(): string
    {
        return 'd.user_id';
    }

    public function discussionColumn(): ?string
    {
        return 'd.id';
    }

    public function statKeys(): array
    {
        return ['topStreak'];
    }

    public function dailyScoreQuery(ConnectionInterface $db, Carbon $fromDate): ?\Illuminate\Database\Query\Builder
    {
        return $db->table('discussions as d')
            ->whereNull('d.hidden_at')
            ->where('d.created_at', '>=', $fromDate->copy()->startOfDay())
            ->selectRaw($this->col($db, 'd', 'user_id').' as user_id, DATE('.$this->col($db, 'd', 'created_at').') as date, COUNT(*) as score')
            ->groupBy('d.user_id', 'date');
    }
}
