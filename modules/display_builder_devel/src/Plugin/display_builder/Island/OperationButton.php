<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\display_builder_devel\Helper\DisplayBuilderDevelHelper;

/**
 * State buttons island plugin implementation.
 */
#[Island(
  id: 'operation',
  label: new TranslatableMarkup('Operations'),
  description: new TranslatableMarkup('Buttons to provide some direct operations.'),
  type: IslandType::Button,
)]
class OperationButton extends IslandPluginBase {

  use RenderableBuilderTrait;

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $button = $this->buildButton($this->t('Operations'), $this->t('Operations'));
    $button['#props']['variant'] = 'neutral';
    $button['#attributes']['outline'] = TRUE;

    // @phpstan-ignore-next-line
    $current_route = Url::FromRoute(\Drupal::routeMatch()->getRouteName(), ['builder_id' => $builder_id])->toString();
    $items = [];

    foreach (DisplayBuilderDevelHelper::getOperationLinks($builder_id) as $item) {
      /** @var \Drupal\Core\Url $url */
      $url = $item['url'];
      $url->setOption('query', ['destination' => $current_route]);
      $items[] = [
        'title' => $item['title'],
        'url' => $url,
      ];
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#props' => [
        'placement' => 'bottom',
      ],
      '#slots' => [
        'button' => $button,
        'content' => [
          '#type' => 'component',
          '#component' => 'display_builder:menu',
          '#props' => [
            'items' => $items,
          ],
          '#attributes' => [
            'class' => ['db-background'],
          ],
        ],
      ],
    ];
  }

}
