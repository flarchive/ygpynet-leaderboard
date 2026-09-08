import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';

import LeaderboardPage from './components/LeaderboardPage';

export { default as extend } from '../common/extend';

app.initializers.add('huseyinfiliz/leaderboard', () => {
  app.routes['huseyinfiliz-leaderboard.index'] = {
    path: '/leaderboard',
    component: LeaderboardPage,
  };

  // Add sidebar nav link
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('canViewLeaderboard')) return;

    const leaderboardName = app.forum.attribute('huseyinfiliz-leaderboard.leaderboard_name') || 'Leaderboard';

    items.add(
      'huseyinfiliz-leaderboard',
      <LinkButton href={app.route('huseyinfiliz-leaderboard.index')} icon="fas fa-trophy">
        {leaderboardName}
      </LinkButton>,
      10
    );
  });

  // Points on user cards are rendered by ygpynet/point-system — no duplicate here.
});
