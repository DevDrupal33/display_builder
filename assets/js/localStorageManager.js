/**
 * @file
 * Simple local storage manager.
 */

Drupal.displayBuilder = Drupal.displayBuilder || {};

/**
 * Simple localStorage utility functions to handle multiple key-value pairs
 * within a single localStorage entry.
 */
Drupal.displayBuilder.LocalStorageManager = class {
  /**
   * Gets a value from localStorage for a specific key within a namespace.
   *
   * @param {string} namespace - The namespace (main key) in localStorage.
   * @param {string} key - The specific key within the namespace.
   * @param {mixed|null} defaultValue - Default to return of not found.
   * @return {any|null} The value associated with the key, or null if not found.
   */
  static get(namespace, key, defaultValue = null) {
    const prefixedNamespace = `Drupal.${namespace}`;
    try {
      const storageString = localStorage.getItem(prefixedNamespace);
      if (!storageString) {
        return defaultValue;
      }

      const storage = JSON.parse(storageString);
      return storage[key] !== undefined ? storage[key] : defaultValue;
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error(
        `Error getting from localStorage (${prefixedNamespace}.${key}):`,
        error,
      );
      return defaultValue;
    }
  }

  /**
   * Sets a value in localStorage for a specific key within a namespace.
   *
   * @param {string} namespace - The namespace (main key) in localStorage.
   * @param {string} key - The specific key within the namespace.
   * @param {any} value - The value to store.
   */
  static set(namespace, key, value) {
    const prefixedNamespace = `Drupal.${namespace}`;
    try {
      const storageString = localStorage.getItem(prefixedNamespace);
      const storage = storageString ? JSON.parse(storageString) : {};

      storage[key] = value;
      localStorage.setItem(prefixedNamespace, JSON.stringify(storage));
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error(
        `Error setting in localStorage (${prefixedNamespace}.${key}):`,
        error,
      );
    }
  }

  /**
   * Removes a specific key-value pair from a namespace in localStorage.
   *
   * @param {string} namespace - The namespace (main key) in localStorage.
   * @param {string} key - The specific key to remove.
   */
  static remove(namespace, key) {
    const prefixedNamespace = `Drupal.${namespace}`;
    try {
      const storageString = localStorage.getItem(prefixedNamespace);
      if (!storageString) {
        return; // Nothing to remove
      }

      const storage = JSON.parse(storageString);
      if (storage.hasOwnProperty(key)) {
        delete storage[key];
        localStorage.setItem(prefixedNamespace, JSON.stringify(storage));
      }
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error(
        `Error removing from localStorage (${prefixedNamespace}.${key}):`,
        error,
      );
    }
  }

  /**
   * Clears all data within a specific namespace in localStorage.
   *
   * @param {string} namespace - The namespace (main key) to clear.
   */
  static clearNamespace(namespace) {
    const prefixedNamespace = `Drupal.${namespace}`;
    try {
      localStorage.removeItem(prefixedNamespace);
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error(
        `Error clearing namespace in localStorage (${prefixedNamespace}):`,
        error,
      );
    }
  }
};
