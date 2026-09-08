<?php

namespace HuseyinFiliz\Leaderboard\Ranking\Category;

class LikesGiven extends LikesBase
{
    public function __construct()
    {
        parent::__construct('pl');
    }

    public function key(): string
    {
        return 'likes_given';
    }

    public function statKeys(): array
    {
        return ['likesReceivedCount', 'repliesCount'];
    }
}
