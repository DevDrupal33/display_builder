<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Drupal\display_builder\StorageProperties;

/**
 * Test the DisplayBuilderEntityViewDisplay.
 */
#[CoversClass('\Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay')]
#[Group('display_builder')]
class DisplayBuilderEntityViewDisplayTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'display_builder',
    'display_builder_entity_view',
    'ui_patterns',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['display_builder']);
  }

  /**
   * Provides test data for ::testIsDisplayBuilderEnabled().
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestIsDisplayBuilderEnabled(): array {
    $data = [];
    $data['default enabled'] = [TRUE, 'default'];
    $data['full enabled'] = [TRUE, 'full'];

    return $data;
  }

  /**
   * Test the ::isDisplayBuilderEnabled method.
   *
   * @param bool $expected
   *   The expected result.
   * @param string $view_mode
   *   The view mode to test.
   */
  #[DataProvider('providerTestIsDisplayBuilderEnabled')]
  public function testIsDisplayBuilderEnabled($expected, $view_mode): void {
    $display = DisplayBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => $view_mode,
      'status' => TRUE,
      'third_party_settings' => [
        'display_builder' => [
          StorageProperties::ConfigEntityId->value => 'default',
        ],
      ],
    ]);

    $result = $display->isDisplayBuilderEnabled();

    $this->assertSame($expected, $result);
  }

}
