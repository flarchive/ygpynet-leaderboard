import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import type { IPageAttrs } from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import SelectDropdown from 'flarum/common/components/SelectDropdown';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import PodiumSection from './PodiumSection';
import TopContendersGrid from './TopContendersGrid';
import LeaderboardList from './LeaderboardList';
import LeaderboardState, { CATEGORIES, PERIODS, loadPersistedSelection, type Category } from '../utils/LeaderboardState';
import { CATEGORY_ICONS, categoryLabel, DEFAULT_CATEGORY } from '../categories';

export default class LeaderboardPage extends Page<IPageAttrs, LeaderboardState> {
  oninit(vnode: Mithril.Vnode<IPageAttrs, this>) {
    super.oninit(vnode);

    if (!app.forum.attribute('canViewLeaderboard')) return;

    // Restore the user's last selection (survives back-navigation & reloads).
    const { category, period } = loadPersistedSelection();

    this.state = new LeaderboardState();
    this.state.category = category;
    this.state.load(period, category);
  }

  oncreate(vnode: Mithril.VnodeDOM<IPageAttrs, this>) {
    super.oncreate(vnode);

    const leaderboardName =
      (app.forum.attribute<string>('ygpynet-leaderboard.leaderboard_name') as string) ||
      extractText(app.translator.trans('ygpynet-leaderboard.forum.nav.leaderboard'));
    app.setTitle(leaderboardName);

    // Register this page in Flarum's route history stack. Without this, the
    // stack when navigating IndexPage → Leaderboard → UserProfile only holds
    // [index, user], so the profile's back button skipped the leaderboard and
    // returned straight to the home page.
    app.history.push('ygpynet-leaderboard.index', leaderboardName);
  }

  view() {
    return (
      <PageStructure className="IndexPage LeaderboardPage" hero={this.hero.bind(this)} sidebar={this.sidebar.bind(this)}>
        {this.contentView()}
      </PageStructure>
    );
  }

  hero(): Mithril.Children {
    const leaderboardName = app.forum.attribute('ygpynet-leaderboard.leaderboard_name') || '排行榜';

    return (
      <header className="Hero LeaderboardHero">
        <div className="container">
          <div className="containerNarrow">
            <h1 className="Hero-title">
              <i aria-hidden="true" className="icon fas fa-trophy" /> {leaderboardName}
            </h1>
          </div>
        </div>
      </header>
    );
  }

  sidebar(): Mithril.Children {
    return <IndexSidebar />;
  }

  contentView(): Mithril.Children {
    if (!app.forum.attribute('canViewLeaderboard')) {
      return (
        <div className="LeaderboardPage-denied">
          <p>{app.translator.trans('ygpynet-leaderboard.forum.permission_denied')}</p>
        </div>
      );
    }

    return [
      <div className="LeaderboardPage-filters">
        {this.categoryDropdown()}
        <span className="LeaderboardPage-filtersSpacer" />
        <button className="Button LeaderboardPage-refreshBtn" onclick={() => this.state.load(this.state.period)} disabled={!this.state.isFullyLoaded}>
          <i className="fas fa-sync-alt" />
        </button>
        {PERIODS.map((p) => (
          <button
            key={p}
            className={'Button LeaderboardPage-filterBtn' + (this.state.period === p ? ' active' : '')}
            onclick={() => this.state.load(p)}
            disabled={!this.state.isFullyLoaded}
          >
            {app.translator.trans(`ygpynet-leaderboard.forum.period.${p}`)}
          </button>
        ))}
      </div>,

      this.state.isEmpty ? (
        <div className="LeaderboardPage-empty">
          <p>{app.translator.trans('ygpynet-leaderboard.forum.list.no_results')}</p>
        </div>
      ) : (
        <div className="LeaderboardPage-content">
          {this.podiumView()}
          {this.contendersView()}
          {this.honorableView()}
        </div>
      ),
    ];
  }

  categoryDropdown(): Mithril.Children {
    return (
      <SelectDropdown
        className="LeaderboardPage-categoryDropdown"
        buttonClassName="Button"
        icon={CATEGORY_ICONS[this.state.category]}
        defaultLabel={categoryLabel(this.state.category)}
      >
        {CATEGORIES.map((c) => (
          <Button
            key={c}
            icon={CATEGORY_ICONS[c]}
            active={this.state.category === c}
            disabled={!this.state.isFullyLoaded}
            onclick={() => this.state.load(this.state.period, c)}
          >
            {categoryLabel(c)}
          </Button>
        ))}
      </SelectDropdown>
    );
  }

  podiumView() {
    if (this.state.podiumLoading) {
      return (
        <div className="LeaderboardPodium LeaderboardSkeleton-podium">
          {[1, 2, 3].map((i) => (
            <div className={`LeaderboardPodium-place LeaderboardPodium-place--${i} LeaderboardSkeleton-card`} key={i}>
              <div className="LeaderboardSkeleton-badge LeaderboardSkeleton-pulse" />
              <div className="LeaderboardSkeleton-avatar LeaderboardSkeleton-pulse" />
              <div className="LeaderboardSkeleton-text LeaderboardSkeleton-pulse" />
              <div className="LeaderboardSkeleton-textSm LeaderboardSkeleton-pulse" />
            </div>
          ))}
        </div>
      );
    }

    if (this.state.podiumEntries.length === 0) return null;
    return <PodiumSection entries={this.state.podiumEntries} category={this.state.category} />;
  }

  contendersView() {
    if (this.state.contendersLoading) {
      return (
        <div className="LeaderboardContenders LeaderboardSkeleton-contenders">
          <div className="LeaderboardSkeleton-sectionTitle LeaderboardSkeleton-pulse" />
          <div className="LeaderboardContenders-grid">
            {[1, 2, 3, 4, 5, 6, 7].map((i) => (
              <div className="LeaderboardContenders-card LeaderboardSkeleton-card" key={i}>
                <div className="LeaderboardSkeleton-avatar LeaderboardSkeleton-avatarSm LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-text LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-textSm LeaderboardSkeleton-pulse" />
              </div>
            ))}
          </div>
        </div>
      );
    }

    if (this.state.contenderEntries.length === 0) return null;
    return <TopContendersGrid entries={this.state.contenderEntries} category={this.state.category} />;
  }

  honorableView() {
    if (this.state.honorableLoading) {
      return (
        <div className="LeaderboardHonorable LeaderboardSkeleton-honorable">
          <div className="LeaderboardSkeleton-sectionTitle LeaderboardSkeleton-pulse" />
          <div className="LeaderboardHonorable-grid">
            {[1, 2, 3, 4].map((i) => (
              <div className="LeaderboardItem LeaderboardSkeleton-item" key={i}>
                <div className="LeaderboardSkeleton-rank LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-avatar LeaderboardSkeleton-avatarXs LeaderboardSkeleton-pulse" />
                <div className="LeaderboardSkeleton-text LeaderboardSkeleton-pulse" style={{ flex: 1 }} />
                <div className="LeaderboardSkeleton-textSm LeaderboardSkeleton-pulse" />
              </div>
            ))}
          </div>
        </div>
      );
    }

    if (this.state.honorableEntries.length === 0) return null;
    return <LeaderboardList entries={this.state.honorableEntries} state={this.state} />;
  }
}
