<?php

namespace ygpynet\Leaderboard\Service;

use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use ygpynet\Leaderboard\Support\LeaderboardSettings;
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
        protected PointsRepositoryInterface $points
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
