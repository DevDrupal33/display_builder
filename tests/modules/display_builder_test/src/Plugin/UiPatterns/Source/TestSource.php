<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Test source plugin.
 */
#[Source(
  id: 'test_source',
  label: new TranslatableMarkup('Test Empty'),
  description: new TranslatableMarkup('A test source plugin that do nothing.'),
  prop_types: ['string'],
)]
class TestSource extends SourcePluginBase {

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
    // Required by SourcePluginBase.
    return NULL;
  }

}
