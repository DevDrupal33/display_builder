<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Help button island plugin implementation.
 */
#[Island(
  id: 'help',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Help'),
  description: new TranslatableMarkup('Information about the available keyboard shortcuts.'),
  type: IslandType::Button,
  region: 'end',
)]
class HelpButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    // The tooltip is a placeholder: keyboard.js swaps its content for the
    // shortcut list on sl-show. It would be the accessible name of an
    // icon-only button, so the icon carries a real one instead.
    // @see components/display_builder/js/keyboard.js
    $button = $this->buildButton('', 'help', 'question-circle', '...');
    $button['#props']['icon_label'] = $this->t('Help');

    return $button;
  }

}
