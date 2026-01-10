/**
 * @file
 * Build container for theme-isolated content.
 *
 * Loads content via AJAX from the frontend theme and injects it into
 * the container. CSS is loaded inline to provide style isolation.
 *
 * Note: Shadow DOM was initially considered but is incompatible with
 * Sortable.js drag-and-drop as events don't propagate through Shadow DOM
 * boundaries. Instead, we use direct DOM injection with CSS scoping.
 */

(function (Drupal, once) {
  'use strict';

  // Track pending reloads to prevent duplicates.
  const pendingReloads = new Map();

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
  async function initBuildContainer(container) {
    const builderId = container.dataset.dbBuildContainer;
    const src = container.dataset.dbBuildSrc;

    console.log('[Build Container] Initializing:', builderId, 'src:', src);

    if (!src) {
      console.error('[Build Container] Missing data-db-build-src:', builderId);
      return;
    }

    // Show loading state.
    container.classList.add('db-build-container--loading');

    try {
      await loadBuildContent(container, src, builderId);
      console.log('[Build Container] Content loaded successfully');
    } catch (error) {
      console.error('[Build Container] Failed to load content:', error);
      container.innerHTML = `<p class="db-build-container__error">Failed to load content: ${error.message}</p>`;
    } finally {
      container.classList.remove('db-build-container--loading');
    }
  }

  /**
   * Load content via AJAX and inject into container.
   *
   * @param {HTMLElement} container
   *   The container element.
   * @param {string} src
   *   The URL to fetch content from.
   * @param {string} builderId
   *   The builder ID.
   */
  async function loadBuildContent(container, src, builderId) {
    // Generate a unique request ID to track this specific load.
    const requestId = Date.now().toString();
    pendingReloads.set(builderId, requestId);

    console.log('[Build Container] Fetching:', src, 'requestId:', requestId);

    const response = await fetch(src, {
      headers: {
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    });

    // Check if this request is still the latest one.
    if (pendingReloads.get(builderId) !== requestId) {
      console.log('[Build Container] Request superseded, ignoring:', requestId);
      return;
    }

    console.log('[Build Container] Response status:', response.status);

    if (!response.ok) {
      const errorText = await response.text();
      console.error('[Build Container] Error response:', errorText.substring(0, 500));
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const contentType = response.headers.get('content-type');
    console.log('[Build Container] Content-Type:', contentType);

    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('[Build Container] Not JSON! First 500 chars:', text.substring(0, 500));
      throw new Error('Expected JSON but got: ' + contentType);
    }

    const data = await response.json();

    // Check again if this request is still the latest one (after parsing).
    if (pendingReloads.get(builderId) !== requestId) {
      console.log('[Build Container] Request superseded after parse, ignoring:', requestId);
      return;
    }

    console.log('[Build Container] API Response:', data);
    console.log('[Build Container] HTML length:', data.html?.length || 0);
    console.log('[Build Container] CSS count:', data.css?.length || 0);

    // Clear existing content.
    container.innerHTML = '';

    // Load CSS files and create inline styles.
    if (data.css && Array.isArray(data.css) && data.css.length > 0) {
      const cssPromises = data.css.map((cssUrl) => loadCssAsInline(cssUrl));
      const cssContents = await Promise.all(cssPromises);

      const styleEl = document.createElement('style');
      styleEl.setAttribute('data-db-scoped-css', builderId);
      styleEl.textContent = cssContents.join('\n');
      container.appendChild(styleEl);
    }

    // Create content wrapper for the build content.
    // Use a unique class name to avoid conflicts with the main island container.
    const outerWrapper = document.createElement('div');
    outerWrapper.className = 'db-build-wrapper';

    const contentWrapper = document.createElement('div');
    contentWrapper.className = 'db-build-content';
    contentWrapper.innerHTML = data.html || '';

    outerWrapper.appendChild(contentWrapper);
    container.appendChild(outerWrapper);

    // Merge drupalSettings if provided.
    if (data.settings && typeof data.settings === 'object') {
      Drupal.settings = Drupal.settings || {};
      Object.assign(Drupal.settings, data.settings);
    }

    // Attach Drupal behaviors to the new content.
    Drupal.attachBehaviors(outerWrapper, data.settings || {});

    // Initialize HTMX on the new content.
    // This is critical for drag-and-drop to work - the HTML has HTMX attributes
    // that need to be processed for events to fire.
    if (typeof htmx !== 'undefined' && htmx.process) {
      console.log('[Build Container] Initializing HTMX on new content');
      htmx.process(outerWrapper);
    }

    // Dispatch event to notify that build container content is ready.
    const event = new CustomEvent('db-build-container:ready', {
      detail: { builderId, container },
      bubbles: true,
    });
    container.dispatchEvent(event);
  }

  /**
   * Load a CSS file and return its content as a string.
   *
   * @param {string} cssUrl
   *   The URL of the CSS file.
   * @returns {Promise<string>}
   *   Promise resolving to the CSS content.
   */
  async function loadCssAsInline(cssUrl) {
    try {
      const response = await fetch(cssUrl, { credentials: 'same-origin' });
      if (response.ok) {
        return await response.text();
      }
    } catch (e) {
      console.warn('[Build Container] Failed to load CSS:', cssUrl, e);
    }
    return '';
  }

  // Global API for reloading build container.
  Drupal.displayBuilder = Drupal.displayBuilder || {};

  /**
   * Reload a build container's content.
   *
   * Called via SSE when content changes.
   *
   * @param {string} builderId
   *   The builder ID.
   */
  Drupal.displayBuilder.reloadBuildContainer = async function (builderId) {
    const container = document.querySelector(
      `[data-db-build-container="${builderId}"]`
    );

    if (!container) {
      console.warn('Build container not found:', builderId);
      return;
    }

    const src = container.dataset.dbBuildSrc;
    if (!src) {
      console.error('Build container missing src:', builderId);
      return;
    }

    container.classList.add('db-build-container--loading');

    try {
      await loadBuildContent(container, src, builderId);
    } catch (error) {
      console.error('Failed to reload build content:', error);
    } finally {
      container.classList.remove('db-build-container--loading');
    }
  };

  /**
   * Set up listeners to detect when content changes and reload the build container.
   *
   * After any HTMX POST/PUT/DELETE request completes (which indicates a state change),
   * we trigger a reload of all build containers.
   */
  function setupReloadTriggerListeners() {
    console.log('[Build Container] Setting up reload trigger listeners...');

    // Listen for HTMX afterSettle event - fires after all swaps are complete.
    // This catches all state-changing requests (POST, PUT, DELETE).
    document.body.addEventListener('htmx:afterSettle', (event) => {
      const method = event.detail?.requestConfig?.verb?.toUpperCase();
      const url = event.detail?.requestConfig?.path || '';

      console.log('[Build Container] htmx:afterSettle event, method:', method, 'url:', url);

      // Only reload after state-changing requests to the Display Builder API.
      if (url.includes('/api/display-builder/') && ['POST', 'PUT', 'DELETE'].includes(method)) {
        console.log('[Build Container] State change detected, reloading all build containers');
        reloadAllBuildContainers();
      }
    });

    // Also use MutationObserver for SSE-based updates.
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeType === Node.ELEMENT_NODE) {
            checkForReloadTriggers(node);
          }
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
    console.log('[Build Container] MutationObserver and HTMX listeners set up');
  }

  // Debounce timer for reload operations.
  let reloadDebounceTimer = null;

  /**
   * Reload all build containers on the page (debounced).
   */
  function reloadAllBuildContainers() {
    // Debounce rapid successive reload requests.
    if (reloadDebounceTimer) {
      clearTimeout(reloadDebounceTimer);
    }

    reloadDebounceTimer = setTimeout(() => {
      reloadDebounceTimer = null;
      const containers = document.querySelectorAll('[data-db-build-container]');
      containers.forEach((container) => {
        const builderId = container.dataset.dbBuildContainer;
        console.log('[Build Container] Reloading container:', builderId);
        Drupal.displayBuilder.reloadBuildContainer(builderId);
      });
    }, 100); // 100ms debounce
  }

  /**
   * Check for reload triggers within an element and its descendants.
   *
   * @param {HTMLElement} element
   *   The element to check.
   */
  function checkForReloadTriggers(element) {
    if (!element) {
      console.log('[Build Container] checkForReloadTriggers: no element');
      return;
    }

    console.log('[Build Container] checkForReloadTriggers:', element.tagName, element.id || '', element.className || '');

    // Check if the element itself is a reload trigger.
    if (element.dataset?.dbReloadTrigger) {
      console.log('[Build Container] Element IS a reload trigger!');
      handleReloadTrigger(element);
      return;
    }

    // Check for reload triggers within the element.
    if (element.querySelectorAll) {
      const triggers = element.querySelectorAll('[data-db-reload-trigger]');
      console.log('[Build Container] Found', triggers.length, 'triggers within element');
      triggers.forEach(handleReloadTrigger);
    }
  }

  /**
   * Handle a reload trigger element.
   *
   * @param {HTMLElement} element
   *   The element with data-db-reload-trigger attribute.
   */
  function handleReloadTrigger(element) {
    const builderId = element.dataset.dbReloadTrigger;
    if (!builderId) return;

    // Avoid processing the same trigger multiple times.
    if (element.dataset.dbReloadProcessed) return;
    element.dataset.dbReloadProcessed = 'true';

    console.log('[Build Container] Reload trigger detected for:', builderId);

    // Remove the trigger element.
    element.remove();

    // Trigger the reload.
    Drupal.displayBuilder.reloadBuildContainer(builderId);
  }

  // Set up listeners once DOM is ready.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupReloadTriggerListeners);
  } else {
    setupReloadTriggerListeners();
  }
})(Drupal, once);
