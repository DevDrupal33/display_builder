<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Defines an export display builder instance form.
 */
final class ExportForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL): array {
    $data = $this->stateManager->getCurrentState($builder_id);
    self::cleanInstanceId($data);

    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Export data'),
      '#rows' => '20',
      '#default_value' => Yaml::encode($data),
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
    $form_state->setRedirectUrl(
      new Url(
        'display_builder_devel.collection',
      )
    );
  }

  /**
   * Recursively regenerate the _instance_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function cleanInstanceId(array &$array): void {
    unset($array['_instance_id']);

    foreach ($array as $key => &$value) {
      if (\is_array($value)) {
        self::cleanInstanceId($value);

        if (isset($value['source_id'], $value['source']['value']) && empty($value['source']['value'])) {
          unset($array[$key]);
        }
      }
    }
  }

}
