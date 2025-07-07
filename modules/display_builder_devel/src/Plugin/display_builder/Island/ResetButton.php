<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder_devel\Form\ImportForm;

/**
 * State buttons island plugin implementation.
 */
#[Island(
  id: 'reset',
  label: new TranslatableMarkup('Reset'),
  description: new TranslatableMarkup('Buttons to completely reset the content on the builder with a fixture.'),
  type: IslandType::Button,
)]
class ResetButton extends IslandPluginBase implements IslandWithFormInterface {

  use IslandPluginFormTrait;

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    /** @var \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch */
    $currentRouteMatch = \Drupal::service('current_route_match');
    $form = \Drupal::formBuilder()->getForm(
      static::getFormClass(),
      $builder_id,
      $currentRouteMatch->getRouteName(),
      $currentRouteMatch->getParameters(),
    );
    unset($form['cancel']);

    $button = $this->buildButton('Reset', $this->t('Reset'));
    $button['#props']['variant'] = 'warning';
    $button['#attributes']['outline'] = TRUE;

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#props' => [
        'placement' => 'bottom',
      ],
      '#slots' => [
        'button' => $button,
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['db-background', 'db-devel-reset'],
          ],
          'content' => $form,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(string $builder_id, array $data): array {
    return $this->reloadWithLocalData($builder_id, $data, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public static function getFormClass(): string {
    return ImportForm::class;
  }

}
