<?php

namespace HuseyinFiliz\Leaderboard\Api\Controller;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Api\LeaderboardSerializer;
use HuseyinFiliz\Leaderboard\Ranking\CategoryRegistry;
use HuseyinFiliz\Leaderboard\Ranking\Exclusions;
use HuseyinFiliz\Leaderboard\Ranking\ExclusionsFactory;
use HuseyinFiliz\Leaderboard\Ranking\RateLimiter;
use HuseyinFiliz\Leaderboard\Ranking\RankingRepository;
use HuseyinFiliz\Leaderboard\Ranking\StatsProvider;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Thin HTTP shell: parses/validates the request, delegates to the ranking
 * pipeline (category registry → repository → stats) and serializes.
 * All business rules live in src/Ranking.
 */
class ListLeaderboardController implements RequestHandlerInterface
{
    /** Sections map to fixed windows of the board the frontend renders. */
    protected const SECTIONS = [
        'podium' => [0, 3],
        'contenders' => [3, 7],
    ];

    /** Honorable mentions start after these fixed windows. */
    protected const HONORABLE_BASE = 10;

    protected const PERIODS = ['all', 'daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

    public function __construct(
        protected RankingRepository $ranking,
        protected CategoryRegistry $categories,
        protected ExclusionsFactory $exclusionsFactory,
        protected StatsProvider $stats,
        protected RateLimiter $rateLimiter,
        protected LeaderboardSerializer $serializer
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        if (!$actor->hasPermission('huseyinfiliz-leaderboard.viewLeaderboard')) {
            throw new PermissionDeniedException();
        }

        // Aggregates are expensive; cap abusive clients before touching the DB.
        // Guests are limited per client IP, logged-in users per account.
        $this->rateLimiter->assertAllowed($actor, $request->getAttribute('ipAddress'));

        $params = $request->getQueryParams();
        $filter = Arr::get($params, 'filter', []);
        $filter = is_array($filter) ? $filter : [];

        $categoryKey = $this->categoryKey(Arr::get($filter, 'category', 'points'));
        $periodStart = $this->periodStart(Arr::get($filter, 'period', 'all'));
        $section = (string) Arr::get($filter, 'section', '');

        [$offset, $linkOffset, $limit, $withLinks] = $this->window($section, $params);

        $exclusions = $this->exclusionsFactory->forRequest();

        $result = $this->ranking->get($categoryKey, $periodStart, $offset, $limit, $exclusions);

        $stats = $this->statsFor($categoryKey, $result['entries'], $periodStart, $exclusions);

        $body = $this->serializer->serialize(
            $result['entries'],
            $stats,
            $result['total'],
            $linkOffset,
            $limit,
            $request,
            $withLinks
        );

        return new JsonResponse($body);
    }

    /**
     * Secondary stats for the current page's users, per the category's
     * definition.
     */
    protected function statsFor(string $categoryKey, array $entries, ?Carbon $periodStart, Exclusions $exclusions): array
    {
        $definition = $this->categories->get($categoryKey);

        if ($definition === null || empty($entries)) {
            return [];
        }

        $userIds = array_map(fn ($entry) => $entry->id, $entries);

        return $this->stats->forUsers($definition->statKeys(), $userIds, $periodStart, $definition, $exclusions);
    }

    protected function categoryKey(mixed $requested): string
    {
        $key = is_string($requested) ? $requested : '';

        return $this->categories->has($key) ? $key : 'points';
    }

    /**
     * Whitelisted periods only; unknown values degrade to 'all' rather than
     * erroring, so stale bookmarks never break.
     */
    protected function periodStart(mixed $requested): ?Carbon
    {
        $period = is_string($requested) ? $requested : 'all';

        if (!in_array($period, self::PERIODS, true) || $period === 'all') {
            return null;
        }

        $now = Carbon::now();

        return match ($period) {
            'daily' => $now->copy()->startOfDay(),
            'weekly' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'monthly' => $now->copy()->startOfMonth(),
            'quarterly' => $now->copy()->firstOfQuarter(),
            'yearly' => $now->copy()->startOfYear(),
            default => null,
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: bool}  [queryOffset, linkOffset, limit, withLinks]
     */
    protected function window(string $section, array $params): array
    {
        if (isset(self::SECTIONS[$section])) {
            [$offset, $limit] = self::SECTIONS[$section];

            return [$offset, $offset, $limit, false];
        }

        if ($section === 'honorable') {
            $raw = $this->offset($params);

            // Honorable mentions page within their own window: the query is
            // anchored at the fixed base, pagination links use the raw offset.
            return [self::HONORABLE_BASE + $raw, $raw, $this->limit($params), true];
        }

        $raw = $this->offset($params);

        return [$raw, $raw, $this->limit($params), true];
    }

    /**
     * Offset is capped just under the board limit: deep paging is a scrape
     * vector, and the clamp must never truncate the `next` link chain — a
     * lower cap would make link pagination re-serve the same page forever
     * (the board is bounded at RankingRepository::BOARD_LIMIT rows anyway).
     */
    protected function offset(array $params): int
    {
        $page = Arr::get($params, 'page', []);
        $page = is_array($page) ? $page : [];

        return min(RankingRepository::BOARD_LIMIT - 1, max(0, (int) Arr::get($page, 'offset', 0)));
    }

    protected function limit(array $params): int
    {
        $page = Arr::get($params, 'page', []);
        $page = is_array($page) ? $page : [];

        return max(1, min((int) Arr::get($page, 'limit', 20) ?: 20, 50));
    }
}
