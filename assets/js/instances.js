/**
 * @file
 * Instances panel: reveal the rows a group caps, and lift the cap to search.
 */
/* eslint no-use-before-define: 0 */

((Drupal, once) => {
  /**
   * The class on a row hidden by its group's cap.
   *
   * @type {string}
   */
  const overCapClass = 'db-instances__item--over-cap';

  /**
   * Drupal behavior for the Instances panel.
   *
   * Delegated from the panel root, so a pane rebuilt off-screen comes back
   * working. The cap itself resets to its rendered state, which is the honest
   * thing for a list that was rebuilt.
   *
   * The other half of the cap needs no JavaScript here: the shared filter
   * searches every row, capped ones included, and marks the container it
   * filters, which is what the CSS lifts the cap on.
   *
   * @see components/library_panel/search.js
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior.
   */
  Drupal.behaviors.displayBuilderInstances = {
    attach(context) {
      once('dbInstances', '.db-instances', context).forEach((panel) => {
        panel.addEventListener('click', (event) => {
          const button = event.target.closest('.db-instances__more');
          if (button) {
            revealMore(button);
          }
        });
      });
    },
  };

  /**
   * Reveals the next batch of rows of one group.
   *
   * @param {HTMLElement} button
   *   The View more button of the group to reveal rows in.
   */
  const revealMore = (button) => {
    const step = parseInt(button.dataset.step, 10);
    const capped = button
      .closest('.db-instances__group')
      .querySelectorAll(`.${overCapClass}`);

    Array.from(capped)
      .slice(0, step)
      .forEach((item) => item.classList.remove(overCapClass));

    const left = capped.length - step;
    if (left <= 0) {
      button.remove();
      return;
    }

    button.textContent = Drupal.formatPlural(
      left,
      'View 1 more',
      'View @count more',
    );
  };
})(Drupal, once);
