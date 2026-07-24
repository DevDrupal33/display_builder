<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Menu island plugin implementation.
 */
#[Island(
  id: 'menu_preset',
  label: new TranslatableMarkup('Preset'),
  description: new TranslatableMarkup('Save as a preset.'),
  type: IslandType::Menu,
)]
class MenuPreset extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();

    $save_preset = $this->buildMenuItem($this->t('Save as preset'), 'save_preset');
    $save_preset = $this->htmxEvents->onClickSavePreset($save_preset, $builder_id, $this->t('Name of preset'));

    return [
      $this->buildMenuDivider(),
      $save_preset,
    ];
  }

}
