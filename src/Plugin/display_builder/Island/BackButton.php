<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\IslandType;

/**
 * Help parent link island plugin implementation.
 */
#[Island(
  id: 'back',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Back'),
  description: new TranslatableMarkup('Exit the display builder and go back to admin UI.'),
  type: IslandType::Button,
)]
class BackButton extends IslandPluginToolbarButtonConfigurationBase {

  /**
   * {@inheritdoc}
   */
  public function hasButtons(): array {
    return [
      'back' => ['label' => FALSE, 'icon' => TRUE],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $url = self::findParentDisplayFromId((string) $builder->id());

    if (!$url) {
      return [];
    }

    $button = $this->buildButton(
      ($this->showLabel('back')) ? $this->t('Back') : '',
      'back',
      $this->showIcon('back') ? 'box-arrow-up-right' : '',
      $this->t('Exit without losing any data.'),
    );
    $button['#attributes']['href'] = $url->toString();

    return $button;
  }

  /**
   * Simply determine the parent display type from id.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return \Drupal\Core\Url|null
   *   The url of the instance.
   */
  private static function findParentDisplayFromId(string $instance_id): ?Url {
    // phpcs:ignore-next-line Drupal.DeprecatedFunctions.GlobalFunction
    $providers = \Drupal::moduleHandler()->invokeAll('display_builder_provider_info');

    foreach ($providers as $provider) {
      if (\str_starts_with($instance_id, $provider['prefix'])) {
        return $provider['class']::getDisplayUrlFromInstanceId($instance_id);
      }
    }

    return NULL;
  }

}
