import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';

import LeaderboardPage from './components/LeaderboardPage';

export { default as extend } from '../common/extend';

app.initializers.add('ygpynet/leaderboard', () => {
  app.routes['ygpynet-leaderboard.index'] = {
    path: '/leaderboard',
    component: LeaderboardPage,
  };

  // Add sidebar nav link
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('canViewLeaderboard')) return;

    const leaderboardName = app.forum.attribute('ygpynet-leaderboard.leaderboard_name') || '排行榜';

    items.add(
      'ygpynet-leaderboard',
      <LinkButton href={app.route('ygpynet-leaderboard.index')} icon="fas fa-trophy">
        {leaderboardName}
      </LinkButton>,
      10
    );
  });

  // Points on user cards are rendered by ygpynet/point-system — no duplicate here.
});
