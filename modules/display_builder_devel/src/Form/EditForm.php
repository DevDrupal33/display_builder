<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormTrait;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Drupal\Component\Serialization\Json;

/**
 * Defines an edit display builder instance form.
 */
final class EditForm extends FormBase {

  use AutowireTrait;

  use ConfigFormTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    private readonly StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL, ?string $routeName = NULL, ?ParameterBag $routeParameters = NULL): array {
    if (!$routeName) {
      $routeName = 'display_builder_devel.view';
      $routeParameters = ['builder_id' => $builder_id];
    }

    $builder_config_id = $this->stateManager->getEntityConfigId($builder_id);
    $form['builder_config_id'] = $this->buildConfigForm($builder_config_id);

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
    $entity_config_id = $form_state->getValue('builder_config_id');
    $builder_id = $form_state->getValue('builder_id');

    $route_name = $form_state->getValue('route_name');
    $route_parameters = $form_state->getValue('route_parameters');
    if ($route_parameters) {
      $route_parameters = Json::decode($route_parameters);
    }
    $route_parameters['builder_id'] = $builder_id;

    $this->stateManager->setEntityConfigId($builder_id, $entity_config_id);

    // phpcs:ignore
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();

    $form_state->setRedirectUrl(
      new Url(
        $route_name,
        $route_parameters,
      )
    );
  }

}
