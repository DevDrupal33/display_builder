<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\PatternPreset;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the PatternPreset entity.
 *
 * @internal
 */
#[CoversClass(PatternPreset::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
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
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test', 'node']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('pattern_preset');
    $this->installEntitySchema('filter_format');
    $this->installEntitySchema('node');
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
    self::assertSame($sources, $loadedSources);
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
   * Tests the getGroup method.
   *
   * @param array $data
   *   The data to create the preset.
   * @param string|null $expected
   *   The expected group.
   */
  #[DataProvider('providerGetGroup')]
  public function testGetGroup(array $data, ?string $expected): void {
    $preset = PatternPreset::create($data);
    $preset->save();

    $loaded = PatternPreset::load($data['id']);
    self::assertSame($expected, $loaded->getGroup());
  }

  /**
   * Data provider for testGetGroup().
   *
   * @return iterable
   *   The data to test.
   */
  public static function providerGetGroup(): iterable {
    yield 'preset empty no group' => [
      'data' => [
        'id' => 'test_preset_group_empty',
        'label' => 'Test Preset',
        'sources' => [],
      ],
      'expected' => NULL,
    ];

    yield 'preset with group' => [
      'data' => [
        'id' => 'test_preset_group_manual_empty',
        'label' => 'Test Preset',
        'group' => 'Foo',
        'sources' => [],
      ],
      'expected' => 'Foo',
    ];

    yield 'preset with group and source' => [
      'data' => [
        'id' => 'test_preset_group_manual',
        'label' => 'Test Preset',
        'group' => 'Foo',
        'sources' => [
          'source_id' => 'test_group_source',
          'source' => [],
        ],
      ],
      'expected' => 'Foo',
    ];

    yield 'preset without group and source' => [
      'data' => [
        'id' => 'test_preset_group_source',
        'label' => 'Test Preset',
        'sources' => [
          'source_id' => 'test_group_source',
          'source' => [],
        ],
      ],
      'expected' => 'Test Source Group',
    ];
  }

  /**
   * Tests creating and editing a PatternPreset entity.
   */
  public function testPatternPresetCrud(): void {
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

}
