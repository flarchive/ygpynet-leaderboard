<?php

namespace HuseyinFiliz\Leaderboard\Service;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use HuseyinFiliz\Leaderboard\Support\LeaderboardSettings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Ramon\PointSystem\Model\PointTransaction;
use Ramon\PointSystem\Points\PointsRepositoryInterface;

/**
 * Bridges the leaderboard's point sources into ygpynet/point-system's ledger.
 * Rankings are no longer computed from local tables — the leaderboard reads
 * lifetime points and transactions directly from point-system.
 */
class PointService
{
    /**
     * Leaderboard reason => point-system ledger reason code.
     */
    protected array $ledgerReasons = [
        'daily_login' => 'daily.login',
        'reaction_received' => 'reaction.received',
        'reaction_given' => 'reaction.given',
        'best_answer' => 'best.answer',
        'badge_earned' => 'badge.earned',
        'upvote_received' => 'upvote.received',
        'downvote_received' => 'downvote.received',
    ];

    public function __construct(
        protected LeaderboardSettings $settings,
        protected ExtensionManager $extensions,
        protected PointsRepositoryInterface $points,
        protected Cache $cache
    ) {
    }

    public function award(User $user, int $points, string $reason, ?string $subjectType = null, ?int $subjectId = null, ?int $actorId = null): void
    {
        if ($points === 0 || !$user) {
            return;
        }

        $this->points->award(
            $user,
            $points,
            $this->ledgerReason($reason),
            $subjectType,
            $subjectId,
            $actorId !== null ? ['actor_id' => $actorId] : null
        );
    }

    public function revoke(User $user, string $reason, ?string $subjectType = null, ?int $subjectId = null): void
    {
        if (!$user || $subjectType === null || $subjectId === null) {
            return;
        }

        $this->points->revert(
            $user,
            $this->ledgerReason($reason),
            $subjectType,
            $subjectId
        );
    }

    protected function ledgerReason(string $reason): string
    {
        return $this->ledgerReasons[$reason] ?? 'leaderboard.'.$reason;
    }

    public function checkDailyLogin(User $user): void
    {
        $today = Carbon::today()->toDateString();
        $cacheKey = "leaderboard_daily_login:{$user->id}:{$today}";

        if ($this->cache->has($cacheKey)) {
            return;
        }

        // DB fallback: if cache was cleared, check if already awarded today
        if ($this->hasLedgerEntrySince($user, 'daily.login', Carbon::today())) {
            $this->cache->put($cacheKey, true, 86400);

            return;
        }

        $points = $this->settings->pointsFor('daily_login');

        if ($points !== 0) {
            $this->award($user, $points, 'daily_login');
        }

        $this->cache->put($cacheKey, true, 86400);
    }

    protected function hasLedgerEntrySince(User $user, string $ledgerReason, Carbon $since): bool
    {
        return PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('reason', $ledgerReason)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    public function getPointsForReason(string $reason): int
    {
        return $this->settings->pointsFor($reason);
    }

    public function isExcludedByTags(Discussion $discussion): bool
    {
        if (!$this->extensions->isEnabled('flarum-tags')) {
            return false;
        }

        $excludedTagIds = $this->settings->excludedTagIds();

        if (empty($excludedTagIds)) {
            return false;
        }

        return $discussion->tags()->whereIn('tags.id', $excludedTagIds)->exists();
    }

    public function isExcludedByGroup(User $user): bool
    {
        $excludedGroupIds = $this->settings->excludedGroupIds();

        if (empty($excludedGroupIds)) {
            return false;
        }

        return $user->groups()->whereIn('groups.id', $excludedGroupIds)->exists();
    }
}
