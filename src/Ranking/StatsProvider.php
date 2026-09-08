<?php

namespace ygpynet\Leaderboard\Ranking;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;

/**
 * Resolves the secondary per-user metrics shown on podium cards
 * (repliesCount, likesReceivedCount, …). Each resolver is a batched aggregate
 * over the page's users — exactly one query per stat, not per user.
 */
class StatsProvider
{
    public function __construct(protected ConnectionInterface $db)
    {
    }

    /**
     * @param string[] $statKeys  stats wanted for the current category
     * @param int[]    $userIds
     * @return array<int, array<string, int>>  userId => statKey => value
     */
    public function forUsers(array $statKeys, array $userIds, ?Carbon $periodStart, ?CategoryDefinition $definition = null, ?Exclusions $exclusions = null): array
    {
        if (empty($statKeys) || empty($userIds)) {
            return [];
        }

        $resolved = [];

        foreach (array_unique($statKeys) as $key) {
            if ($key === 'topStreak') {
                // The check-in board's natural secondary stat is the user's own
                // current check-in streak — "who topped a day" doesn't make
                // sense when every day's board is the same users checking in.
                if ($definition !== null && $definition->key() === 'checkin_streak') {
                    $resolved[$key] = $this->checkinStreaks($userIds);
                    continue;
                }

                $resolved[$key] = $definition && $exclusions
                    ? $this->topStreak($definition, $exclusions, $userIds)
                    : [];
                continue;
            }

            $resolved[$key] = $this->resolve($key, $userIds, $periodStart);
        }

        $stats = array_fill_keys($userIds, []);

        foreach ($userIds as $userId) {
            $row = [];
            foreach ($resolved as $key => $map) {
                $row[$key] = (int) ($map[$userId] ?? 0);
            }
            $stats[$userId] = $row;
        }

        return $stats;
    }

    /**
     * Current consecutive check-in days per user, from point-system's
     * checkin_streak column. point-system only updates the streak when the
     * user checks in (lazy update), so a stale streak can linger after the
     * user stopped checking in — validate it against last_checkin_date:
     * only a streak continued into today or yesterday is still standing.
     *
     * @param int[] $userIds
     * @return array<int, int>
     */
    protected function checkinStreaks(array $userIds): array
    {
        $validFrom = Carbon::yesterday()->toDateString();

        return $this->db->table('point_system_user_points')
            ->whereIn('user_id', $userIds)
            ->where('checkin_streak', '>', 0)
            ->where('last_checkin_date', '>=', $validFrom)
            ->pluck('checkin_streak', 'user_id')
            ->all();
    }

    /**
     * How many consecutive days each user has been the day's #1 on this
     * category's daily board. Ties for a day's top score go to the smaller
     * user id (matching the ranking's ORDER BY). If the user is not today's
     * winner, the streak counts back from yesterday (i.e. their standing
     * streak is preserved until someone else tops a day).
     *
     * @param int[] $userIds
     * @return array<int, int>  userId => consecutive days
     */
    public function topStreak(CategoryDefinition $definition, Exclusions $exclusions, array $userIds, int $windowDays = 60): array
    {
        $from = Carbon::today()->subDays($windowDays - 1);

        $query = $definition->dailyScoreQuery($this->db, $from);

        if ($query === null) {
            return [];
        }

        $exclusions->applyGroupExclusion($query, $definition->userColumn());
        $exclusions->applyTagExclusion($query, $definition->discussionColumn());

        // Group scores by day in PHP (avoids window functions for MySQL 5.7).
        // Rows are sorted by user id within a day so the winner loop's
        // first-seen-wins tie-break is deterministic (smallest user id),
        // matching the board's ORDER BY score DESC, user_id ASC.
        $daily = [];
        foreach ($query->orderBy('user_id')->get() as $row) {
            $day = substr((string) $row->date, 0, 10);
            $daily[$day][(int) $row->user_id] = (int) $row->score;
        }

        // Day winner: highest score, ties to the smallest user id.
        $winner = [];
        foreach ($daily as $day => $scores) {
            $bestId = null;
            $bestScore = null;
            foreach ($scores as $uid => $score) {
                if ($bestScore === null || $score > $bestScore) {
                    $bestScore = $score;
                    $bestId = $uid;
                }
            }
            if ($bestId !== null) {
                $winner[$day] = $bestId;
            }
        }

        $streaks = [];
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        foreach (array_unique($userIds) as $userId) {
            // Anchor on today when the user tops it, otherwise count the
            // streak that stands as of yesterday.
            $day = ($winner[$today] ?? null) === $userId
                ? Carbon::today()
                : Carbon::yesterday();

            $streak = 0;
            while (($winner[$day->toDateString()] ?? null) === $userId) {
                $streak++;
                $day->subDay();
            }

            if ($streak > 0) {
                $streaks[$userId] = $streak;
            }
        }

        return $streaks;
    }

    /** @return array<int|string, int>  userId => count */
    protected function resolve(string $key, array $userIds, ?Carbon $periodStart): array
    {
        $db = $this->db;

        switch ($key) {
            case 'repliesCount':
                $q = $db->table('posts')
                    ->where('type', 'comment')
                    ->where('number', '>', 1)
                    ->whereNull('hidden_at')
                    ->whereIn('user_id', $userIds)
                    ->selectRaw('user_id, COUNT(*) as c')
                    ->groupBy('user_id');

                if ($periodStart !== null) {
                    $q->where('created_at', '>=', $periodStart);
                }

                return $q->pluck('c', 'user_id')->all();

            case 'discussionsCount':
                $q = $db->table('discussions')
                    ->whereNull('hidden_at')
                    ->whereIn('user_id', $userIds)
                    ->selectRaw('user_id, COUNT(*) as c')
                    ->groupBy('user_id');

                if ($periodStart !== null) {
                    $q->where('created_at', '>=', $periodStart);
                }

                return $q->pluck('c', 'user_id')->all();

            case 'likesReceivedCount':
                return $this->likesCount('p.user_id', $userIds, $periodStart);

            case 'likesGivenCount':
                return $this->likesCount('pl.user_id', $userIds, $periodStart);

            default:
                return [];
        }
    }

    /** @return array<int|string, int>  userId => count */
    protected function likesCount(string $ownerColumn, array $userIds, ?Carbon $periodStart): array
    {
        $byPl = $ownerColumn === 'pl.user_id';
        $prefix = $this->db->getTablePrefix();

        $q = $this->db->table('post_likes as pl')
            ->join('posts as p', 'p.id', '=', 'pl.post_id')
            ->whereColumn('p.user_id', '!=', 'pl.user_id')
            ->whereNull('p.hidden_at')
            ->whereIn($ownerColumn, $userIds)
            ->selectRaw('COUNT(*) as c, '.($byPl ? $prefix.'pl.user_id' : $prefix.'p.user_id').' as user_id')
            ->groupBy($ownerColumn);

        if ($periodStart !== null) {
            $q->where('pl.created_at', '>=', $periodStart);
        }

        return $q->pluck('c', 'user_id')->all();
    }
}
