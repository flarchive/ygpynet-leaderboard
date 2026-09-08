<?php

namespace HuseyinFiliz\Leaderboard\Ranking\Category;

use Carbon\Carbon;
use HuseyinFiliz\Leaderboard\Ranking\Support\SchemaCache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Best answers board: post author of every discussion's best answer.
 * Requires fof/best-answer's columns; serves an empty board when absent.
 */
class BestAnswers extends Category
{
    public function __construct(protected SchemaCache $schema)
    {
    }

    public function key(): string
    {
        return 'best_answers';
    }

    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder
    {
        if (!$this->schema->hasColumn('discussions', 'best_answer_post_id')) {
            return null;
        }

        $query = $db->table('discussions as d')
            ->join('posts as p', 'p.id', '=', 'd.best_answer_post_id')
            ->whereNull('p.hidden_at')
            ->selectRaw($this->col($db, 'p', 'user_id').' as user_id, COUNT(*) as score')
            ->groupBy('p.user_id');

        if ($periodStart !== null) {
            $query->whereRaw(
                'COALESCE('.$this->col($db, 'd', 'best_answer_set_at').', '.$this->col($db, 'd', 'created_at').') >= ?',
                [$periodStart]
            );
        }

        return $query;
    }

    public function userColumn(): string
    {
        return 'p.user_id';
    }

    public function discussionColumn(): ?string
    {
        return 'd.id';
    }

    public function statKeys(): array
    {
        return ['topStreak'];
    }

    public function dailyScoreQuery(ConnectionInterface $db, Carbon $fromDate): ?\Illuminate\Database\Query\Builder
    {
        if (!$this->schema->hasColumn('discussions', 'best_answer_post_id')) {
            return null;
        }

        return $db->table('discussions as d')
            ->join('posts as p', 'p.id', '=', 'd.best_answer_post_id')
            ->whereNull('p.hidden_at')
            ->whereRaw('COALESCE('.$this->col($db, 'd', 'best_answer_set_at').', '.$this->col($db, 'd', 'created_at').') >= ?', [$fromDate->copy()->startOfDay()])
            ->selectRaw($this->col($db, 'p', 'user_id').' as user_id, DATE(COALESCE('.$this->col($db, 'd', 'best_answer_set_at').', '.$this->col($db, 'd', 'created_at').')) as date, COUNT(*) as score')
            ->groupBy('p.user_id', 'date');
    }
}
