<?php

namespace ygpynet\Leaderboard\Api;

use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use ygpynet\Leaderboard\Api\Data\LeaderboardEntryData;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JSON:API assembly for the leaderboard endpoint: entry resources, included
 * users (with their category stats) and offset pagination links.
 */
class LeaderboardSerializer
{
    public function __construct(
        protected SlugManager $slugManager,
        protected UrlGenerator $url
    ) {
    }

    /**
     * @param LeaderboardEntryData[] $entries
     * @param array<int, array<string, int>> $stats  userId => statKey => value
     */
    public function serialize(array $entries, array $stats, ?int $total, int $offset, int $limit, ServerRequestInterface $request, bool $withLinks): array
    {
        $data = [];
        $included = [];
        $seenUsers = [];

        foreach ($entries as $entry) {
            $entryData = [
                'type' => 'leaderboard-entries',
                'id' => (string) $entry->id,
                'attributes' => [
                    'score' => $entry->score,
                    'rank' => $entry->rank,
                ],
            ];

            if ($entry->user) {
                $entryData['relationships'] = [
                    'user' => [
                        'data' => ['type' => 'users', 'id' => (string) $entry->user->id],
                    ],
                ];

                if (!isset($seenUsers[$entry->user->id])) {
                    $included[] = $this->user($entry->user, $stats[$entry->user->id] ?? []);
                    $seenUsers[$entry->user->id] = true;
                }
            }

            $data[] = $entryData;
        }

        $response = ['data' => $data];

        if (!empty($included)) {
            $response['included'] = $included;
        }

        if ($withLinks && $total !== null) {
            $hasMore = self::hasMore($total, $offset, count($entries));
            $links = $this->paginationLinks($request, $offset, $limit, $hasMore);

            if (!empty($links)) {
                $response['links'] = $links;
            }
        }

        return $response;
    }

    /**
     * Whether another page follows. An empty page must never advertise more
     * results — otherwise a client past the reachable board end (clamped
     * offset) would fetch the same empty window forever.
     */
    public static function hasMore(int $total, int $offset, int $count): bool
    {
        return $count > 0 && $total > $offset + $count;
    }

    /**
     * @param array<string, int> $stats
     */
    protected function user(User $user, array $stats): array
    {
        $attributes = [
            'username' => $user->username,
            'displayName' => $user->display_name,
            'slug' => $this->slugManager->forResource(User::class)->toSlug($user),
        ];

        if ($user->avatar_url) {
            $attributes['avatarUrl'] = $user->avatar_url;
        }

        foreach ($stats as $key => $value) {
            $attributes[$key] = (int) $value;
        }

        return [
            'type' => 'users',
            'id' => (string) $user->id,
            'attributes' => $attributes,
        ];
    }

    protected function paginationLinks(ServerRequestInterface $request, int $offset, int $limit, bool $hasMore): array
    {
        $links = [];
        $baseUrl = $this->url->to('api')->route('ygpynet-leaderboard.api.index');
        $queryParams = $request->getQueryParams();

        if ($offset > 0) {
            $firstParams = $queryParams;
            $firstParams['page'] = ['offset' => 0];
            $links['first'] = $baseUrl.'?'.http_build_query($firstParams, '', '&', PHP_QUERY_RFC3986);

            $prevParams = $queryParams;
            $prevParams['page'] = ['offset' => max(0, $offset - $limit)];
            $links['prev'] = $baseUrl.'?'.http_build_query($prevParams, '', '&', PHP_QUERY_RFC3986);
        }

        if ($hasMore) {
            $nextParams = $queryParams;
            $nextParams['page'] = ['offset' => $offset + $limit];
            $links['next'] = $baseUrl.'?'.http_build_query($nextParams, '', '&', PHP_QUERY_RFC3986);
        }

        return $links;
    }
}
