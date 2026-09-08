<?php

namespace HuseyinFiliz\Leaderboard\Ranking;

use Flarum\Post\Exception\FloodingException;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Lightweight per-identity rate limit for the leaderboard endpoint.
 *
 * Authenticated users are bucketed by user id; guests are bucketed by
 * client IP (the same REMOTE_ADDR-derived attribute Flarum core itself
 * trusts). A single shared bucket across all guests would let one visitor
 * exhaust the anonymous quota for everyone.
 *
 * Fixed-minute buckets: the bucket key changes every 60s, each identity
 * gets its policy's `maxRequests` hits per bucket. Fail-open — if the cache
 * errors out we serve the request rather than take the page down.
 */
class RateLimiter
{
    public function __construct(
        protected Cache $cache,
        protected int $maxAuthenticated = 30,
        protected int $maxGuest = 10
    ) {
    }

    /**
     * @param string|null $ipAddress client IP from the request's ipAddress
     *                               attribute (used only for guest buckets)
     * @throws FloodingException when the identity exceeds its window
     */
    public function assertAllowed(User $actor, ?string $ipAddress = null): void
    {
        try {
            if ($this->cache->get('lb_rate_bypass') === true) {
                return;
            }
        } catch (\Throwable) {
            // Cache unavailable — fail open.
            return;
        }

        if ($actor->isGuest()) {
            $this->hit('ip', $ipAddress !== null && $ipAddress !== '' ? $ipAddress : 'unknown', $this->maxGuest);
        } else {
            $this->hit('user', (string) $actor->id, $this->maxAuthenticated);
        }
    }

    protected function hit(string $scope, string $identity, int $maxRequests): void
    {
        try {
            $bucket = (int) floor(time() / 60);
            $key = "lb_rate_{$scope}_{$identity}_{$bucket}";

            $hits = (int) $this->cache->get($key, 0) + 1;

            // A bit past the bucket boundary is enough: once a new bucket
            // starts, the old key is never read again.
            $this->cache->put($key, $hits, 90);

            if ($hits > $maxRequests) {
                throw new FloodingException;
            }
        } catch (FloodingException $e) {
            throw $e;
        } catch (\Throwable) {
            // Cache unavailable — fail open.
        }
    }
}
