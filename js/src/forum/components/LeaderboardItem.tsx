import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import Avatar from 'flarum/common/components/Avatar';
import type Mithril from 'mithril';

import type LeaderboardEntry from '../../common/models/LeaderboardEntry';

interface ItemAttrs {
  entry: LeaderboardEntry;
  unit: string | null;
}

export default class LeaderboardItem extends Component<ItemAttrs> {
  view() {
    const { entry, unit } = this.attrs;
    const user = entry.user();
    const rank = entry.rank();

    return (
      <Link href={user ? app.route('user', { username: user.slug() }) : '#'} className="LeaderboardItem">
        <span className="LeaderboardItem-rank">#{rank}</span>
        <span className="LeaderboardItem-avatar">{user ? <Avatar user={user} /> : <span className="Avatar">?</span>}</span>
        <span className="LeaderboardItem-name">{user ? user.displayName() : '?'}</span>
        <span className="LeaderboardItem-points">
          {entry.score()}
          {unit ? ' ' + unit : ''}
        </span>
      </Link>
    );
  }
}
