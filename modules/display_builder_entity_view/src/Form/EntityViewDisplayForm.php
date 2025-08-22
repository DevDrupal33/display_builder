<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\field_ui\Form\EntityViewDisplayEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit form for the entity view display entity type.
 *
 * @internal
 *   Form classes are internal.
 */
final class EntityViewDisplayForm extends EntityViewDisplayEditForm {

  use EntityViewDisplayFormTrait;

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
   * The entity being used by this form.
   *
   * @var \Drupal\display_builder_entity_view\Entity\EntityViewDisplay
   */
  protected $entity;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->configFormBuilder = $container->get('display_builder.config_form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->localTaskManager = $container->get('plugin.manager.menu.local_task');
    $instance->routeBuilder = $container->get('router.builder');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    return $this->entityViewDisplayForm($form);
  }

}
