<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for the display_builder_views module.
 */
class PreprocessViewsView {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire('@ui_patterns.component_element_builder')]
    protected ComponentElementBuilder $componentElementBuilder,
    #[Autowire('@plugin.manager.display_buildable')]
    private DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for 'views_view'.
   *
   * @param array $variables
   *   An associative array containing the variables to pass to the template.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'];
    $extenders = $view->getDisplay()->getExtenders();

    if (!isset($extenders['display_builder'])) {
      return;
    }

    $extender = $extenders['display_builder'];
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('view_display', ['extender' => $extender]);
    $sources = $buildable->getSources();

    // We fallback on normal View if Display Builder is empty or disabled!
    if (empty($sources) || !$buildable->getProfile()) {
      return;
    }

    // Inject the view in context to be available by our UI Patterns source
    // plugins.
    $contexts = [];
    $view_entity = $this->entityTypeManager->getStorage('view')->load($view->id());
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($view_entity);
    $contexts['ui_patterns_views:view'] = new Context(new ContextDefinition('any'), $view);
    $contexts['ui_patterns_views:rows'] = new Context(new ContextDefinition('any'), $variables['rows'] ?? []);
    // @todo pass all variables for each source, find a way to do it sooner than
    // in this preprocess if possible.
    $contexts['ui_patterns_views:variables'] = new Context(new ContextDefinition('any'), $variables);

    $fake_build = [];

    foreach ($sources as $source) {
      $fake_build = $this->componentElementBuilder->buildSource($fake_build, 'content', [], $source, $contexts);
    }

    // Init the variable to render in views-view.html.twig.
    // @see \Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender::preExecute()
    $variables['content'] = $fake_build['#slots']['content'] ?? [];
    $variables['content']['#cache'] = $fake_build['#cache'] ?? [];
  }

}
