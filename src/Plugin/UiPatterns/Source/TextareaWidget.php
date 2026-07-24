<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginPropValueWidget;
use Drupal\ui_patterns\SourceTags;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source for a simple editor.
 *
 * @toto add calculateDependencies() to the format id or find a better
 * filter_format integration.
 */
#[Source(
  id: 'textarea',
  label: new TranslatableMarkup('Textarea'),
  description: new TranslatableMarkup('Multi-line text field.'),
  prop_types: ['string', 'identifier'],
  tags: [SourceTags::Widget->value]
)]
class TextareaWidget extends SourcePluginPropValueWidget {

  public const FILTER_FORMAT_ID = 'display_builder_html';

  /**
   * The filter format repository.
   *
   * @var \Drupal\filter\FilterFormatRepositoryInterface
   */
  protected $filterFormat;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $plugin = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $plugin->filterFormat = $container->get(FilterFormatRepositoryInterface::class);

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $attributes = ['data-db-ckeditor' => 'true'];
    $libraries = ['display_builder/textarea_ckeditor'];

    // Soft integration: when ui_icons_ckeditor5 is enabled, also load its
    // CKEditor plugin bundle so window.CKEditor5.icon is available. The editor
    // JS (textarea_ckeditor.js) picks it up on its own to preserve and preview
    // <drupal-icon> markup; without the module the bundle is simply absent and
    // the editor loads unchanged. The data-db-ckeditor-icon flag tells the JS
    // this instance must wait for that separate bundle to finish loading
    // before creating the editor - it evaluates independently of the core
    // CKEditor 5 DLL builds, so without the flag the editor can be created
    // before it lands and silently omit the plugin.
    if ($this->moduleHandler->moduleExists('ui_icons_ckeditor5')) {
      $libraries[] = 'ui_icons_ckeditor5/icon';
      $attributes['data-db-ckeditor-icon'] = 'true';
    }

    $form['value'] = [
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('value'),
      '#attributes' => $attributes,
      '#attached' => ['library' => $libraries],
    ];

    $this->addRequired($form['value']);
    $this->addTokenTreeLink($form, 'help');

    // @todo allow when we can really use it as currently it does not copy in
    // CKEditor.
    if (isset($form['help'])) {
      $form['help']['#click_insert'] = FALSE;
    }

    if (!isset($form['value']['#title'])) {
      $form['value']['#title'] = $this->propDefinition['title'] ?? $this->propId;
    }

    // If our format is disabled or not usable, skip the editor.
    if ($this->getFormatId() !== self::FILTER_FORMAT_ID) {
      unset($form['value']['#attributes']['data-db-ckeditor']);
      $form['value']['#description_display'] = 'before';
      // @todo add doc link to this problem.
      $form['value']['#description'] = $this->t('<mark>Warning</mark>: Display Builder filter format is disable or missing, HTML markup will be escaped.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Unlike Textfield, this widget's value is allowed to contain HTML: it
   * is run through the forced 'display_builder_html' text format (see
   * config/optional/filter.format.display_builder_html.yml) via
   * #type => 'processed_text', never #type => 'text_format' — the latter
   * renders Drupal's format selector and attaches its editor library,
   * which this widget must not do. The processed_text element is rendered
   * eagerly here into a Markup object, rather than returned as a render
   * array: 'string'-tagged sources can also be used to fill a 'slot' prop
   * through SlotPropType's string-to-slot conversion path, which only
   * trusts a MarkupInterface value (anything else is treated as plain text
   * and passed to #plain_text, which requires a string and TypeErrors on
   * an array). Returning Markup here keeps both paths - direct string/
   * identifier props and the slot conversion - working, and StringPropType/
   * IdentifierPropType still treat it as trusted so it is not re-escaped.
   */
  public function getPropValue(): mixed {
    $value = parent::getPropValue();

    if (empty($value)) {
      return $value;
    }

    $value = $this->replaceTokens($value, TRUE);

    $html = $this->normalizer->convertToString([
      '#type' => 'processed_text',
      '#text' => $value,
      '#format' => $this->getFormatId(),
    ]);

    return Markup::create($html);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $text = \reset($summary);

    return [
      \html_entity_decode((string) $text),
    ];
  }

  /**
   * Get format id for our editor.
   */
  private function getFormatId(): string {
    $format_id = self::FILTER_FORMAT_ID;
    $formats = $this->filterFormat->getAllFormats();

    if (!isset($formats[$format_id])) {
      $format_id = $this->filterFormat->getFallbackFormatId();
    }

    return $format_id;
  }

}
