<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Test source plugin that implements getGroup().
 *
 * This plugin is used to verify that the PatternPreset entity correctly
 * lazy-loads the group from a source plugin when the entity's group property
 * is not set.
 */
#[Source(
  id: 'test_group_source',
  label: new TranslatableMarkup('Test Group Source'),
  description: new TranslatableMarkup('A test source plugin that provides a group name.'),
  prop_types: [
    'slot',
  ],
)]
class TestGroupSource extends SourcePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue(?PropTypeInterface $prop_type = NULL): mixed {
    // This is a dummy implementation for testing purposes.
    return 'test value';
  }

  /**
   * Get the group name for this source plugin.
   *
   * This method will be implemented in Drupal\ui_patterns\SourceInterface.
   *
   * @return string
   *   The name of the group.
   */
  public function getGroup(): string {
    return 'Test Source Group';
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    // Required by SourcePluginBase.
    return NULL;
  }

}
