<?php

namespace HuseyinFiliz\Leaderboard\Ranking;

use Flarum\Extension\ExtensionManager;
use HuseyinFiliz\Leaderboard\Support\LeaderboardSettings;

/**
 * Builds the per-request {@see Exclusions} set. A factory rather than a
 * singleton: exclusions depend on admin settings that can change at runtime,
 * and the parsed ids participate in the ranking cache key.
 */
class ExclusionsFactory
{
    public function __construct(
        protected LeaderboardSettings $settings,
        protected ExtensionManager $extensions
    ) {
    }

    public function forRequest(): Exclusions
    {
        return Exclusions::fromSettings($this->settings, $this->extensions);
    }
}
