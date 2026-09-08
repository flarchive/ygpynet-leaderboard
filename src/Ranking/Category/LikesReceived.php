<?php

namespace HuseyinFiliz\Leaderboard\Ranking\Category;

class LikesReceived extends LikesBase
{
    public function __construct()
    {
        parent::__construct('p');
    }

    public function key(): string
    {
        return 'likes_received';
    }

    public function statKeys(): array
    {
        return ['likesGivenCount', 'repliesCount'];
    }
}
