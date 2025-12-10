/**
 * @param Drupal
 * @param once
 * @file
 * Specific builder search for the display builder descriptions.
 */

((Drupal, once) => {
  /**
   * Drupal behavior for display builder form description.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the behavior.
   *
   * @listens shoelace:sl-input
   */
  Drupal.behaviors.builderFormDescriptionBehaviors = {
    attach(context) {
      once('dbInstanceDescription', '.db-description-toggle', context).forEach(
        (button) => {
          const container = button.closest('.db-instance-description');
          const state = Drupal.displayBuilder.LocalStorageManager.get(
            'displayBuilder',
            'description',
            true,
          );

          if (state) {
            button.textContent = Drupal.t('Hide description');
            container.classList.remove('db-description-hide');
          } else {
            button.textContent = Drupal.t('Show description');
            container.classList.add('db-description-hide');
          }

          button.addEventListener('click', () => {
            const current = Drupal.displayBuilder.LocalStorageManager.get(
              'displayBuilder',
              'description',
              true,
            );
            const next = !current;
            Drupal.displayBuilder.LocalStorageManager.set(
              'displayBuilder',
              'description',
              next,
            );

            if (next) {
              container.classList.remove('db-description-hide');
              button.textContent = Drupal.t('Hide description');
            } else {
              container.classList.add('db-description-hide');
              button.textContent = Drupal.t('Show description');
            }
          });
        },
      );
    },
  };
})(Drupal, once);
