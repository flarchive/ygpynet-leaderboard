<?php

namespace ygpynet\Leaderboard\Support;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Single source of truth for the extension's settings keys and default
 * values. extend.php registers defaults from {@see POINT_DEFAULTS} and every
 * backend reader parses through this class — a key or a default value can
 * no longer drift between the wiring file and the services.
 */
final class LeaderboardSettings
{
    public const PREFIX = 'ygpynet-leaderboard.';

    public const NAME_KEY = self::PREFIX.'leaderboard_name';
    public const DEFAULT_NAME = '排行榜';

    public const EXCLUDED_GROUPS_KEY = self::PREFIX.'excluded_groups';
    public const EXCLUDED_TAGS_KEY = self::PREFIX.'excluded_tags';

    /**
     * Earning reason => default points. The same map feeds the Extend\Settings
     * defaults in extend.php and acts as the fallback for unset/invalid stored
     * values, so both sides stay identical by construction.
     *
     * Core earning (discussions, posts, likes, check-in) is owned by
     * ygpynet/point-system and is intentionally NOT listed here.
     *
     * @var array<string, int>
     */
    public const POINT_DEFAULTS = [
        'reaction_received' => 1,
        'reaction_received' => 1,
        'reaction_given' => 0,
        'best_answer' => 2,
        'badge_earned' => 3,
        'upvote_received' => 1,
        'downvote_received' => -1,
    ];

    /**
     * Setting key for an earning reason's point value.
     */
    public static function pointKey(string $reason): string
    {
        return self::PREFIX.'points_'.$reason;
    }

    /**
     * Fully-qualified setting key for any suffix (e.g. 'excluded_tags').
     */
    public static function key(string $name): string
    {
        return self::PREFIX.$name;
    }

    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Points configured for an earning reason, falling back to the shared
     * default when the stored value is unset.
     */
    public function pointsFor(string $reason): int
    {
        return (int) $this->settings->get(self::pointKey($reason), self::POINT_DEFAULTS[$reason] ?? 0);
    }

    /**
     * @return int[] group ids hidden from rankings and earning
     */
    public function excludedGroupIds(): array
    {
        return $this->ids(self::EXCLUDED_GROUPS_KEY);
    }

    /**
     * @return int[] tag ids whose discussions never earn points
     */
    public function excludedTagIds(): array
    {
        return $this->ids(self::EXCLUDED_TAGS_KEY);
    }

    /**
     * The settings store keeps raw JSON that an admin (or a corrupted entry)
     * may have mangled; keep only positive integers so the arrays are always
     * safe to feed into whereIn(). Sorted + deduped: the parsed ids feed the
     * ranking cache key via Exclusions::hash(), and admins re-select the same
     * groups in a different order constantly — without normalisation, equal
     * exclusion sets would fragment the cache.
     *
     * @return int[]
     */
    protected function ids(string $key): array
    {
        $json = $this->settings->get($key);

        if (empty($json) || !is_string($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', $decoded), fn ($id) => $id > 0));

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
