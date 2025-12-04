/**
 * @file
 * Fixes for the Display Builder Wysiwyg block.
 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

Drupal.displayBuilder.fixWysiwyg = (elt, event) => {
  // When the editor is loaded, the textarea do not have a copy of the editor
  // input, reason unknown, here is a dirty fix.
  const _form = elt.closest('.db-island-contextual_form');

  if (!_form) return;
  const _textarea = _form.querySelector('textarea[data-ckeditor5-id]');
  if (!_textarea) return;
  _textarea.style.display = 'none';
  const _editor = _form.querySelector('.ck-content');
  if (!_editor) return;

  event.detail.parameters.set('value[value]', _editor.innerHTML);
};
