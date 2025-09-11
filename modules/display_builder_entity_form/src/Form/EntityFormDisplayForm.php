<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\field_ui\Form\EntityFormDisplayEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Edit form for the entity form display entity type.
 *
 * @internal
 *   Form classes are internal.
 */
final class EntityFormDisplayForm extends EntityFormDisplayEditForm {

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
   * @var \Drupal\display_builder_entity_form\Entity\EntityFormDisplay
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

    $is_display_builder_enabled = $this->entity->isDisplayBuilderEnabled();

    if ($is_display_builder_enabled) {
      // Hide the table of fields.
      $form['fields']['#access'] = FALSE;
      $form['#fields'] = [];
      $form['#extra'] = [];
    }

    $form['manage_display_builder'] = [
      '#type' => 'link',
      '#title' => $this->t('Display builder'),
      '#weight' => -11,
      '#attributes' => ['class' => ['button']],
      '#url' => $this->entity->getBuilderUrl(),
      '#access' => $is_display_builder_enabled,
    ];

    if (isset($form['modes'])) {
      $form['modes']['#weight'] = 0;
    }

    $form['display_builder_wrapper'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Display builder'),
      '#weight' => 1,
    ];

    $form['display_builder_wrapper'][ConfigFormBuilderInterface::PROFILE_PROPERTY] = $this->configFormBuilder->build($this->entity, FALSE);

    return $form;
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    // @todo we should have always a fallback.
    $display_builder_config = $form_state->getValue([ConfigFormBuilderInterface::PROFILE_PROPERTY]) ?? 'default';

    // Empty means disabled.
    if (empty($display_builder_config)) {
      $this->entity->unsetThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY);
    }
    else {
      $this->entity->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY, $display_builder_config);
    }

    $this->entity->save();
    $this->localTaskManager->clearCachedDefinitions();
    $this->routeBuilder->rebuild();
  }

}
