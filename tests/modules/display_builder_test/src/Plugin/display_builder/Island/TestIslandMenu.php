<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Test island plugin for testing purposes.
 */
#[Island(
  id: 'test_island_menu',
  label: new TranslatableMarkup('Test Island Menu'),
  description: new TranslatableMarkup('A test island for testing.'),
  type: IslandType::Menu,
)]
class TestIslandMenu extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    return [];
  }

}
