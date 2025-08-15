<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Plugin implementation of the source.
 *
 * The plugin_id is set manually for the BlockSource to process.
 */
#[Source(
  id: 'local_actions',
  label: new TranslatableMarkup('[Page] Local actions'),
  description: new TranslatableMarkup('The Drupal admin actions `local actions` block (local_actions_block).'),
  prop_types: ['slot'],
  tags: [],
  context_requirements: ['page'],
  context_definitions: []
)]
class LocalActionsSource extends SourcePluginBase {

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

    $configuration = $this->getSetting('local_actions_block') ?? [];
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = \Drupal::service('plugin.manager.block')->createInstance('local_actions_block', $configuration);

    return $block->build();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    // Do not pick config from parent source as we force a block id.
    return $form;
  }

}
