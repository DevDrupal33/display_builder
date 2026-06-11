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

}
