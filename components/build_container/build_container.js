/**
 * @file
 * Build Container Web Component with Shadow DOM for CSS isolation.
 *
 * This component creates a Shadow DOM boundary to completely isolate
 * the builder content from backend theme CSS.
 */

(function (Drupal) {
  'use strict';

  /**
   * Custom element for Display Builder build container with Shadow DOM.
   */
  class DbBuildContainer extends HTMLElement {
    constructor() {
      super();
      this._shadowRoot = null;
    }

    connectedCallback() {
      // Only attach shadow DOM once
      if (this._shadowRoot) {
        return;
      }

      // Get the original content before attaching shadow
      const originalContent = this.innerHTML;
      const originalAttributes = {};

      // Copy data attributes to shadow host for external access
      for (const attr of this.attributes) {
        if (attr.name.startsWith('data-')) {
          originalAttributes[attr.name] = attr.value;
        }
      }

      // Attach shadow DOM
      this._shadowRoot = this.attachShadow({ mode: 'open' });

      // Create container inside shadow DOM
      const container = document.createElement('div');
      container.className = 'db-build-container-inner';
      container.innerHTML = originalContent;

      // Add minimal reset styles inside shadow DOM
      const style = document.createElement('style');
      style.textContent = `
        :host {
          display: block;
          min-height: 100%;
          width: 100%;
        }
        .db-build-container-inner {
          display: flex;
          flex-direction: column;
          min-height: 100%;
        }
      `;

      this._shadowRoot.appendChild(style);
      this._shadowRoot.appendChild(container);

      // Clear the light DOM content (now in shadow)
      this.innerHTML = '';

      // Process HTMX inside shadow DOM
      if (typeof htmx !== 'undefined') {
        htmx.process(this._shadowRoot);
      }

      // Re-initialize Drupal behaviors in shadow DOM
      if (Drupal.attachBehaviors) {
        Drupal.attachBehaviors(this._shadowRoot, Drupal.settings || drupalSettings);
      }
    }

    /**
     * Get the shadow root for external access.
     */
    get shadowContainer() {
      return this._shadowRoot?.querySelector('.db-build-container-inner');
    }
  }

  // Register the custom element
  if (!customElements.get('db-build-container')) {
    customElements.define('db-build-container', DbBuildContainer);
  }

})(Drupal);

