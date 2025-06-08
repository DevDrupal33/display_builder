<?php

declare(strict_types=1);

namespace Drupal\ui_patterns_overrides\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource;

/**
 * Plugin implementation of the source.
 *
 * The plugin_id is set manually for the BlockSource to process.
 */
#[Source(
  id: 'local_actions',
  label: new TranslatableMarkup('Primary admin actions'),
  description: new TranslatableMarkup('The Drupal admin actions `local actions` block (local_actions_block).'),
  prop_types: ['slot'],
  tags: [],
  context_requirements: ['is_display_builder_page_layout'],
  context_definitions: []
)]
class LocalActionsSource extends BlockSource {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $this->setSettings([
      'plugin_id' => 'local_actions_block',
      'local_actions_block' => [
        'id' => 'local_actions_block',
        'label' => 'Primary admin actions',
        'label_display' => '',
        'provider' => 'system',
      ],
    ]);

    return parent::getPropValue();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    // Do not pick config from parent source as we force a block id.
    return $form;
  }

}
