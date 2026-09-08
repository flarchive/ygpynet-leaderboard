<?php

namespace ygpynet\Leaderboard\Ranking;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Contract for one leaderboard category.
 *
 * A category is a self-contained ranking source: it knows how to build a
 * (user_id, score) query for a given period, and describes the columns the
 * generic pipeline needs for group/tag exclusions. Register implementations
 * in the CategoryRegistry — adding a new board never touches the controller.
 */
interface CategoryDefinition
{
    /**
     * Unique, stable key used in the API filter[category] and on the frontend.
     */
    public function key(): string;

    /**
     * Build the (user_id, score) query for the given period.
     *
     * $periodStart === null means "all time". Return null when the data source
     * is not available (missing extension, missing columns) — the controller
     * then serves an empty board instead of a 500.
     */
    public function scoreQuery(ConnectionInterface $db, ?Carbon $periodStart): ?Builder;

    /**
     * Qualified column of the ranked user inside the query built above
     * (e.g. "p.user_id"), used to apply group exclusions. For single-table
     * queries that select the PK directly, the unqualified physical column
     * name also works (e.g. "user_id" when selecting user_id as user_id).
     * The column must be resolvable in a WHERE clause — MySQL does not
     * resolve SELECT aliases there.
     */
    public function userColumn(): string;

    /**
     * Qualified column of the discussion each row belongs to (e.g.
     * "p.discussion_id"), used to apply tag exclusions.
     * Return null when tag exclusions do not apply to this category.
     */
    public function discussionColumn(): ?string;

    /**
     * Secondary stats attribute names shown on the podium cards
     * (resolved through the StatsProvider).
     *
     * @return string[]
     */
    public function statKeys(): array;

    /**
     * Build a per-day (user_id, date, score) query used to compute the
     * "consecutive days at #1" stat. $fromDate is inclusive; `date` must be a
     * plain Y-m-d value, `score` the metric accumulated that day. Return null
     * when the source is unavailable. The query must keep the same user
     * column shape as scoreQuery() so exclusions apply unchanged.
     */
    public function dailyScoreQuery(\Illuminate\Database\ConnectionInterface $db, \Carbon\Carbon $fromDate): ?\Illuminate\Database\Query\Builder;
}
