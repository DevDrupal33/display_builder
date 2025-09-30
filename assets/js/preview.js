/**
 * Libraries preview in Display Builder.
 */
/* cspell:ignore uidom */
/* eslint no-console: 0 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

/**
 * Show preview in floating ui.
 *
 * @param {HTMLElement} builder - The builder.
 * @param {HTMLElement} trigger - The menu element.
 */
Drupal.displayBuilder.showPreview = (builder, trigger) => {
  if (!FloatingUIDOM) return;

  const preview = builder.querySelector('.db-preview');
  if (!preview) return;

  preview.innerHTML = '';
  preview.style.display = 'block';

  FloatingUIDOM.computePosition(trigger, preview, {
    placement: 'right-start',
    middleware: [
      FloatingUIDOM.offset({ mainAxis: 20, alignmentAxis: 40 }),
      FloatingUIDOM.shift({ crossAxis: true }),
      FloatingUIDOM.autoPlacement({}),
    ],
  }).then(({ x, y }) => {
    Object.assign(preview.style, {
      left: `${x}px`,
      top: `${y}px`,
    });
  });
};

/**
 * Hide preview in floating ui.
 *
 * @param {HTMLElement} builder - The builder.
 */
Drupal.displayBuilder.hidePreview = (builder) => {
  const preview = builder.querySelector('.db-preview');
  if (!preview) return;
  preview.style.display = 'none';
  preview.innerHTML = '';
};
