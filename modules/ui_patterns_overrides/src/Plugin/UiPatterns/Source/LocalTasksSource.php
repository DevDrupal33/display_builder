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
  id: 'local_tasks',
  label: new TranslatableMarkup('Tabs (Local tasks)'),
  description: new TranslatableMarkup('The Drupal tabs `local tasks` block (local_tasks_block).'),
  prop_types: ['slot'],
  tags: [],
  context_requirements: ['is_display_builder_page_layout'],
  context_definitions: []
)]
class LocalTasksSource extends BlockSource {

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
