<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Hook;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface;

/**
 * Hook implementations to override entity template.
 */
class TemplateOverride {

  /**
   * Key to store info for template override.
   */
  public const KEY = 'display_builder_entity_view';

  public function __construct(
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_entity_view().
   *
   * @param array $build
   *   The renderable array representing the entity content.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being viewed.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display configuration entity to be used to build the entity.
   * @param string $view_mode
   *   The view mode in which the entity is being viewed.
   *
   * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$display instanceof DisplayBuilderEntityDisplayInterface) {
      return;
    }

    // @todo Is isDisplayBuilderEnabled enough?
    $build['#' . $this::KEY] = $display->isDisplayBuilderEnabled();
  }

  /**
   * Implements hook_theme_suggestions_alter().
   *
   * @param array $suggestions
   *   An array of theme hook suggestions.
   * @param array $variables
   *   An array of variables to pass to the theme function or template.
   * @param string $hook
   *   The name of the theme hook being invoked.
   */
  #[Hook('theme_suggestions_alter', order: Order::Last)]
  public function suggestionsAlter(array &$suggestions, array $variables, string $hook): void {
    $key = '#' . $this::KEY;

    if (!isset($variables['elements'][$key]) || empty($variables['elements'][$key])) {
      return;
    }

    if (isset($variables['theme_hook_original'])) {
      // CommentViewBuilder overrides #theme to a compound suggestion like
      // 'comment__FIELD_NAME__CONTENT_BUNDLE'.
      // Extract the base entity type ID (first segment) so the registered
      // suggestion 'comment__display_builder' is always used.
      $base_hook = explode('__', $variables['theme_hook_original'])[0];
      $suggestions[] = $base_hook . '__display_builder';
    }
  }

  /**
   * Implements hook_theme_registry_alter().
   *
   * Add entry for display builder suggestion.
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(array &$theme_registry): void {
    $modulePath = $this->moduleExtensionList->getPath('display_builder_entity_view');
    $templatePath = $modulePath . '/templates';
    $entityDefinitions = $this->entityTypeManager->getDefinitions();

    foreach ($entityDefinitions as $entityTypeId => $entityType) {
      if (!$entityType instanceof ContentEntityTypeInterface) {
        continue;
      }

      if (!isset($theme_registry[$entityTypeId])) {
        continue;
      }

      $theme_registry[$entityTypeId . '__display_builder'] = [
        // @todo Does the base hook always match the entity type ID?
        'base hook' => $entityTypeId,
        'path' => $templatePath,
        'preprocess functions' => ['display_builder_entity_view_preprocess_entity'],
        'render element' => 'elements',
        'template' => 'entity',
        'theme path' => $modulePath,
        'type' => 'base_theme_engine',
      ];
    }
  }

}
