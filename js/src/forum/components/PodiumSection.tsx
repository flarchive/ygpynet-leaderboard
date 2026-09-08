import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import type Mithril from 'mithril';

import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
import { STAT_DEFS, categoryUnit } from '../categories';

interface PodiumAttrs {
  entries: LeaderboardEntry[];
  category: string;
}

export default class PodiumSection extends Component<PodiumAttrs> {
  view() {
    const { entries, category } = this.attrs;

    if (entries.length === 0) {
      return null;
    }

    const unit = categoryUnit(category);

    // Display order: 2nd, 1st, 3rd (desktop uses CSS order, mobile overrides)
    const ordered = [entries[1], entries[0], entries[2]].filter(Boolean);

    return (
      <div className="LeaderboardPodium">
        {ordered.map((entry) => {
          const rank = entry.rank();
          const user = entry.user();
          const placeClass = `LeaderboardPodium-place--${rank}`;
          const medalClass = rank === 1 ? 'gold' : rank === 2 ? 'silver' : 'bronze';

          return (
            <Link
              href={user ? app.route('user', { username: user.slug() }) : '#'}
              className={`LeaderboardPodium-place ${placeClass}`}
              key={entry.id()}
            >
              <div className={`LeaderboardPodium-medal LeaderboardPodium-medal--${medalClass}`}>
                {rank === 1 && <i className="fas fa-crown LeaderboardPodium-crown" />}
                <span className="LeaderboardPodium-rankNumber">#{rank}</span>
              </div>
              <div className="LeaderboardPodium-avatar">{user ? <Avatar user={user} /> : <span className="Avatar">?</span>}</div>
              <div className="LeaderboardPodium-name">{user ? user.displayName() : '?'}</div>
              <div className="LeaderboardPodium-points">
                {entry.score()}
                {unit ? ' ' + unit : ''}
              </div>
              {user && this.statsView(user)}
            </Link>
          );
        })}
      </div>
    );
  }

  statsView(user: any) {
    const keys = Object.keys(STAT_DEFS).filter((k) => user.attribute(k) !== undefined);

    if (keys.length === 0) return null;

    return (
      <div className="LeaderboardPodium-stats">
        {keys.map((key) => {
          const def = STAT_DEFS[key];
          return (
            <span className="LeaderboardPodium-stat" title={app.translator.trans(def.labelKey) as string}>
              <i className={def.icon} />
              {user.attribute(key)}
            </span>
          );
        })}
      </div>
    );
  }
}
