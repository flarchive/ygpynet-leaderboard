<?php

namespace ygpynet\Leaderboard\Api;

use Flarum\Foundation\KnownError;

/**
 * Raised when a client exceeds the leaderboard rate limit; mapped to 429 by
 * Flarum's error registry ('too_many_requests').
 */
class RateLimitedException extends \Exception implements KnownError
{
    public function getType(): string
    {
        return 'too_many_requests';
    }
}
