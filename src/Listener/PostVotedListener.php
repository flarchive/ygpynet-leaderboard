<?php

namespace ygpynet\Leaderboard\Listener;

use FoF\Gamification\Events\PostWasVoted;
use ygpynet\Leaderboard\Service\PointService;

class PostVotedListener
{
    public function __construct(protected PointService $pointService)
    {
    }

    public function handle(PostWasVoted $event): void
    {
        $vote = $event->vote;
        $post = $vote->post;
        $recipient = $post->user;
        $voter = $vote->user;

        if (!$recipient || !$voter) {
            return;
        }

        // Don't award if the voter is the post author
        if ($voter->id === $recipient->id) {
            return;
        }

        if ($this->pointService->isExcludedByGroup($recipient)) {
            return;
        }

        if ($this->pointService->isExcludedByTags($post->discussion)) {
            return;
        }

        // One vote per voter per post: revoke the previous credit from this
        // voter on this post (up- or down-) before applying the new value.
        $this->pointService->revoke($recipient, 'upvote_received', 'post', $post->id);
        $this->pointService->revoke($recipient, 'downvote_received', 'post', $post->id);

        // Cast before strict comparison: upstream Vote has no int cast, so
        // the value's type depends on the PDO driver configuration.
        $value = (int) $vote->value;

        if ($value === 1) {
            $this->pointService->award(
                $recipient,
                $this->pointService->getPointsForReason('upvote_received'),
                'upvote_received',
                'post',
                $post->id,
                $voter->id
            );
        } elseif ($value === -1) {
            $this->pointService->award(
                $recipient,
                $this->pointService->getPointsForReason('downvote_received'),
                'downvote_received',
                'post',
                $post->id,
                $voter->id
            );
        }
        // value === 0 means vote removed — the revokes above already undid it
    }
}
