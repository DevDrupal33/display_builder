<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_views\Plugin\ViewsUiPatternsSourceBase;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\views\ViewExecutable;

/**
 * Plugin implementation of the source for views.
 */
#[Source(
  id: 'view_more',
  label: new TranslatableMarkup('[View] More'),
  prop_types: ['slot'],
  tags: ['views'],
  context_requirements: ['views:style'],
  context_definitions: [
    'ui_patterns_views:view_entity' => new EntityContextDefinition('entity:view'),
    'display' => new ContextDefinition('string'),
  ],
)]
class ViewMoreSource extends ViewsUiPatternsSourceBase {

  /**
   * The form key holding the "more link" display options.
   */
  private const OPTIONS_KEY = 'more_options';

  /**
   * {@inheritdoc}
   *
   * The more link is not backed by a views plugin: it is three options of the
   * display itself, which core builds under its "use_more" section.
   *
   * @see \Drupal\views\Plugin\views\display\DisplayPluginBase::buildOptionsForm()
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $display = $this->getViewDisplay();

    if ($display === NULL) {
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
  protected function renderFromView(ViewExecutable $view): mixed {
    return $view->getDisplay()->renderMoreLink();
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptionsKey(): ?string {
    return self::OPTIONS_KEY;
  }

}
