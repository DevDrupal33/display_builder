<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
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
    $this->installConfig(['display_builder']);
  }

  /**
   * Test context requirements methods.
   */
  public function testContextRequirements(): void {
    $instance = $this->createDisplayBuilderInstance();

    self::assertFalse($instance->isPublishable());
    self::assertFalse($instance->hasSaveContextsRequirement('any'));

    $contexts = RequirementsContext::addToContext(['key1'], []);

    $instance = Instance::create([
      'id' => 'test_id',
      'contexts' => $contexts,
    ]);

    self::assertTrue($instance->isPublishable());
    self::assertTrue($instance->hasSaveContextsRequirement('key1'));
    self::assertFalse($instance->hasSaveContextsRequirement('key2'));
  }

  /**
   * Test the ::isPublishedPresent() method.
   */
  public function testIsPublishedPresent(): void {
    $instance = $this->createDisplayBuilderInstance();
    $testData = [['source_id' => 'component', 'node_id' => '1', 'source' => []]];

    // Initially Save match init, so is true.
    self::assertTrue($instance->isPublishedPresent());

    // Set save data.
    $instance->setSave($testData);
    self::assertFalse($instance->isPublishedPresent());

    // Modify state - should no longer be current.
    $modifiedData = [['source_id' => 'component', 'node_id' => '2', 'source' => []]];
    $instance->setNewPresent($modifiedData, 'Modified state');

    // Note: There may be edge cases where saveIsCurrent returns unexpected
    // results.
    // The important thing is that restore() works correctly, which it does.
    self::assertFalse($instance->isPublishedPresent());

    // Restore - should be current again.
    $instance->restore();
    // After restore, present should equal save, so isPublishedPresent() should
    // be true.
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
    $testData = [['source_id' => 'component', 'node_id' => '1']];
    $modifiedData = [['source_id' => 'component', 'node_id' => '2']];

    // Set save data.
    $instance->setSave($testData);

    // Modify current state.
    $instance->setNewPresent($modifiedData, 'Modified state');
    self::assertSame($modifiedData, $instance->getCurrentState());

    // Restore to save.
    $instance->restore();
    self::assertSame($testData, $instance->getCurrentState());
    self::assertSame('Back to saved data.', (string) $instance->getCurrent()->getLog());
  }

}
