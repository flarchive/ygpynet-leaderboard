import app from 'flarum/admin/app';
import LeaderboardSettingsPage from './components/LeaderboardSettingsPage';

export { default as extend } from '../common/extend';

app.initializers.add('huseyinfiliz/leaderboard', () => {
  app.registry.for('huseyinfiliz-leaderboard').registerPage(LeaderboardSettingsPage);

  app.registry.for('huseyinfiliz-leaderboard').registerPermission(
    {
      icon: 'fas fa-trophy',
      label: app.translator.trans('huseyinfiliz-leaderboard.admin.permissions.view_leaderboard'),
      permission: 'huseyinfiliz-leaderboard.viewLeaderboard',
      allowGuest: true,
    },
    'view'
  );
});
