<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\TopBarItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\navigation\TopBarItemBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display Builder toolbar base class.
 */
abstract class ToolbarBase extends TopBarItemBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(RouteMatchInterface::class),
      $container->get(EntityTypeManagerInterface::class),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];
    $buildable = $this->getDisplayBuildable();

    if (!$buildable) {
      return $build;
    }

    if ($profile = $buildable->getProfile()) {
      $build['#cache']['tags'] = $profile->getCacheTags();
    }
    $toolbar = $this->buildToolbar($buildable);

    if (empty($toolbar)) {
      return $build;
    }

    /** @var array{region: \Drupal\navigation\TopBarRegion} $pluginDefinition */
    $pluginDefinition = $this->getPluginDefinition();
    $build += $toolbar[$pluginDefinition['region']->value] ?? [];

    return $build;
  }

  /**
   * Get display buildable.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   An entity or a plugin with a buildable display.
   */
  protected function getDisplayBuildable(): ?DisplayBuildableInterface {
    $route = $this->routeMatch->getRouteName();

    if ($route === NULL) {
      return NULL;
    }

    $routeParameters = $this->routeMatch->getParameters();
    $providers = $this->moduleHandler->invokeAll('display_builder_provider_info');

    foreach ($providers as $provider) {
      if ($buildable = $provider['class']::createFromRoute($route, $routeParameters)) {
        return $buildable;
      }
    }

    return NULL;
  }

  /**
   * Build toolbar.
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   An entity or a plugin with a buildable display.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildToolbar(DisplayBuildableInterface $buildable): array {
    if (!$buildable->getProfile()) {
      // Display Builder is not activated for this display buildable.
      return [];
    }

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($buildable->getInstanceId());

    if (!$instance) {
      // Display Builder instance was not created yet, or was deleted, for this
      // entity.
      return [];
    }

    $contexts = $instance->getContexts() ?? [];
    /** @var \Drupal\display_builder\ProfileViewBuilder $view_builder */
    $view_builder = $this->entityTypeManager->getViewBuilder('display_builder_profile');

    return $view_builder->buildToolbar($instance, $contexts);
  }

}
