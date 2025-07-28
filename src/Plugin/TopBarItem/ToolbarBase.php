<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\TopBarItem;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\navigation\TopBarItemBase;
use Drupal\views\ViewEntityInterface;
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

    $entity = NULL;
    $routeName = $this->routeMatch->getRouteName();

    if ($routeName === NULL) {
      return $build;
    }

    if ($routeName === 'display_builder_views.views.manage') {
      $entity = $this->getDisplayEntityFromViews();
    }
    elseif (\str_starts_with($routeName, 'display_builder_entity_view.')) {
      $entity = $this->getDisplayEntityFromEntityView();
    }
    // Fallback or new non-core case. Loop on all route parameters and check if
    // one is an object implementing DisplayBuildableInterface.
    else {
      $routeParameters = $this->routeMatch->getParameters();

      foreach ($routeParameters as $parameter) {
        if ($parameter instanceof DisplayBuildableInterface) {
          $entity = $parameter;

          break;
        }
      }
    }

    if (!($entity instanceof DisplayBuildableInterface)) {
      return $build;
    }

    $toolbar = $this->buildToolbar($entity);

    if (empty($toolbar)) {
      return $build;
    }

    /** @var array{region: \Drupal\navigation\TopBarRegion} $pluginDefinition */
    $pluginDefinition = $this->getPluginDefinition();
    $build += $toolbar[$pluginDefinition['region']->value] ?? [];

    return $build;
  }

  /**
   * Get display entity from entity view.
   */
  protected function getDisplayEntityFromEntityView(): ?DisplayBuildableInterface {
    $entity_type_id = $this->routeMatch->getParameter('entity_type_id');
    $bundle = $this->routeMatch->getParameter('bundle');
    $view_mode = $this->routeMatch->getParameter('view_mode_name');

    if ($entity_type_id === NULL || $bundle === NULL || $view_mode === NULL) {
      return NULL;
    }

    $display_id = "{$entity_type_id}.{$bundle}.{$view_mode}";
    $storage = $this->entityTypeManager->getStorage('entity_view_display');

    $entity_display = $storage->load($display_id);

    if (!($entity_display instanceof DisplayBuildableInterface)) {
      return NULL;
    }

    return $entity_display;
  }

  /**
   * Get display entity from views.
   */
  protected function getDisplayEntityFromViews(): ?DisplayBuildableInterface {
    $display = $this->routeMatch->getParameter('display');

    if (!\is_string($display)) {
      return NULL;
    }

    $view = $this->routeMatch->getParameter('view');

    if (!($view instanceof ViewEntityInterface)) {
      return NULL;
    }

    $view = $view->getExecutable();
    $view->setDisplay($display);
    $extenders = $view->getDisplay()->getExtenders();

    if (!isset($extenders['display_builder']) || !($extenders['display_builder'] instanceof DisplayBuildableInterface)) {
      return NULL;
    }

    return $extenders['display_builder'];
  }

  /**
   * Build toolbar.
   */
  protected function buildToolbar(DisplayBuildableInterface $entity): array {
    $profile = $entity->getProfile();

    if (!$profile) {
      // Display Builder profile is not activated for this entity. This is not
      // supposed to happen because Display Builder is mandatory.
      return [];
    }

    $instanceId = $entity->getInstanceId();

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($instanceId);

    if (!$instance) {
      // Display Builder instance was not created yet, or was deleted, for this
      // entity.
      return [];
    }

    $contexts = $instance->getContexts() ?? [];

    $view_builder = $this->entityTypeManager->getViewBuilder('display_builder_profile');

    return $view_builder->buildToolbar($instance, $contexts);
  }

}
