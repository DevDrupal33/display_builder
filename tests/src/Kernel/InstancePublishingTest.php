<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Instance entity.
 *
 * @internal
 */
#[CoversClass(Instance::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class InstancePublishingTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_ui',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder', 'display_builder_test']);
  }

  /**
   * Test the ::isPublishedPresent() method.
   */
  public function testIsPublishedPresent(): void {
    // We create an instance entity with 'test' as display buildable plugin,
    // so the data will be published in the State API.
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['source_id' => 'component', 'node_id' => '1', 'source' => []]];
    $instance->setNewPresent($testData, 'Initial state');

    $instance->publish();
    self::assertTrue($instance->isPublishedPresent());

    // Modify state - should no longer be current.
    $modifiedData = [['source_id' => 'component', 'node_id' => '2', 'source' => []]];
    $instance->setNewPresent($modifiedData, 'Modified state');

    // We don't publish yet.
    self::assertFalse($instance->isPublishedPresent());

    // Restore to the initial state.
    $instance->restore();
    // After restore, present should equal save, so isPublishedPresent() should
    // be true.
    self::assertTrue($instance->isPublishedPresent());

    // We test again, but we publish.
    $instance->setNewPresent($modifiedData, 'Modified state');
    self::assertFalse($instance->isPublishedPresent());

    $instance->publish();
    self::assertTrue($instance->isPublishedPresent());

    // Test edge case: when both present and save are null, should return true
    // This covers the null-safe operator behavior.
    $instance2 = $this->createDisplayBuilderInstance();
    self::assertTrue($instance2->isPublishedPresent());
  }

  /**
   * Test the ::restore() method.
   */
  public function testRestore(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = ['node_id' => '1', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []];
    $modifiedData = ['node_id' => '2', 'source_id' => 'component', 'source' => [], 'third_party_settings' => []];

    // Publish initial data.
    $instance->setNewPresent([$testData], 'Modified state');
    self::assertSame($testData, $instance->getCurrentState()[0]);

    $instance->publish();
    self::assertTrue($instance->isPublishedPresent());

    // Modify current state without publishing.
    $instance->setNewPresent([$modifiedData], 'Modified state');
    self::assertSame($modifiedData, $instance->getCurrentState()[0]);
    self::assertFalse($instance->isPublishedPresent());

    // Published state.
    $instance->restore();
    self::assertSame($testData, $instance->getCurrentState()[0]);
    self::assertSame('Restore published data.', (string) $instance->getRevisionLogMessage());
    self::assertTrue($instance->isPublishedPresent());
  }

}
