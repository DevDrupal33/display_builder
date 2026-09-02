<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all component forms.
 */
class EntityOverrideViewLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    $display_infos = EntityViewOverride::getDisplayInfos($this->entityTypeManager);

    foreach ($display_infos as $entity_type_id => $display_info) {
      $base_route = \sprintf('entity.%s.canonical', $entity_type_id);

      // Every overridable display is its own tab, flat alongside View, Edit,
      // Delete, Revisions.
      foreach ($display_info['modes'] as $view_mode => $view_mode_label) {
        $route = \sprintf('entity.%s.display_builder.%s', $entity_type_id, $view_mode);
        $this->derivatives[$route] = [
          'route_name' => $route,
          'base_route' => $base_route,
          'title' => $this->t('Display: :display', [':display' => $view_mode_label]),
          // In-between 'Edit' and 'Delete'.
          'weight' => 10,
        ];
      }
    }

    return $this->derivatives;
  }

}
