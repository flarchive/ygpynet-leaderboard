<?php

namespace ygpynet\Leaderboard\Ranking\Category;

use Carbon\Carbon;
use ygpynet\Leaderboard\Ranking\Support\SchemaCache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Points board — straight from ygpynet/point-system's ledger: lifetime total
 * for the all-time ranking, net credited amount for period rankings. Serves
 * an empty board when point-system's tables don't exist (never enabled).
 */
class Points extends Category
{
    public function __construct(protected SchemaCache $schema)
    {
    }

    public function key(): string
    {
        return 'points';
    }

    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder
    {
        if (!$this->schema->hasTable('point_system_user_points')) {
            return null;
        }

        if ($periodStart !== null) {
            if (!$this->schema->hasTable('point_system_transactions')) {
                return null;
            }

            return $db->table('point_system_transactions')
                ->selectRaw('user_id, SUM(amount) as score')
                ->where('created_at', '>=', $periodStart)
                ->groupBy('user_id')
                ->havingRaw('SUM(amount) > 0');
        }

        return $db->table('point_system_user_points')
            ->selectRaw('user_id, lifetime as score')
            ->where('lifetime', '>', 0);
    }

    public function userColumn(): string
    {
        return 'user_id';
    }

    public function discussionColumn(): ?string
    {
        return null;
    }

    public function statKeys(): array
    {
        return ['topStreak'];
    }

    public function dailyScoreQuery(ConnectionInterface $db, Carbon $fromDate): ?\Illuminate\Database\Query\Builder
    {
        if (!$this->schema->hasTable('point_system_transactions')) {
            return null;
        }

        return $db->table('point_system_transactions')
            ->selectRaw('user_id, DATE(created_at) as date, SUM(amount) as score')
            ->where('created_at', '>=', $fromDate->copy()->startOfDay())
            ->groupBy('user_id', 'date');
    }
}
