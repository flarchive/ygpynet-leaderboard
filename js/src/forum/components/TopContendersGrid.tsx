import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import type Mithril from 'mithril';

import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
import { STAT_DEFS, categoryUnit } from '../categories';

interface ContendersAttrs {
  entries: LeaderboardEntry[];
  category: string;
}

export default class TopContendersGrid extends Component<ContendersAttrs> {
  view() {
    const { entries, category } = this.attrs;

    if (entries.length === 0) {
      return null;
    }

    const unit = categoryUnit(category);

    return (
      <div className="LeaderboardContenders">
        <h3 className="LeaderboardContenders-title">
          <i className="fas fa-medal" />
          {app.translator.trans('huseyinfiliz-leaderboard.forum.contenders.title')}
        </h3>
        <div className="LeaderboardContenders-grid">
          {entries.map((entry) => {
            const user = entry.user();
            const rank = entry.rank();

            return (
              <Link href={user ? app.route('user', { username: user.slug() }) : '#'} className="LeaderboardContenders-card" key={entry.id()}>
                <span className="LeaderboardContenders-rank">#{rank}</span>
                <div className="LeaderboardContenders-avatar">{user ? <Avatar user={user} /> : <span className="Avatar">?</span>}</div>
                <span className="LeaderboardContenders-name">{user ? user.displayName() : '?'}</span>
                <span className="LeaderboardContenders-points">
                  {entry.score()}
                  {unit ? ' ' + unit : ''}
                </span>
                {user && this.statsView(user)}
              </Link>
            );
          })}
        </div>
      </div>
    );
  }

  statsView(user: any) {
    const keys = Object.keys(STAT_DEFS).filter((k) => user.attribute(k) !== undefined);

    if (keys.length === 0) return null;

    return (
      <div className="LeaderboardContenders-stats">
        {keys.map((key) => {
          const def = STAT_DEFS[key];
          return (
            <span className="LeaderboardContenders-stat" title={app.translator.trans(def.labelKey) as string}>
              <i className={def.icon} />
              {user.attribute(key)}
            </span>
          );
        })}
      </div>
    );
  }
}
