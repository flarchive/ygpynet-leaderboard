<?php

namespace ygpynet\Leaderboard\Ranking\Category;

use Carbon\Carbon;
use ygpynet\Leaderboard\Ranking\Support\SchemaCache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Likes boards, parameterised by which side of the like owns the score:
 * "likes_received" ranks post authors, "likes_given" ranks likers.
 * Self-likes are excluded on both, mirroring the point earning rules.
 */
abstract class LikesBase extends Category
{
    public function __construct(protected string $ownerAlias)
    {
    }

    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder
    {
        $query = $db->table('post_likes as pl')
            ->join('posts as p', 'p.id', '=', 'pl.post_id')
            ->whereColumn('p.user_id', '!=', 'pl.user_id')
            ->whereNull('p.hidden_at')
            ->selectRaw($this->col($db, $this->ownerAlias, 'user_id').' as user_id, COUNT(*) as score')
            ->groupBy($this->ownerAlias.'.user_id');

        if ($periodStart !== null) {
            $query->where('pl.created_at', '>=', $periodStart);
        }

        return $query;
    }

    public function userColumn(): string
    {
        return $this->ownerAlias.'.user_id';
    }

    public function discussionColumn(): ?string
    {
        return 'p.discussion_id';
    }

    public function dailyScoreQuery(ConnectionInterface $db, \Carbon\Carbon $fromDate): ?\Illuminate\Database\Query\Builder
    {
        return $db->table('post_likes as pl')
            ->join('posts as p', 'p.id', '=', 'pl.post_id')
            ->whereColumn('p.user_id', '!=', 'pl.user_id')
            ->whereNull('p.hidden_at')
            ->where('pl.created_at', '>=', $fromDate->copy()->startOfDay())
            ->selectRaw($this->col($db, $this->ownerAlias, 'user_id').' as user_id, DATE('.$this->col($db, 'pl', 'created_at').') as date, COUNT(*) as score')
            ->groupBy($this->ownerAlias.'.user_id', 'date');
    }
}
