import Extend from 'flarum/common/extenders';
import LeaderboardEntry from './models/LeaderboardEntry';

export default [new Extend.Store().add('leaderboard-entries', LeaderboardEntry), new Extend.Model(LeaderboardEntry).hasOne('user')];
