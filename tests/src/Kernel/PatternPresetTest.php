<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\PatternPreset;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the PatternPreset entity.
 *
 * @internal
 */
#[CoversClass(PatternPreset::class)]
#[Group('display_builder')]
final class PatternPresetTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'filter',
    'ui_patterns',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('pattern_preset');
    $this->installEntitySchema('filter_format');
  }

  /**
   * Tests creating and editing a PatternPreset entity.
   */
  public function testPatternPresetCrud(): void {
    // Create a new pattern preset entity.
    $patternPreset = PatternPreset::create([
      'id' => 'test_preset',
      'label' => 'Test Preset',
      'description' => 'Test Description',
      'group' => 'Test Group',
      'weight' => 10,
      'sources' => [
        'source_id' => 'token',
        'source' => ['value' => 'foo bar'],
      ],
    ]);
    $patternPreset->save();

    // Test that the entity was created correctly.
    self::assertNotEmpty($patternPreset->id());
    self::assertSame('test_preset', $patternPreset->id());
    self::assertSame('Test Preset', $patternPreset->label());
    self::assertSame('Test Description', $patternPreset->get('description'));
    self::assertSame('Test Group', $patternPreset->getGroup());
    self::assertSame(10, $patternPreset->get('weight'));

    // Test entity loading.
    $loaded = PatternPreset::load('test_preset');
    self::assertNotNull($loaded);
    self::assertSame($patternPreset->id(), $loaded->id());
    self::assertSame($patternPreset->label(), $loaded->label());

    // Update the entity.
    $loaded->set('label', 'Updated Preset');
    $loaded->set('description', 'Updated Description');
    $loaded->set('group', 'Updated Group');
    $loaded->set('weight', 20);
    $loaded->save();

    // Reload and verify changes.
    $updated = PatternPreset::load('test_preset');
    self::assertSame('Updated Preset', $updated->label());
    self::assertSame('Updated Description', $updated->get('description'));
    self::assertSame('Updated Group', $updated->getGroup());
    self::assertSame(20, $updated->get('weight'));

    // Delete the entity.
    $updated->delete();
    $deleted = PatternPreset::load('test_preset');
    self::assertNull($deleted);
  }

  /**
   * Tests the getSources method.
   */
  public function testGetSources(): void {
    $sources = [
      'source_id' => 'token',
      'source' => ['value' => 'foo bar'],
    ];
    $patternPreset = PatternPreset::create([
      'id' => 'test_preset_sources',
      'sources' => [$sources],
    ]);
    $patternPreset->save();

    $loaded = PatternPreset::load('test_preset_sources');
    $loadedSources = $loaded->getSources();

    // getSources adds a node_id.
    self::assertArrayHasKey('node_id', $loadedSources);
    self::assertNotEmpty($loadedSources['node_id']);

    // Remove it for comparison.
    unset($loadedSources['node_id']);
    self::assertEquals($sources, $loadedSources);
  }

  /**
   * Tests the getSummary method.
   */
  public function testGetSummary(): void {
    $patternPreset = PatternPreset::create([
      'id' => 'test_preset_summary',
      'label' => 'Test Preset Summary',
      'sources' => [
        'source_id' => 'token',
        'source' => ['value' => 'foo bar'],
      ],
    ]);
    $patternPreset->save();

    $loaded = PatternPreset::load('test_preset_summary');
    self::assertSame('Token: foo bar', $loaded->getSummary());
  }

  /**
   * Tests the getContexts method.
   */
  public function testGetContextsEmpty(): void {
    $sources = [
      'source_id' => 'wysiwyg',
      'source' => [
        'value' => [
          'value' => 'foo bar',
          'format' => 'plain_text',
        ],
      ],
    ];
    $patternPreset = PatternPreset::create([
      'id' => 'test_preset_contexts',
      'sources' => $sources,
    ]);
    $patternPreset->save();

    $loaded = PatternPreset::load('test_preset_contexts');
    $contexts = $loaded->getContexts();
    self::assertEmpty($contexts);
  }

}
