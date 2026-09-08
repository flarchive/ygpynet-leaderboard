import app from 'flarum/forum/app';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
import { CATEGORIES, DEFAULT_CATEGORY, PERIODS, type Category, type Period } from '../categories';

export { CATEGORIES, PERIODS };
export type { Category, Period };

const STORAGE_KEY = 'ygpynet-leaderboard.selection';

/**
 * Last category/period the user picked, persisted per browser tab so the
 * selection survives SPA back-navigation (e.g. leaderboard → user profile →
 * back) and even full page reloads.
 */
export function loadPersistedSelection(): { category: Category; period: string } {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      const category = CATEGORIES.includes(parsed.category) ? (parsed.category as Category) : DEFAULT_CATEGORY;
      const period = PERIODS.includes(parsed.period) ? (parsed.period as string) : 'all';
      return { category, period };
    }
  } catch {
    // Corrupted or unavailable storage — fall through to defaults.
  }
  return { category: DEFAULT_CATEGORY, period: 'all' };
}

function persistSelection(category: Category, period: string): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ category, period }));
  } catch {
    // Storage full/blocked — selection simply won't persist.
  }
}

export default class LeaderboardState {
  podiumEntries: LeaderboardEntry[] = [];
  contenderEntries: LeaderboardEntry[] = [];
  honorableEntries: LeaderboardEntry[] = [];

  podiumLoading: boolean = false;
  contendersLoading: boolean = false;
  honorableLoading: boolean = false;
  loadingMore: boolean = false;

  category: Category = DEFAULT_CATEGORY;
  period: string = 'all';
  honorableOffset: number = 0;
  hasMore: boolean = false;

  /**
   * Identity of the selection the in-flight requests were launched for.
   * Responses belonging to an older selection are discarded, so a slow
   * request can never overwrite a newer one when the user switches
   * category/period quickly.
   */
  private currentQueryKey: string = '';

  private queryKey(): string {
    return `${this.period}|${this.category}`;
  }

  async load(period: string, category: Category = this.category) {
    this.period = period;
    this.category = category;
    persistSelection(this.category, this.period);
    this.honorableOffset = 0;
    this.podiumEntries = [];
    this.contenderEntries = [];
    this.honorableEntries = [];
    this.podiumLoading = true;
    this.contendersLoading = true;
    this.honorableLoading = true;
    this.hasMore = false;

    const key = this.queryKey();
    this.currentQueryKey = key;
    m.redraw();

    await Promise.all([this.fetchPodium(key), this.fetchContenders(key), this.fetchHonorable(false, key)]);
  }

  async loadMore() {
    if (!this.hasMore || this.loadingMore) return;

    this.loadingMore = true;
    m.redraw();

    await this.fetchHonorable(true, this.currentQueryKey);
  }

  private async fetchPodium(key: string) {
    try {
      const results = await app.store.find<LeaderboardEntry>('leaderboard-entries', {
        include: 'user',
        filter: { period: this.period, category: this.category, section: 'podium' },
      } as any);

      if (this.currentQueryKey !== key) return;
      this.podiumEntries = Array.isArray(results) ? results : [results];
    } catch (e) {
      // Error handled by Flarum
    } finally {
      if (this.currentQueryKey === key) {
        this.podiumLoading = false;
        m.redraw();
      }
    }
  }

  private async fetchContenders(key: string) {
    try {
      const results = await app.store.find<LeaderboardEntry>('leaderboard-entries', {
        include: 'user',
        filter: { period: this.period, category: this.category, section: 'contenders' },
      } as any);

      if (this.currentQueryKey !== key) return;
      this.contenderEntries = Array.isArray(results) ? results : [results];
    } catch (e) {
      // Error handled by Flarum
    } finally {
      if (this.currentQueryKey === key) {
        this.contendersLoading = false;
        m.redraw();
      }
    }
  }

  private async fetchHonorable(append: boolean, key: string) {
    try {
      const results = await app.store.find<LeaderboardEntry>('leaderboard-entries', {
        include: 'user',
        filter: { period: this.period, category: this.category, section: 'honorable' },
        page: { offset: this.honorableOffset, limit: 20 },
      } as any);

      if (this.currentQueryKey !== key) return;

      const payload = (results as any).payload;
      const newEntries: LeaderboardEntry[] = Array.isArray(results) ? results : [results];

      if (append) {
        this.honorableEntries = [...this.honorableEntries, ...newEntries];
      } else {
        this.honorableEntries = newEntries;
      }

      this.honorableOffset += newEntries.length;

      // JSON:API standard: if there's a "next" link, there are more results
      this.hasMore = !!payload?.links?.next;
    } catch (e) {
      // Error handled by Flarum
    } finally {
      if (this.currentQueryKey === key) {
        this.honorableLoading = false;
        this.loadingMore = false;
        m.redraw();
      }
    }
  }

  get isFullyLoaded(): boolean {
    return !this.podiumLoading && !this.contendersLoading && !this.honorableLoading;
  }

  get isEmpty(): boolean {
    return this.isFullyLoaded && this.podiumEntries.length === 0 && this.contenderEntries.length === 0 && this.honorableEntries.length === 0;
  }
}
