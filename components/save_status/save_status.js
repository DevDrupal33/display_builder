/**
 * @file
 * Save status pip: flips state from the client's own request, not a reload.
 *
 * The four labels the pip can show are static, translated strings shipped
 * once as data attributes by \Drupal\display_builder\Plugin\display_builder\
 * Island\SaveStatus::build() (the resting state on load or a plain
 * reload()). Every other transition is inferred here from which endpoint
 * the client itself just called successfully, instead of asking PHP to
 * rebuild and OOB-swap the whole pip.
 *
 * @see \Drupal\display_builder\Plugin\display_builder\Island\SaveStatus
 * @see assets/js/request_action.js
 */

((Drupal) => {
  /**
   * Path suffix to the status it puts the pip in.
   *
   * @type {Object<string, string>}
   */
  const STATUS_BY_SUFFIX = {
    '/publish': 'published',
    '/restore': 'restored',
    '/revert': 'reverted',
  };

  /**
   * Whether a request is a write against the given instance's own API.
   *
   * Everything else the module dispatches (attach, move, update, delete,
   * undo, redo, and the paste/style variants of those) settles the draft in
   * the same 'saved' state, so this is the fallback once publish/restore/
   * revert have already been ruled out. save_as_preset writes a separate
   * preset entity rather than this instance's draft, so it is the one
   * exclusion.
   *
   * @param {string} path
   *   The request path.
   * @param {string} verb
   *   The request's HTTP verb.
   * @param {string} instanceId
   *   The display builder instance the pip belongs to.
   *
   * @return {boolean}
   *   Whether this is a mutating request against the instance's own API.
   */
  const isOwnMutation = (path, verb, instanceId) => {
    const prefix = `/api/display-builder/${instanceId}`;
    const ownApi = path === prefix || path.startsWith(`${prefix}/`);
    const mutating = ['post', 'put'].includes(verb?.toLowerCase());

    return ownApi && mutating && !path.endsWith('/save_as_preset');
  };

  /**
   * Classifies a successful request against one instance's own API.
   *
   * @param {string} path
   *   The request path.
   * @param {string} verb
   *   The request's HTTP verb.
   * @param {string} instanceId
   *   The display builder instance the pip belongs to.
   *
   * @return {string|null}
   *   One of 'published', 'restored', 'reverted', 'saved', or null when the
   *   request is not one of this instance's mutating endpoints.
   */
  const classify = (path, verb, instanceId) => {
    const suffixMatch = Drupal.displayBuilder.matchRequestPath(
      path,
      STATUS_BY_SUFFIX,
    );

    return (
      suffixMatch ?? (isOwnMutation(path, verb, instanceId) ? 'saved' : null)
    );
  };

  /**
   * Applies a status to the pip, replaying its one-shot pulse.
   *
   * Setting a class that is already present does not replay an already-run
   * CSS animation, so the pulse class is left off the fresh className and a
   * forced reflow separates removing it from adding it back.
   *
   * @param {HTMLElement} pip
   *   The '.db-save-status' element.
   * @param {string} status
   *   One of 'saved', 'published', 'restored', 'reverted'.
   */
  const applyStatus = (pip, status) => {
    const key = `label${status.charAt(0).toUpperCase()}${status.slice(1)}`;
    const label = pip.dataset[key];
    const dot = pip.querySelector('.db-save-status__dot');

    pip.className = `db-save-status db-save-status--${status}`;
    if (dot) {
      if (label) {
        dot.setAttribute('title', label);
      } else {
        dot.removeAttribute('title');
      }
    }

    // Force a reflow before adding the pulse class back, so the animation
    // restarts even if the pip was already mid-pulse for a prior action.
    pip.getBoundingClientRect();
    pip.classList.add('db-save-status--pulse');
  };

  window.addEventListener('htmx:afterRequest', (event) => {
    const { requestConfig, successful } = event.detail ?? {};
    const path = successful ? requestConfig?.path : null;
    if (!path) {
      return;
    }

    document.querySelectorAll('.db-save-status').forEach((pip) => {
      // The instance ID lives on the '.display-builder' root's own id, the
      // same source \Drupal\display_builder\Island\IslandPluginBase::
      // getHtmlId() builds every OOB target from server-side.
      // @see components/display_builder/js/split.js
      const instanceId = pip.closest('.display-builder')?.id;
      const status =
        instanceId && classify(path, requestConfig?.verb, instanceId);
      if (status) {
        applyStatus(pip, status);
      }
    });
  });
})(Drupal);
