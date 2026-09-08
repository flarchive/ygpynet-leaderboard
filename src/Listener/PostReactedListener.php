<?php

namespace ygpynet\Leaderboard\Listener;

use FoF\Reactions\Event\PostWasReacted;
use ygpynet\Leaderboard\Service\PointService;

class PostReactedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(PostWasReacted $event): void
    {
        $postAuthor = $event->post->user;
        $reactor = $event->user;

        if (!$postAuthor) {
            return;
        }

        if ($this->pointService->isExcludedByTags($event->post->discussion)) {
            return;
        }

        // One reaction per reactor per post: point-system's revert() undoes the
        // latest matching credit, so revoke-then-award keeps the net total at
        // exactly one award when the reaction is changed.
        if ($reactor->id !== $postAuthor->id && !$this->pointService->isExcludedByGroup($postAuthor)) {
            $this->pointService->revoke($postAuthor, 'reaction_received', 'post', $event->post->id);
            $this->pointService->award(
                $postAuthor,
                $this->pointService->getPointsForReason('reaction_received'),
                'reaction_received',
                'post',
                $event->post->id,
                $reactor->id
            );
        }

        if (!$this->pointService->isExcludedByGroup($reactor)) {
            $this->pointService->revoke($reactor, 'reaction_given', 'post', $event->post->id);
            $this->pointService->award(
                $reactor,
                $this->pointService->getPointsForReason('reaction_given'),
                'reaction_given',
                'post',
                $event->post->id,
                $reactor->id
            );
        }
    }
}
