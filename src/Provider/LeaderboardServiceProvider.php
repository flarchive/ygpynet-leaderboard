<?php

namespace ygpynet\Leaderboard\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use ygpynet\Leaderboard\Ranking\CategoryRegistry;
use ygpynet\Leaderboard\Ranking\ExclusionsFactory;
use ygpynet\Leaderboard\Ranking\RateLimiter;
use ygpynet\Leaderboard\Ranking\RankingRepository;
use ygpynet\Leaderboard\Ranking\StatsProvider;
use ygpynet\Leaderboard\Ranking\Support\SchemaCache;
use ygpynet\Leaderboard\Service\PointService;
use ygpynet\Leaderboard\Support\LeaderboardSettings;
use ygpynet\Leaderboard\Api\LeaderboardSerializer;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Ramon\PointSystem\Support\PointReason;

class LeaderboardServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        // Settings facade: single source for keys + defaults across the
        // extension (extend.php reads the same constants statically).
        $this->container->singleton(LeaderboardSettings::class);

        // Service layer (point bridging into point-system).
        $this->container->singleton(PointService::class);

        // Ranking pipeline. CategoryRegistry is the extension point: other
        // extensions may add boards via $container->make(CategoryRegistry::class)->add(...).
        $this->container->singleton(CategoryRegistry::class);
        $this->container->singleton(SchemaCache::class);
        $this->container->singleton(StatsProvider::class);
        $this->container->singleton(RankingRepository::class);
        $this->container->singleton(ExclusionsFactory::class);
        $this->container->singleton(RateLimiter::class);
        $this->container->singleton(LeaderboardSerializer::class);
    }

    public function boot()
    {
        // Register the leaderboard's point sources in point-system's reason
        // registry so its ledger UI can label the transactions. The binding
        // only exists when ygpynet/point-system is enabled.
        if ($this->container->bound(PointReason::class)) {
            $reasons = $this->container->make(PointReason::class);

            $reasons->register('reaction.received', 'reaction_received', PointReason::CATEGORY_EARN, true);
            $reasons->register('reaction.given', 'reaction_given', PointReason::CATEGORY_EARN, true);
            $reasons->register('best.answer', 'best_answer', PointReason::CATEGORY_EARN, true);
            $reasons->register('badge.earned', 'badge_earned', PointReason::CATEGORY_EARN, false);
            $reasons->register('upvote.received', 'upvote_received', PointReason::CATEGORY_EARN, true);
            $reasons->register('downvote.received', 'downvote_received', PointReason::CATEGORY_EARN, true);
        }
    }
}
