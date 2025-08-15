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
  id: 'local_tasks',
  label: new TranslatableMarkup('[Page] Local tasks'),
  description: new TranslatableMarkup('The Drupal tabs `local tasks` block (local_tasks_block).'),
  prop_types: ['slot'],
  tags: [],
  context_requirements: ['page'],
  context_definitions: []
)]
class LocalTasksSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $this->setSettings([
      'plugin_id' => 'local_tasks_block',
      'local_tasks_block' => [
        'id' => 'local_tasks_block',
        'label' => 'Tabs',
        'label_display' => '',
        'provider' => 'system',
        'primary' => TRUE,
        'secondary' => TRUE,
      ],
    ]);

    $configuration = $this->getSetting('local_tasks_block') ?? [];
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = \Drupal::service('plugin.manager.block')->createInstance('local_tasks_block', $configuration);

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
