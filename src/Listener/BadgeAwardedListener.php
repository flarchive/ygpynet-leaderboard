<?php

namespace ygpynet\Leaderboard\Listener;

use FoF\Badges\Event\BadgeAwarded;
use ygpynet\Leaderboard\Service\PointService;

class BadgeAwardedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(BadgeAwarded $event): void
    {
        if ($this->pointService->isExcludedByGroup($event->user)) {
            return;
        }

        $this->pointService->award(
            $event->user,
            $this->pointService->getPointsForReason('badge_earned'),
            'badge_earned',
            'badge',
            $event->badge->id ?? null
        );
    }
}
