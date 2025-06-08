<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\Core\Entity\EntityInterface;

/**
 * Hook implementations for display_builder_devel.
 */
class DisplayBuilderDevelHooks {

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('user_insert')]
  public function userInsert(EntityInterface $entity): void {
    $builder_id = \sprintf('demo_%s', uniqid());
    $builder_data = DisplayBuilderHelpers::getFixtureDataFromModule('display_builder_devel', '', 'ui_suite_bootstrap_demo');
    /** @var \Drupal\display_builder\StateManager\StateManagerInterface $stateManager */
    $stateManager = \Drupal::service('display_builder.state_manager');
    // Create a display builder.
    $stateManager->create(
      $builder_id,
      'demo',
      $builder_data,
      [],
    );

    // Associate this display builder to this user.
    \Drupal::state()->set(\sprintf('db_demo_user_%s', $entity->id()), $builder_id);
  }

}
