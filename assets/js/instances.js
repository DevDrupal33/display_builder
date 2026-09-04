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

  /**
   * Which data attribute pair a publishing action's dot state lives in.
   *
   * Publish and Restore both end with the draft matching what is published,
   * so they share the 'published' outcome; Revert always ends with the two
   * disagreeing again, so it gets its own 'unpublished' outcome.
   * @see \Drupal\display_builder\Plugin\display_builder\Island\InstancesPanel::buildDotStateAttributes
   *
   * @type {Object<string, string>}
   */
  const DOT_STATE_BY_SUFFIX = {
    '/publish': 'publishedState',
    '/restore': 'publishedState',
    '/revert': 'unpublishedState',
  };

  /**
   * Flips the current row's status dot right after publish/restore/revert.
   *
   * Reacts to the request directly instead of through the island/event
   * system: each of these actions only ever changes the row being edited,
   * and the server already computed what its dot looks like afterwards into
   * data attributes, so no reload of this panel is needed to reflect it.
   *
   * @see assets/js/request_action.js
   *
   * @listens htmx:afterRequest
   */
  window.addEventListener('htmx:afterRequest', (event) => {
    const path = event.detail?.successful
      ? event.detail?.requestConfig?.path
      : null;
    const stateKey = Drupal.displayBuilder.matchRequestPath(
      path,
      DOT_STATE_BY_SUFFIX,
    );
    if (!stateKey) {
      return;
    }

    const labelKey = `${stateKey}Label`;

    document
      .querySelectorAll(
        '.db-instances__item--current .db-instances__status-dot',
      )
      .forEach((dot) => {
        if (!(stateKey in dot.dataset)) {
          return;
        }

        const severity = dot.dataset[stateKey];
        const label = dot.dataset[labelKey];

        dot.className = severity
          ? `db-instances__status-dot db-instances__status-dot--${severity}`
          : 'db-instances__status-dot';

        if (label) {
          dot.setAttribute('role', 'img');
          dot.setAttribute('aria-label', label);
          dot.setAttribute('title', label);
        } else {
          dot.removeAttribute('role');
          dot.removeAttribute('aria-label');
          dot.removeAttribute('title');
        }

        delete dot.dataset.publishedState;
        delete dot.dataset.publishedStateLabel;
        delete dot.dataset.unpublishedState;
        delete dot.dataset.unpublishedStateLabel;
      });
  });
})(Drupal, once);
