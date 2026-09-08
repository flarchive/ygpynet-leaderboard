<?php

namespace ygpynet\Leaderboard\Ranking\Category;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Replies board: comment posts other than the discussion's first post.
 */
class Posts extends Category
{
    public function key(): string
    {
        return 'posts';
    }

    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder
    {
        $query = $db->table('posts as p')
            ->where('p.type', 'comment')
            ->where('p.number', '>', 1)
            ->whereNull('p.hidden_at')
            ->selectRaw($this->col($db, 'p', 'user_id').' as user_id, COUNT(*) as score')
            ->groupBy('p.user_id');

        if ($periodStart !== null) {
            $query->where('p.created_at', '>=', $periodStart);
        }

        return $query;
    }

    public function userColumn(): string
    {
        return 'p.user_id';
    }

    public function discussionColumn(): ?string
    {
        return 'p.discussion_id';
    }

    public function statKeys(): array
    {
        return ['topStreak'];
    }

    public function dailyScoreQuery(ConnectionInterface $db, Carbon $fromDate): ?\Illuminate\Database\Query\Builder
    {
        return $db->table('posts as p')
            ->where('p.type', 'comment')
            ->where('p.number', '>', 1)
            ->whereNull('p.hidden_at')
            ->where('p.created_at', '>=', $fromDate->copy()->startOfDay())
            ->selectRaw($this->col($db, 'p', 'user_id').' as user_id, DATE('.$this->col($db, 'p', 'created_at').') as date, COUNT(*) as score')
            ->groupBy('p.user_id', 'date');
    }
}
