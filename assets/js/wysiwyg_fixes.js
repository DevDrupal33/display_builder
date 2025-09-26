// @see src/HtmxEvents.php
// For unknown reason the CKEditor do not copy the text in the textarea.
// We manually copy the editor value in the textarea for a working update.
/* eslint no-unused-vars: 0 */
/* eslint no-console: 0 */
function fixWysiwygUpdate(elt, event) {
  const _form = elt.closest('.db-island-contextual_form');
  if (!_form) return;
  const _textarea = _form.querySelector('textarea[data-ckeditor5-id]');
  if (!_textarea) return;
  const _editor = _form.querySelector('.ck-content');
  if (!_editor) return;

  event.detail.parameters.set('value[value]', _editor.innerHTML);
}
