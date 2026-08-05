/**
 * @param Drupal
 * @param once
 * @file
 * Flash-free live preview refresh.
 *
 * The Preview island renders a persistent <iframe> plus a hidden refresh token.
 * On every change the server bumps the token's content via an out-of-band swap
 * rather than rebuilding the iframe (@see
 * \Drupal\display_builder\Plugin\display_builder\Island\PreviewPanel). Replacing
 * the iframe would reload it from blank and flash white on each edit; instead
 * this watches the token and double-buffers: the fresh render loads into a
 * second iframe stacked invisibly over the current one, and is swapped in only
 * once it has loaded, so the visible render never blanks.
 *
 * Rapid edits are coalesced - one buffer is ever in flight, and a single
 * trailing refresh runs after it lands so the last change always wins.
 */

((Drupal, once) => {
  Drupal.displayBuilder = Drupal.displayBuilder || {};

  /**
   * The iframe currently showing the render (never a loading buffer).
   *
   * @param {HTMLElement} pane - The preview pane.
   * @return {HTMLIFrameElement|null} The visible iframe, or null.
   */
  function currentIframe(pane) {
    return pane.querySelector('.db-live-preview:not(.db-live-preview--buffer)');
  }

  /**
   * Load the current render into an off-screen buffer, then swap it in.
   *
   * @param {HTMLElement} pane - The preview pane.
   */
  function refresh(pane) {
    const state = pane.dbPreviewState;

    // One buffer at a time: note that another refresh is wanted and let the
    // in-flight one finish, then run exactly once more with the latest render.
    if (state.loading) {
      state.pending = true;
      return;
    }

    const current = currentIframe(pane);
    const url = current?.getAttribute('src');
    if (!url) {
      return;
    }
    state.loading = true;

    const buffer = document.createElement('iframe');
    buffer.className = 'db-live-preview db-live-preview--buffer';
    buffer.title = current.title;
    buffer.addEventListener(
      'load',
      () => {
        // Promote the buffer, drop the previous render(s).
        pane
          .querySelectorAll('.db-live-preview:not(.db-live-preview--buffer)')
          .forEach((el) => el.remove());
        buffer.classList.remove('db-live-preview--buffer');
        state.loading = false;

        if (state.pending) {
          state.pending = false;
          refresh(pane);
        }
      },
      { once: true },
    );
    buffer.setAttribute('src', url);
    // Load into the scale box, so the buffer inherits the same zoom scaling as
    // the current render. @see the .db-live-preview rules in
    // components/display_builder/css/display_builder.css.
    (pane.querySelector('.db-live-preview-scale') || pane).appendChild(buffer);
  }

  /**
   * Drupal behavior wiring the flash-free preview refresh.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.displayBuilderLivePreview = {
    attach(context) {
      once('dbLivePreview', '.db-live-preview-refresh', context).forEach(
        (token) => {
          const pane = token.closest('.db-island-preview');
          if (!pane) {
            return;
          }
          pane.dbPreviewState = pane.dbPreviewState || {
            loading: false,
            pending: false,
          };

          // The token element persists across reloads (only its content is
          // swapped), so one observer keeps working for the pane's lifetime.
          new MutationObserver(() => refresh(pane)).observe(token, {
            childList: true,
            characterData: true,
            subtree: true,
          });
        },
      );
    },
  };
})(Drupal, once);
