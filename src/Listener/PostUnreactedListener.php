<?php

namespace ygpynet\Leaderboard\Listener;

use FoF\Reactions\Event\PostWasUnreacted;
use ygpynet\Leaderboard\Service\PointService;

class PostUnreactedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(PostWasUnreacted $event): void
    {
        $postAuthor = $event->post->user;
        $reactor = $event->user;

        if ($postAuthor) {
            $this->pointService->revoke($postAuthor, 'reaction_received', 'post', $event->post->id);
        }

        $this->pointService->revoke($reactor, 'reaction_given', 'post', $event->post->id);
    }
}
