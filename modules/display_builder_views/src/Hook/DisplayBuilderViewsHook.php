<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay;
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
   * Implements hook_entity_operation_alter().
   *
   * @param array $operations
   *   An associative array of operations.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the operations are being altered.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if (!$entity instanceof InstanceInterface) {
      return;
    }

    $id = (string) $entity->id();

    if (\str_starts_with($id, ViewDisplay::getPrefix())) {
      $operations['build'] = [
        'title' => new TranslatableMarkup('Build display'),
        'url' => ViewDisplay::getUrlFromInstanceId($id),
        'weight' => -1,
      ];
      $operations['edit'] = [
        'title' => new TranslatableMarkup('Edit view'),
        'url' => ViewDisplay::getDisplayUrlFromInstanceId($id),
        'weight' => 10,
      ];
    }
  }

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
        $log = new TranslatableMarkup('Synced from configuration import.');
        $instance->setNewPresent($sources, $log);
        $instance->save();
      }
    }
  }

}
