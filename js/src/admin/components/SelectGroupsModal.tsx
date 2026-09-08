import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import GroupBadge from 'flarum/common/components/GroupBadge';
import type Group from 'flarum/common/models/Group';
import type Mithril from 'mithril';

interface SelectGroupsModalAttrs extends IFormModalAttrs {
  selectedGroupIds: number[];
  onsubmit: (ids: number[]) => void;
}

export default class SelectGroupsModal<CustomAttrs extends SelectGroupsModalAttrs = SelectGroupsModalAttrs> extends FormModal<CustomAttrs> {
  private selected!: Set<string>;

  oninit(vnode: Mithril.Vnode<CustomAttrs>) {
    super.oninit(vnode);
    this.selected = new Set(this.attrs.selectedGroupIds.map(String));
  }

  className() {
    return 'SelectGroupsModal Modal--small';
  }

  title() {
    return app.translator.trans('huseyinfiliz-leaderboard.admin.settings.excluded_groups_label');
  }

  content() {
    const groups = this.getAvailableGroups();

    return (
      <div className="Modal-body">
        <div className="SelectGroupsModal-list">
          {groups.map((group) => {
            const gid = group.id()!;
            const isSelected = this.selected.has(gid);

            return (
              <label className="SelectGroupsModal-item" key={gid}>
                <input
                  type="checkbox"
                  checked={isSelected}
                  onchange={() => {
                    if (isSelected) {
                      this.selected.delete(gid);
                    } else {
                      this.selected.add(gid);
                    }
                    m.redraw();
                  }}
                />
                <GroupBadge group={group} label={null} />
                <span className="SelectGroupsModal-name">{group.nameSingular()}</span>
              </label>
            );
          })}
        </div>
        <div className="SelectGroupsModal-actions">
          <Button className="Button Button--primary" onclick={() => this.submit()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.save')}
          </Button>
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('huseyinfiliz-leaderboard.admin.modals.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  getAvailableGroups(): Group[] {
    return app.store
      .all<Group>('groups')
      .filter((group) => {
        const gid = group.id();
        return gid !== '2';
      })
      .sort((a, b) => {
        const aName = a.namePlural() || '';
        const bName = b.namePlural() || '';
        return aName.localeCompare(bName);
      });
  }

  submit() {
    const ids = Array.from(this.selected).map(Number);
    this.attrs.onsubmit(ids);
    this.hide();
  }
}
