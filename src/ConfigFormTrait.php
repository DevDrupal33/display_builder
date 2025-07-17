<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Config form trait.
 */
trait ConfigFormTrait {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Build 'Display builder' config entity form.
   *
   * @param ?string $display_builder
   *   The entity ID of a Display builder config entity.
   *
   * @return array
   *   A form renderable array.
   */
  protected function buildConfigForm(?string $display_builder): array {
    /** @var \Drupal\display_builder\DisplayBuilderInterface[] $display_builders */
    $display_builders = $this->entityTypeManager->getStorage('display_builder')->loadMultiple();
    $options = [];
    foreach ($display_builders as $entity_id => $entity) {
      if ($this->currentUser->hasPermission($entity->getPermissionName())) {
        $options[$entity_id] = $entity->label();
      }
    }
    return match (count($options)) {
      // No form input if no display builders.
      0 => [],
      // Hidden form input if only one display builder.
      1 => [
        '#type' => 'hidden',
        '#default_value' => array_keys($options)[0],
      ],
      default => [
        '#type' => 'select',
        '#title' => $this->t('Display builder config'),
        '#options' => $options,
        '#default_value' => $display_builder,
      ]
    };
  }

}
