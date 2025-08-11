<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Test island plugin for testing purposes.
 */
#[Island(
  id: 'test_island_contextual',
  label: new TranslatableMarkup('Test Island Contextual'),
  description: new TranslatableMarkup('A test island for testing.'),
  type: IslandType::Contextual,
)]
class TestIslandContextual extends IslandPluginBase {

}
