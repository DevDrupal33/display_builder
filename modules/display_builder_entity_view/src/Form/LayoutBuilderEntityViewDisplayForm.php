<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\layout_builder\Form\LayoutBuilderEntityViewDisplayForm as CoreLayoutBuilderEntityViewDisplayForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit form for the entity view display entity type.
 *
 * @internal
 *   Form classes are internal.
 */
final class LayoutBuilderEntityViewDisplayForm extends CoreLayoutBuilderEntityViewDisplayForm {

  use EntityViewDisplayFormTrait;

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay
   */
  protected $entity;

  /**
   * The config form builder for Display Builder.
   */
  protected ConfigFormBuilderInterface $configFormBuilder;

  /**
   * The local task manager.
   */
  protected LocalTaskManager $localTaskManager;

  /**
   * The router builder.
   */
  protected RouteBuilderInterface $routeBuilder;

  /**
   * The loaded display builder instance.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->configFormBuilder = $container->get('display_builder.config_form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->localTaskManager = $container->get('plugin.manager.menu.local_task');
    $instance->routeBuilder = $container->get('router.builder');
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $form = $this->entityViewDisplayForm($form);

    if (isset($form['layout'])) {
      $form['layout']['#weight'] = 2;
      $form['layout']['#open'] = $this->entity->isLayoutBuilderEnabled();
    }

    return $form;
  }

}
