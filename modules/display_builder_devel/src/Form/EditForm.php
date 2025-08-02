<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_devel\MockEntity;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Defines an edit display builder instance form.
 */
final class EditForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected ConfigFormBuilderInterface $configFormBuilder,
    protected StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL, ?string $routeName = NULL, ?ParameterBag $routeParameters = NULL): array {
    // @todo have a way for saving.
    if (\str_starts_with($builder_id, 'page_layout__') || \str_starts_with($builder_id, 'view__') || \str_starts_with($builder_id, 'entity_view__')) {
      return [
        '#markup' => $this->t('This configuration must be managed directly from the entity type.'),
      ];
    }

    if (!$routeName) {
      $routeName = 'display_builder_devel.view';
      $routeParameters = ['builder_id' => $builder_id];
    }

    $display_builder = new MockEntity($builder_id, 'default', []);
    $form[ConfigFormBuilderInterface::PROFILE_PROPERTY] = $this->configFormBuilder->build($display_builder);
    unset($form[ConfigFormBuilderInterface::PROFILE_PROPERTY]['admin_link']);

    $form['builder_id'] = [
      '#type' => 'hidden',
      '#value' => $builder_id,
    ];

    $form['route_name'] = [
      '#type' => 'hidden',
      '#value' => $routeName,
    ];

    if ($routeParameters) {
      $parameters = [];

      foreach ($routeParameters as $key => $parameter) {
        $parameters[$key] = $parameter;
      }
      $form['route_parameters'] = [
        '#type' => 'hidden',
        '#value' => Json::encode($parameters),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => new Url('display_builder_devel.collection'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_devel_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_config_id = $form_state->getValue(ConfigFormBuilderInterface::PROFILE_PROPERTY);
    $builder_id = $form_state->getValue('builder_id');

    $this->stateManager->setEntityConfigId($builder_id, $entity_config_id);

    // phpcs:ignore
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();

    $builder_data = $this->stateManager->getCurrent($builder_id);
    $display_builder = new MockEntity($builder_id, $entity_config_id, $builder_data);
    $display_builder->initInstanceIfMissing();
    $form_state->setRedirectUrl($display_builder->getBuilderUrl());
  }

}
