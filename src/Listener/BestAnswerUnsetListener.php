<?php

namespace ygpynet\Leaderboard\Listener;

use FoF\BestAnswer\Events\BestAnswerUnset;
use ygpynet\Leaderboard\Service\PointService;

class BestAnswerUnsetListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(BestAnswerUnset $event): void
    {
        $answerAuthor = $event->post->user;

        if (!$answerAuthor) {
            return;
        }

        $this->pointService->revoke($answerAuthor, 'best_answer', 'discussion', $event->discussion->id);
    }
}
