<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Test source plugin with required and optional context definitions.
 */
#[Source(
  id: 'test_context_source',
  label: new TranslatableMarkup('Test Context Source'),
  description: new TranslatableMarkup('A test source plugin that declares context definitions.'),
  prop_types: ['string'],
  context_definitions: [
    'entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Entity'), required: TRUE),
    'optional_entity' => new ContextDefinition('entity', label: new TranslatableMarkup('Optional Entity'), required: FALSE),
  ],
)]
class TestContextSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue(?PropTypeInterface $prop_type = NULL): mixed {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    return NULL;
  }

}
