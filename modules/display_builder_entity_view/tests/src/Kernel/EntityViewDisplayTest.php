<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the Display Builder EntityViewDisplay.
 *
 * @internal
 */
#[CoversClass('\Drupal\display_builder_entity_view\Entity\EntityViewDisplay')]
#[Group('display_builder')]
final class EntityViewDisplayTest extends EntityKernelTestBase {

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
   * Test the ::isDisplayBuilderEnabled method.
   *
   * @param bool $expected
   *   The expected result.
   * @param string $view_mode
   *   The view mode to test.
   */
  #[DataProvider('providerTestIsDisplayBuilderEnabled')]
  public function testIsDisplayBuilderEnabled($expected, $view_mode): void {
    $display = EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => $view_mode,
      'status' => TRUE,
      'third_party_settings' => [
        'display_builder' => [
          ConfigFormBuilderInterface::PROFILE_PROPERTY => 'default',
        ],
      ],
    ]);

    $result = $display->isDisplayBuilderEnabled();

    self::assertSame($expected, $result);
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

}
