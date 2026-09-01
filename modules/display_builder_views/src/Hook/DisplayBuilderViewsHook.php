<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\views\Entity\View;

/**
 * Hook implementations for the display_builder_views module.
 */
class DisplayBuilderViewsHook {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * Implements hook_entity_update().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be updated.
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof View) {
      return;
    }

    // Runs during config import.
    if (!$entity->isSyncing()) {
      return;
    }
    $view = $entity->getExecutable();

    foreach ($entity->get('display') as $display_id => $display) {
      $view->setDisplay($display_id);
      $extenders = $view->getDisplay()->getExtenders();

      if (!isset($extenders['display_builder'])) {
        continue;
      }
      $extender = $extenders['display_builder'];
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('view_display', [
        'extender' => $extender,
      ]);

      $sources = $buildable->getSources();
      $instance_id = $buildable->getInstanceId();

      /** @var \Drupal\display_builder\InstanceInterface|null $instance */
      $instance = $this->entityTypeManager
        ->getStorage('display_builder_instance')
        ->load($instance_id);

      // Reset the state once you import a configuration.
      if ($instance) {
        $log = new TranslatableMarkup('Synchronize display from imported configuration');
        $instance->setNewPresent($sources, $log);
        $instance->save();
      }
    }
  }

  /**
   * Implements hook_ui_patterns_source_info_alter().
   *
   * @param array $definitions
   *   An array of all the existing plugin definitions, passed by reference.
   */
  #[Hook('ui_patterns_source_info_alter')]
  public function sourceInfoAlter(array &$definitions): void {
    // Both plugins belong to ui_patterns_views; ours add the display context
    // it does not model yet, and a placeholder for when there is no view to
    // run. Swapping the class rather than adding a plugin keeps one source id
    // per area, so a stored display keeps working either way.
    // @todo Remove once the UI Patterns context system has been revamped,
    // #3608162.
    $definitions['view_rows']['class'] = 'Drupal\display_builder_views\Plugin\UiPatterns\Source\ViewRowsSource';
    $definitions['view_title']['class'] = 'Drupal\display_builder_views\Plugin\UiPatterns\Source\ViewTitleSource';
  }

}
