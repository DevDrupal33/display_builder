/**
 * @file
 * Library preview on hover in Display Builder.
 *
 * The fetch itself is htmx's job: every library placeholder carries
 * hx-get + hx-trigger="mouseenter delay:..." + hx-target="#preview-<id>"
 * (@see \Drupal\display_builder\RenderableBuilderTrait::applyPreview).
 * This file owns only the *interaction* around that request:
 *
 * - it cancels requests whose element the pointer already left, because
 *   htmx's trigger `delay:` debounces the request but does not cancel it,
 *   so sweeping across the list would otherwise fire (and swap) one
 *   response per item passed over;
 * - it shows and positions the popup only once the response is in the DOM,
 *   since Floating UI must measure a box that already has its content -
 *   positioning an empty box then filling it makes the popup jump;
 * - it keeps repositioning while the popup is up, because content that is
 *   in the DOM is not yet content that has its final size;
 * - it hides with a short grace delay so a brief pointer excursion does
 *   not flicker the popup away.
 *
 * The popup is deliberately non-interactive (pointer-events: none, see
 * css/preview.css): it is informational, and anything the pointer could
 * enter there would need a hover bridge competing with starting a drag.
 */
/* cspell:ignore uidom */

Drupal.displayBuilder = Drupal.displayBuilder || {};

((
  Drupal,
  { computePosition, autoUpdate, offset, shift, flip, size, limitShift },
) => {
  /**
   * Placeholders that can show a preview.
   *
   * Set by RenderableBuilderTrait::applyPreview on both the list and the
   * card (mosaic) variants, so one selector covers every library panel.
   *
   * @type {string}
   */
  const TRIGGER_SELECTOR = '[data-preview]';

  /**
   * Milliseconds to wait after leaving a trigger before hiding.
   *
   * Long enough to survive crossing a sub-pixel gap between two rows,
   * short enough not to feel stuck.
   *
   * @type {number}
   */
  const HIDE_DELAY = 150;

  /**
   * Gap kept between the popup and the viewport edges, in pixels.
   *
   * @type {number}
   */
  const VIEWPORT_PADDING = 8;

  /**
   * Widest the popup may get, in pixels.
   *
   * @type {number}
   */
  const MAX_WIDTH = 800;

  /**
   * The placeholder the pointer is currently on, if any.
   *
   * @type {HTMLElement|null}
   */
  let currentTrigger = null;

  /**
   * Pending hide timer.
   *
   * @type {number|null}
   */
  let hideTimer = null;

  /**
   * Teardown for the running autoUpdate loop, if any.
   *
   * @type {Function|null}
   */
  let stopAutoUpdate = null;

  /**
   * Hide every preview popup and forget the current trigger.
   */
  const hidePreview = () => {
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    if (stopAutoUpdate) {
      stopAutoUpdate();
      stopAutoUpdate = null;
    }
    currentTrigger = null;
    document.querySelectorAll('.db-preview').forEach((preview) => {
      preview.hidden = true;
      preview.innerHTML = '';
      // size() wrote these for the previous item; a leftover cap would
      // constrain whatever is measured next.
      preview.style.maxHeight = '';
      preview.style.maxWidth = '';
    });
  };

  /**
   * Place a popup against its trigger and size it to the room available.
   *
   * @param {HTMLElement} preview - The popup, already filled.
   * @param {HTMLElement} trigger - The placeholder to anchor to.
   *
   * @return {Promise} Resolves once the coordinates are applied.
   */
  const position = (preview, trigger) =>
    computePosition(trigger, preview, {
      strategy: 'fixed',
      placement: 'right-start',
      middleware: [
        offset(12),
        // Left of the sidebar when there is no room on the right, then
        // nudged back into the viewport rather than clipped.
        flip({ fallbackPlacements: ['left-start'] }),
        shift({ padding: VIEWPORT_PADDING, limiter: limitShift() }),
        // Last, so it measures the room left at the final placement. A tall
        // component (Hero) hovered near the bottom of the list gets capped
        // to what fits on screen instead of running off it - shifting alone
        // cannot help once the popup is taller than the viewport.
        size({
          padding: VIEWPORT_PADDING,
          apply({ availableHeight, availableWidth, elements }) {
            Object.assign(elements.floating.style, {
              maxHeight: `${Math.round(availableHeight)}px`,
              maxWidth: `${Math.round(Math.min(MAX_WIDTH, availableWidth))}px`,
            });
          },
        }),
      ],
    }).then(({ x, y }) => {
      Object.assign(preview.style, { left: `${x}px`, top: `${y}px` });
    });

  /**
   * Position a popup, reveal it, and keep it placed while it is shown.
   *
   * The popup stays [hidden] - which css/preview.css implements without
   * display: none precisely so it is still measurable - until the first
   * coordinates land, otherwise it is briefly painted wherever the previous
   * preview left it.
   *
   * Positioning once is not enough: at swap time the markup is in the DOM
   * but its images have no intrinsic size yet, so the popup measures short,
   * no overflow is detected, and it then grows past the bottom of the
   * screen. autoUpdate re-runs the placement as the content settles (and on
   * scroll or resize), which is what actually keeps a Hero preview on
   * screen.
   *
   * @param {HTMLElement} preview - The popup, already filled.
   * @param {HTMLElement} trigger - The placeholder to anchor to.
   */
  const showPreview = (preview, trigger) => {
    if (stopAutoUpdate) {
      stopAutoUpdate();
    }

    stopAutoUpdate = autoUpdate(trigger, preview, () => {
      position(preview, trigger).then(() => {
        // The pointer may have left while we were measuring.
        if (currentTrigger === trigger) {
          preview.hidden = false;
        }
      });
    });
  };

  /**
   * Resolve the placeholder an event happened in, if any.
   *
   * @param {Event} event - A delegated event.
   *
   * @return {HTMLElement|null} The placeholder, or null outside one.
   */
  const triggerOf = (event) =>
    event.target instanceof Element
      ? event.target.closest(TRIGGER_SELECTOR)
      : null;

  /**
   * Make a placeholder the one the preview belongs to.
   *
   * @param {Event} event - mouseover or focusin.
   */
  const enter = (event) => {
    const trigger = triggerOf(event);
    if (!trigger || trigger === currentTrigger) {
      return;
    }

    // Moving straight from one placeholder to another: drop the old
    // content now so a stale preview is never shown next to a new item.
    if (currentTrigger) {
      hidePreview();
    }
    currentTrigger = trigger;
  };

  /**
   * Start the grace delay when a placeholder is really left.
   *
   * @param {Event} event - mouseout or focusout.
   */
  const leave = (event) => {
    const trigger = triggerOf(event);
    if (!trigger || trigger !== currentTrigger) {
      return;
    }
    // Still inside the same placeholder, just crossing a child boundary.
    if (
      event.relatedTarget instanceof Element &&
      trigger.contains(event.relatedTarget)
    ) {
      return;
    }

    hideTimer = setTimeout(hidePreview, HIDE_DELAY);
  };

  // focusin/focusout rather than focus/blur: only the former bubble to a
  // delegated listener, and placeholders are focusable (tabindex="0"), so
  // keyboard users get the same preview as pointer users.
  document.addEventListener('mouseover', enter);
  document.addEventListener('focusin', enter);
  document.addEventListener('mouseout', leave);
  document.addEventListener('focusout', leave);

  // A preview hanging over the canvas during a drag is noise, and its
  // request would keep firing while the pointer is held down.
  ['mousedown', 'dragstart', 'scroll'].forEach((type) => {
    document.addEventListener(type, hidePreview, true);
  });

  // Escape is how a keyboard user dismisses this: they reach a placeholder
  // through focusin, not the pointer, so none of the mousedown/dragstart
  // hides above will ever fire for them.
  //
  // Capture phase, and it stops nothing. Escape walks a chain of things to
  // close - contextual menu, then Settings drawer, then Libraries drawer
  // (@see components/contextual_menu/contextual_menu.js and js/sidebar.js) -
  // and the first of those does stop the event once it acts. This popup is
  // not part of that chain: it is non-interactive informational chrome
  // (pointer-events: none), not a mode the user is in, so it should go on
  // any Escape rather than wait its turn behind one.
  document.addEventListener(
    'keydown',
    (event) => {
      if (event.key === 'Escape') {
        hidePreview();
      }
    },
    true,
  );

  // htmx's trigger `delay:` debounces the request, it does not cancel it -
  // without this every placeholder swept over would still fetch and swap.
  document.addEventListener('htmx:beforeRequest', (event) => {
    const elt = event.detail?.elt;
    if (elt?.matches?.(TRIGGER_SELECTOR) && elt !== currentTrigger) {
      event.preventDefault();
    }
  });

  document.addEventListener('htmx:afterSwap', (event) => {
    const preview = event.target;
    if (!(preview instanceof Element) || !preview.matches('.db-preview')) {
      return;
    }

    const trigger = event.detail?.requestConfig?.elt;
    if (!trigger || trigger !== currentTrigger) {
      preview.innerHTML = '';
      return;
    }

    showPreview(preview, trigger);
  });

  // Kept on the namespace: draggables.js hides the preview when a drag
  // starts from the library.
  Drupal.displayBuilder.hidePreview = hidePreview;
})(Drupal, FloatingUIDOM);
