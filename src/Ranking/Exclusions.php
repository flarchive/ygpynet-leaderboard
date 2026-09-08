<?php

namespace ygpynet\Leaderboard\Ranking;

use Flarum\Extension\ExtensionManager;
use ygpynet\Leaderboard\Support\LeaderboardSettings;
use Illuminate\Database\Query\Builder;

/**
 * Immutable set of ranking exclusions (excluded user groups and discussion
 * tags), parsed and sanitised once per request. The parsed ids also feed the
 * cache key, so changing the settings immediately produces fresh rankings.
 */
class Exclusions
{
    /**
     * @param int[] $groupIds
     * @param int[] $tagIds
     */
    protected function __construct(
        protected array $groupIds,
        protected array $tagIds,
        protected bool $tagsEnabled
    ) {
    }

    public static function fromSettings(LeaderboardSettings $settings, ExtensionManager $extensions): self
    {
        return new self(
            $settings->excludedGroupIds(),
            $settings->excludedTagIds(),
            $extensions->isEnabled('flarum-tags')
        );
    }

    public function hash(): string
    {
        return md5(implode(',', $this->groupIds).'|'.implode(',', $this->tagIds).'|'.(int) $this->tagsEnabled);
    }

    public function applyGroupExclusion(Builder $query, string $userColumn): void
    {
        if (empty($this->groupIds)) {
            return;
        }

        $groupIds = $this->groupIds;

        $query->whereNotIn($userColumn, function ($sub) use ($groupIds) {
            $sub->select('user_id')
                ->from('group_user')
                ->whereIn('group_id', $groupIds);
        });
    }

    public function applyTagExclusion(Builder $query, ?string $discussionColumn): void
    {
        if ($discussionColumn === null || empty($this->tagIds) || !$this->tagsEnabled) {
            return;
        }

        $tagIds = $this->tagIds;

        $query->whereNotIn($discussionColumn, function ($sub) use ($tagIds) {
            $sub->select('discussion_id')
                ->from('discussion_tag')
                ->whereIn('tag_id', $tagIds);
        });
    }
}
