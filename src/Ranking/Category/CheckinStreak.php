<?php

namespace ygpynet\Leaderboard\Ranking\Category;

use Carbon\Carbon;
use ygpynet\Leaderboard\Ranking\Support\SchemaCache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Check-in board: current streak for all-time, number of checked-in days
 * within the period otherwise (point-system stores one row per day).
 * Requires point-system's tables.
 */
class CheckinStreak extends Category
{
    public function __construct(protected SchemaCache $schema)
    {
    }

    public function key(): string
    {
        return 'checkin_streak';
    }

    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder
    {
        if ($periodStart !== null) {
            if (!$this->schema->hasTable('point_system_checkin_days')) {
                return null;
            }

            return $db->table('point_system_checkin_days')
                ->selectRaw('user_id, COUNT(*) as score')
                ->where('date', '>=', $periodStart->toDateString())
                ->groupBy('user_id');
        }

        if (!$this->schema->hasTable('point_system_user_points')) {
            return null;
        }

        return $db->table('point_system_user_points')
            ->selectRaw('user_id, checkin_streak as score')
            ->where('checkin_streak', '>', 0);
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

    /**
     * Daily board for the check-in category: one row per checked-in day.
     * Score is negated seq, so the day's EARLIEST check-in wins the day —
     * "consecutively first to check in" is the streak semantics here.
     */
    public function dailyScoreQuery(ConnectionInterface $db, Carbon $fromDate): ?\Illuminate\Database\Query\Builder
    {
        if (!$this->schema->hasTable('point_system_checkin_days')) {
            return null;
        }

        return $db->table('point_system_checkin_days')
            ->selectRaw('user_id, date, CAST(seq AS SIGNED) * -1 as score')
            ->where('date', '>=', $fromDate->toDateString());
    }
}
