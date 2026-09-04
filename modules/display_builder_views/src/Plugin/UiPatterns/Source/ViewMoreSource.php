<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\EmptyPlaceholderHelpInterface;
use Drupal\display_builder\SourceProcessingDataInterface;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewMoreSource as UiPatternsViewMoreSource;

/**
 * The more link of a view display, in a builder, with its three options.
 *
 * The more link is three display options, not a views plugin, so the form is
 * built here rather than borrowed from a plugin.
 *
 * @see \Drupal\display_builder_views\Hook\DisplayBuilderViewsHook::sourceInfoAlter()
 */
class ViewMoreSource extends UiPatternsViewMoreSource implements EmptyPlaceholderHelpInterface, SourceProcessingDataInterface {

  use ViewsBuilderSourceTrait;

  /**
   * The form key holding the more link options.
   */
  protected const OPTIONS_KEY = 'more_options';

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $display = $this->getViewDisplay();

    if ($display === NULL || !$this->inBuilder()) {
      return $form;
    }
    $form[self::OPTIONS_KEY] = [
      '#tree' => TRUE,
      'use_more' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Create more link'),
        '#description' => $this->t('Adds a link to the display the "Link display" setting points at.'),
        '#default_value' => (bool) $display->getOption('use_more'),
      ],
      'use_more_always' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Always display the more link'),
        '#description' => $this->t('Display the more link even if there are no more items to display.'),
        '#default_value' => (bool) $display->getOption('use_more_always'),
      ],
      'use_more_text' => [
        '#type' => 'textfield',
        '#title' => $this->t('More link text'),
        '#default_value' => $display->getOption('use_more_text'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function processFormData(array $data, FormStateInterface $form_state): array {
    $display = $this->getViewDisplay();
    $values = $form_state->getValue(self::OPTIONS_KEY);

    if ($display === NULL || !\is_array($values)) {
      return $data;
    }
    $display->setOption('use_more', (int) $values['use_more']);
    $display->setOption('use_more_always', (int) $values['use_more_always']);
    $display->setOption('use_more_text', (string) $values['use_more_text']);
    $this->saveView();
    // The options belong to the view, not to the source tree.
    unset($data[self::OPTIONS_KEY]);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptionsKey(): ?string {
    return self::OPTIONS_KEY;
  }

}
