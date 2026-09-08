<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use Flarum\User\Event\LoggedIn;
use HuseyinFiliz\Leaderboard\Service\PointService;

class DailyLoginListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(LoggedIn $event): void
    {
        if ($this->pointService->isExcludedByGroup($event->user)) {
            return;
        }

        $this->pointService->checkDailyLogin($event->user);
    }
}
