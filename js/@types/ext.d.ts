declare module 'ext:flarum/tags/common/components/TagSelectionModal' {
  import FormModal from 'flarum/common/components/FormModal';
  export default class TagSelectionModal extends FormModal {}
}

// `m` is provided at build time by flarum-webpack-config (ProvidePlugin) and
// has no ambient declaration in flarum/core's published dist-typings.
declare const m: import('mithril').Static;
