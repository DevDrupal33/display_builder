/**
 * @file
 * Build container for theme-isolated content.
 *
 * Content is rendered server-side via subrequest with the frontend theme.
 * This script injects the frontend theme's CSS (scoped) into the build
 * container to provide CSS isolation from the backend admin theme.
 *
 * CSS is injected once on init - it doesn't change during the session.
 * HTMX updates work automatically since elements stay in regular DOM.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize build containers.
   */
  Drupal.behaviors.displayBuilderBuildContainer = {
    attach(context) {
      once('db-build-container', '[data-db-build-container]', context).forEach(
        (container) => {
          initBuildContainer(container);
        }
      );
    },
  };

  /**
   * Initialize a single build container.
   *
   * @param {HTMLElement} container
   *   The container element.
   */
  function initBuildContainer(container) {
    const cssUrls = JSON.parse(container.dataset.dbBuildCss || '[]');

    // Inject scoped CSS once - it doesn't change during the session.
    injectScopedCss(container, cssUrls);

    // Dispatch ready event.
    container.dispatchEvent(new CustomEvent('db-build-container:ready', {
      detail: { builderId: container.dataset.dbBuildContainer, container },
      bubbles: true,
    }));
  }

  /**
   * Inject scoped CSS into container.
   *
   * @param {HTMLElement} container
   *   The container element.
   * @param {string[]} cssUrls
   *   Array of CSS file URLs.
   */
  async function injectScopedCss(container, cssUrls) {
    if (!cssUrls || cssUrls.length === 0) {
      return;
    }

    const styleEl = document.createElement('style');
    styleEl.setAttribute('data-db-scoped-css', 'true');

    // Fetch and scope all CSS files.
    const cssContents = await Promise.all(
      cssUrls.map(async (url) => {
        try {
          const response = await fetch(url, { credentials: 'same-origin' });
          if (response.ok) {
            const css = await response.text();
            return scopeCss(css, '.db-build-content');
          }
        } catch (e) {
          // Silently ignore failed CSS loads.
        }
        return '';
      })
    );

    styleEl.textContent = cssContents.join('\n');
    container.insertBefore(styleEl, container.firstChild);
  }

  /**
   * Scope CSS rules to a container selector.
   *
   * Prepends the scope selector to all CSS rules so they only
   * apply within .db-build-content, preventing frontend CSS
   * from affecting the backend admin theme.
   *
   * @param {string} css
   *   The CSS content.
   * @param {string} scope
   *   The selector to scope to.
   * @returns {string}
   *   The scoped CSS.
   */
  function scopeCss(css, scope) {
    return css.replace(
      /(^|})\s*([^@{}]+)\s*{/gm,
      (match, prefix, selectors) => {
        // Skip @-rules.
        if (selectors.trim().startsWith('@')) {
          return match;
        }

        const scoped = selectors.split(',').map(s => {
          s = s.trim();
          if (!s) return s;
          // Convert :root, html, body to the scope selector.
          if (s === ':root' || s === 'html' || s === 'body') {
            return scope;
          }
          return `${scope} ${s}`;
        }).join(', ');

        return `${prefix} ${scoped} {`;
      }
    );
  }

  // Global API.
  Drupal.displayBuilder = Drupal.displayBuilder || {};

  Drupal.displayBuilder.getBuildContent = function (builderId) {
    const container = document.querySelector(`[data-db-build-container="${builderId}"]`);
    return container?.querySelector('.db-build-content') || null;
  };

})(Drupal, once);
