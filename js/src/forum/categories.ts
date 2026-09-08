import app from 'flarum/forum/app';

/**
 * Shared leaderboard category metadata.
 *
 * Adding a category: implement a CategoryDefinition on the backend and
 * register it in the CategoryRegistry, then append its key + icon here and
 * add its locale label. The API drives everything else.
 */

export const CATEGORIES = ['points', 'likes_received', 'likes_given', 'best_answers', 'checkin_streak', 'posts', 'discussions'] as const;

export type Category = (typeof CATEGORIES)[number];

export const CATEGORY_ICONS: Record<Category, string> = {
  points: 'fas fa-coins',
  likes_received: 'fas fa-heart',
  likes_given: 'fas fa-hand-holding-heart',
  best_answers: 'fas fa-check-circle',
  checkin_streak: 'fas fa-fire',
  posts: 'fas fa-comment-dots',
  discussions: 'fas fa-file-alt',
};

export const DEFAULT_CATEGORY: Category = 'points';

export const PERIODS = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'all'] as const;

export type Period = (typeof PERIODS)[number];

/**
 * Unit shown after the score, per category. The points board reuses the
 * admin-configured currency name from point-system; the counting boards use
 * translated unit words (forum.unit.*).
 */
export const categoryUnit = (category: string): string | null => {
  if (category === 'points') {
    return (app.forum.attribute('pointSystem.points_short') as string) || 'pts';
  }

  return app.translator.trans(`ygpynet-leaderboard.forum.unit.${category}`) as string;
};

/** Secondary per-user stats shown on podium/contender cards. */
export const STAT_DEFS: Record<string, { icon: string; labelKey: string }> = {
  topStreak: { icon: 'fas fa-crown', labelKey: 'ygpynet-leaderboard.forum.podium.stat_top_streak' },
};

export const categoryLabel = (category: string): string =>
  app.translator.trans(`ygpynet-leaderboard.forum.category.${category}`) as string;
