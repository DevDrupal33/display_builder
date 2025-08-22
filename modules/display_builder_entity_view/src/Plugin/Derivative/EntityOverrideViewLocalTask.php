<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all component forms.
 */
class EntityOverrideViewLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(protected RouteProviderInterface $routeProvider, protected ComponentPluginManager $componentPluginManager, protected EntityTypeManagerInterface $entityTypeManager, TranslationInterface $stringTranslation) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('router.route_provider'),
      $container->get('plugin.manager.sdc'),
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    $display_infos = EntityViewDisplay::getDisplayInfos($this->entityTypeManager);

    foreach ($display_infos as $entity_type_id => $display_info) {
      $forward = \sprintf('entity.%s.display_builder.forward', $entity_type_id);
      // We add the "Display" tab alongside View, Edit, Delete, Revisions...
      $this->derivatives[$forward] = [
        'route_name' => $forward,
        'base_route' => \sprintf('entity.%s.canonical', $entity_type_id),
        'title' => \count($display_info['modes']) === 1 ? $this->t(':display display', [':display' => \reset($display_info['modes'])]) : $this->t('Display'),
        // In-between 'Edit' and 'Delete'.
        'weight' => 10,
      ];

      // Second level: each tab is a display override.
      $parent_id = \sprintf('display_builder_entity_view.display_builder_tabs:entity.%s.display_builder.forward', $entity_type_id);

      foreach ($display_info['modes'] as $view_mode => $view_mode_label) {
        $route = \sprintf('entity.%s.display_builder.%s', $entity_type_id, $view_mode);
        $this->derivatives[$route] = [
          'route_name' => $route,
          'base_route' => $forward,
          'title' => $view_mode_label,
          'parent_id' => $parent_id,
        ];
      }
    }

    return $this->derivatives;
  }

}
