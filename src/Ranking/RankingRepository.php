<?php

namespace HuseyinFiliz\Leaderboard\Ranking;

use Carbon\Carbon;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Api\Data\LeaderboardEntryData;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;

/**
 * Executes a ranking: builds the category query, applies exclusions, adds a
 * short-lived result cache, computes competition ranks (ties share a rank)
 * and hydrates users in one query.
 */
class RankingRepository
{
    /** Hard upper bound of a computed board: rows fetched, ranked and cached. */
    public const BOARD_LIMIT = 1000;

    /** Cache TTL in seconds. Rankings are not real-time critical; one minute
     * keeps DB load flat under bursts while staying fresh enough. */
    protected int $ttl = 60;

    public function __construct(
        protected ConnectionInterface $db,
        protected CategoryRegistry $categories,
        protected Cache $cache
    ) {
    }

    /**
     * @return array{entries: LeaderboardEntryData[], total: int}
     */
    public function get(
        string $categoryKey,
        ?Carbon $periodStart,
        int $offset,
        int $limit,
        Exclusions $exclusions
    ): array {
        $definition = $this->categories->get($categoryKey);

        if ($definition === null) {
            return ['entries' => [], 'total' => 0];
        }

        // The score query itself can opt out (missing columns / extension);
        // serve an empty board instead of crashing.
        if ($definition->scoreQuery($this->db, $periodStart) === null) {
            return ['entries' => [], 'total' => 0];
        }

        $cacheKey = $this->cacheKey($definition, $periodStart, $exclusions);

        $result = $this->cacheGet($cacheKey) ?? $this->compute($definition, $periodStart, $exclusions, $cacheKey);

        // Slice + hydrate per request (cheap; the cached part is the query).
        $rows = array_slice($result['rows'], $offset, $limit);
        $users = User::whereIn('id', array_column($rows, 'user_id'))->get()->keyBy('id');

        $entries = [];
        foreach ($rows as $index => $row) {
            $user = $users->get($row['user_id']);
            if (!$user) {
                continue;
            }

            $entries[] = new LeaderboardEntryData(
                (int) $row['user_id'],
                (int) $row['score'],
                (int) $row['rank'],
                $user
            );
        }

        return ['entries' => $entries, 'total' => $result['total']];
    }

    /**
     * Full ordered board. Bounded on purpose: the podium/contenders/honorable
     * structure only renders the first pages, and an unbounded result set
     * would let a large forum blow the cache entry size.
     *
     * @return array{rows: array<int, array{user_id: int, score: int, rank: int}>, total: int}
     */
    protected function compute(CategoryDefinition $definition, ?Carbon $periodStart, Exclusions $exclusions, string $cacheKey): array
    {
        $query = $definition->scoreQuery($this->db, $periodStart);

        $exclusions->applyGroupExclusion($query, $definition->userColumn());
        $exclusions->applyTagExclusion($query, $definition->discussionColumn());

        $rows = array_map(
            fn ($row) => (array) $row,
            $query
                ->orderByDesc('score')
                ->orderBy('user_id')
                ->limit(self::BOARD_LIMIT)
                ->get()
                ->all()
        );

        // Competition ranking: ties share a rank, the next distinct score
        // follows after the gap (1, 1, 3 …).
        $rank = 0;
        $prevScore = null;

        foreach ($rows as $index => &$row) {
            $row['user_id'] = (int) $row['user_id'];
            $row['score'] = (int) $row['score'];

            if ($prevScore === null || $row['score'] !== $prevScore) {
                $rank = $index + 1;
                $prevScore = $row['score'];
            }

            $row['rank'] = $rank;
        }
        unset($row);

        $result = [
            'rows' => $rows,
            'total' => count($rows),
        ];

        $this->cachePut($cacheKey, $result);

        return $result;
    }

    /**
     * Bucket the period start to the day: keys stay stable within a day, and
     * old periods don't accumulate unbounded keys. The exclusions hash makes
     * setting changes produce fresh rankings immediately.
     */
    protected function cacheKey(CategoryDefinition $definition, ?Carbon $periodStart, Exclusions $exclusions): string
    {
        $periodPart = $periodStart === null ? 'all' : $periodStart->format('Ymd');

        return "lb_rank_{$definition->key()}_{$periodPart}_{$exclusions->hash()}";
    }

    protected function cacheGet(string $key): mixed
    {
        try {
            $value = $this->cache->get($key);

            return is_array($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cachePut(string $key, array $value): void
    {
        try {
            $this->cache->put($key, $value, $this->ttl);
        } catch (\Throwable) {
            // Cache unavailable — serve uncached.
        }
    }
}
