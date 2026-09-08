import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

import LeaderboardItem from './LeaderboardItem';
import type LeaderboardState from '../utils/LeaderboardState';
import type LeaderboardEntry from '../../common/models/LeaderboardEntry';
import { categoryUnit } from '../categories';

interface ListAttrs {
  entries: LeaderboardEntry[];
  state: LeaderboardState;
}

export default class LeaderboardList extends Component<ListAttrs> {
  view() {
    const { entries, state } = this.attrs;
    const unit = categoryUnit(state.category);

    return (
      <div className="LeaderboardHonorable">
        <h3 className="LeaderboardHonorable-title">
          <i className="fas fa-list-ol" />
          {app.translator.trans('ygpynet-leaderboard.forum.honorable.title')}
        </h3>
        <div className="LeaderboardHonorable-grid">
          {entries.map((entry) => (
            <LeaderboardItem
              key={entry.id()}
              entry={entry}
              unit={unit}
            />
          ))}
        </div>

        {state.hasMore && (
          <div className="LeaderboardHonorable-loadMore">
            <Button className="Button" loading={state.loadingMore} onclick={() => state.loadMore()}>
              {app.translator.trans('ygpynet-leaderboard.forum.list.load_more')}
            </Button>
          </div>
        )}
      </div>
    );
  }
}
