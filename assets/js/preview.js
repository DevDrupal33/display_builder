/**
 * Libraries preview in Display Builder.
 */
/* cspell:ignore uidom */
/* eslint no-console: 0 */

// ((Drupal, debounce, once) => {
//   /**
//    * Drupal behavior for display builder search.
//    *
//    * @type {Drupal~behavior}
//    *
//    * @prop {Drupal~behaviorAttach} attach
//    *   Attaches the behavior.
//    *
//    * @listens shoelace:sl-input
//    */
//   Drupal.behaviors.builderPreviewBehaviors = {
//     attach(context, settings) {
//       once('dbPreview', '.', context).forEach((input) => {

//       })
//     }
//   }
// });

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
      FloatingUIDOM.offset({ mainAxis: 10 }),
      FloatingUIDOM.shift({}),
      FloatingUIDOM.autoPlacement({
        alignment: 'start',
        autoAlignment: false,
        allowedPlacements: ['top', 'right'],
      }),
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
