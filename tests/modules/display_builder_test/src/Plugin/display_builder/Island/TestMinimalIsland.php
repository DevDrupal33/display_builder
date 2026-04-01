<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Minimal island plugin to test IslandPluginBase default behavior.
 *
 * This plugin deliberately avoids overriding any base class methods beyond
 * buildContent() so that the base class defaults can be exercised in tests.
 */
#[Island(
  id: 'test_minimal',
  label: new TranslatableMarkup('[Test] Minimal'),
  description: new TranslatableMarkup('Minimal island for testing base class behavior.'),
  type: IslandType::View,
  icon: 'test-icon',
)]
class TestMinimalIsland extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function buildContent(InstanceInterface $builder, array $data, array $options): array {
    return ['#markup' => 'minimal content'];
  }

}
