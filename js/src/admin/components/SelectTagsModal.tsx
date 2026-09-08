import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';

interface SelectTagsModalAttrs extends IFormModalAttrs {
  selectedTagIds: number[];
  onsubmit: (ids: number[]) => void;
}

export default class SelectTagsModal<CustomAttrs extends SelectTagsModalAttrs = SelectTagsModalAttrs> extends FormModal<CustomAttrs> {
  private selected!: Set<string>;

  oninit(vnode: Mithril.Vnode<CustomAttrs>) {
    super.oninit(vnode);
    this.selected = new Set(this.attrs.selectedTagIds.map(String));
  }

  className() {
    return 'SelectTagsModal Modal--small';
  }

  title() {
    return app.translator.trans('huseyinfiliz-leaderboard.admin.settings.excluded_tags_label');
  }

  content() {
    // Reuse flarum-tags' sorting/label helpers for display only — we intentionally
    // do NOT reuse its TagSelectionModal, since that modal enforces selecting a
    // parent before a child tag (a rule meant for discussion tagging, not for
    // picking tags to exclude from the leaderboard). Each row here is an
    // independent checkbox so a sub-tag can be selected on its own.
    const sortTags = require('ext:flarum/tags/common/utils/sortTags');
    const tagLabel = require('ext:flarum/tags/common/helpers/tagLabel');
    const tags = sortTags(app.store.all('tags'));

    return (
      <div className="Modal-body">
        <div className="SelectTagsModal-list">
          {tags.map((tag: any) => {
            const tid = tag.id()!;
            const isSelected = this.selected.has(tid);
            const isChild = !!tag.parent?.();

            return (
              <label className={'SelectTagsModal-item' + (isChild ? ' SelectTagsModal-item--child' : '')} key={tid}>
                <input
                  type="checkbox"
                  checked={isSelected}
                  onchange={() => {
                    if (isSelected) {
                      this.selected.delete(tid);
                    } else {
                      this.selected.add(tid);
                    }
                    m.redraw();
                  }}
                />
                {tagLabel(tag)}
              </label>
            );
          })}
        </div>
        <div className="SelectTagsModal-actions">
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

  submit() {
    const ids = Array.from(this.selected).map(Number);
    this.attrs.onsubmit(ids);
    this.hide();
  }
}
