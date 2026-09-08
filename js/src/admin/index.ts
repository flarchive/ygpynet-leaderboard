import app from 'flarum/admin/app';
import LeaderboardSettingsPage from './components/LeaderboardSettingsPage';

export { default as extend } from '../common/extend';

app.initializers.add('ygpynet/leaderboard', () => {
  app.registry.for('ygpynet-leaderboard').registerPage(LeaderboardSettingsPage);

  app.registry.for('ygpynet-leaderboard').registerPermission(
    {
      icon: 'fas fa-trophy',
      label: app.translator.trans('ygpynet-leaderboard.admin.permissions.view_leaderboard'),
      permission: 'ygpynet-leaderboard.viewLeaderboard',
      allowGuest: true,
    },
    'view'
  );
});
