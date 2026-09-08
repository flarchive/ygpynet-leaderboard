<?php

namespace HuseyinFiliz\Leaderboard\Listener;

use FoF\BestAnswer\Events\BestAnswerSet;
use HuseyinFiliz\Leaderboard\Service\PointService;

class BestAnswerSetListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(BestAnswerSet $event): void
    {
        $answerAuthor = $event->post->user;

        if (!$answerAuthor) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($answerAuthor)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->discussion)) {
            return;
        }

        $this->pointService->award(
            $answerAuthor,
            $this->pointService->getPointsForReason('best_answer'),
            'best_answer',
            'discussion',
            $event->discussion->id
        );
    }
}
