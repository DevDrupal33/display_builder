<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\StorageProperties;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for the display_builder_views module.
 */
class PreprocessViewsView {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StateManagerInterface $stateManager,
    #[Autowire('@ui_patterns.component_element_builder')] protected ComponentElementBuilder $componentElementBuilder,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for 'views_view'.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'];
    $current_display = $view->getDisplay();
    $extenders = $current_display->getExtenders();

    if (!isset($extenders['display_builder'])) {
      return;
    }

    $options = $extenders['display_builder']->options;
    $builder_config_id = $options[StorageProperties::ConfigEntityId->value] ?? NULL;

    if ($builder_config_id === NULL || empty($builder_config_id)) {
      // @todo something for preview?
      return;
    }

    $display_builder_id = $options[StorageProperties::InstanceId->value] ?? NULL;

    if ($display_builder_id === NULL || empty($display_builder_id)) {
      // @todo something for preview?
      return;
    }

    if ($this->stateManager->load($display_builder_id) === NULL) {
      // @todo if instance is deleted, create a blank one?
      return;
    }

    // Inject the view in context to be available by our UI Patterns source
    // plugins.
    $contexts = [];
    $view_entity = $this->entityTypeManager->getStorage('view')->load($view->id());
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($view_entity);
    $contexts['ui_patterns_views:rows'] = new Context(new ContextDefinition('any'), $variables['rows'] ?? []);
    // @todo pass all variables for each source, find a way to do it sooner than
    // in this preprocess if possible.
    $contexts['ui_patterns_views:variables'] = new Context(new ContextDefinition('any'), $variables);

    $fake_build = [];
    $builder_data = $this->stateManager->getCurrentState($display_builder_id);
    foreach ($builder_data as $source_data) {
      $fake_build = $this->componentElementBuilder->buildSource($fake_build, 'content', [], $source_data, $contexts);
    }

    // Init the variable to render in views-view.html.twig.
    $variables['display_builder'] = $fake_build['#slots']['content'] ?? [];
    $variables['display_builder']['#cache'] = $fake_build['#cache'] ?? [];
  }

}
