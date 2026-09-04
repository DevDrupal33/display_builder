/**
 * @file
 * Shared classifier for a successful htmx request's path.
 *
 * Several islands only care about *which* display-builder endpoint an
 * htmx:afterRequest fired for, not its response body, because the server
 * already computed what each outcome looks like at the last render - see
 * assets/js/instances.js and components/save_status/save_status.js. This is
 * the one piece both are identical in shape: match a path against a
 * suffix-to-outcome table. Extracting the path itself from the event stays
 * with each caller, since save_status.js also needs the verb for its own
 * matching once it has it.
 */

((Drupal) => {
  Drupal.displayBuilder = Drupal.displayBuilder || {};

  /**
   * Matches a request path against a suffix lookup.
   *
   * @param {string|null|undefined} path
   *   The request path, or a falsy value for a failed/pathless request.
   * @param {Object<string, *>} table
   *   Outcomes keyed by the path suffix that selects them (e.g. '/publish').
   *
   * @return {*}
   *   The matching table value, or null when there is no path or it matched
   *   no key.
   */
  Drupal.displayBuilder.matchRequestPath = (path, table) => {
    if (!path) {
      return null;
    }

    const suffix = Object.keys(table).find((candidate) =>
      path.endsWith(candidate),
    );
    return suffix ? table[suffix] : null;
  };
})(Drupal);
