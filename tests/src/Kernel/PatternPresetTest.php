<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\display_builder\Entity\PatternPreset;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
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
      'source_id' => 'textfield',
      'source' => ['value' => 'foo bar'],
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
      'source_id' => 'textfield',
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
        'source_id' => 'textfield',
        'source' => ['value' => 'foo bar'],
      ],
    ]);
    $patternPreset->save();

    $loaded = PatternPreset::load('test_preset_summary');
    self::assertSame('Textfield: foo bar', $loaded->getSummary());
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
   * Tests getContexts() returns empty when source_id or source key is missing.
   *
   * @param array $sources
   *   Incomplete sources configuration.
   */
  #[DataProvider('providerGetContextsMissingKeys')]
  public function testGetContextsMissingKeys(array $sources): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_missing_' . \bin2hex(\random_bytes(8)),
      'sources' => $sources,
    ]);
    $preset->save();
    $loaded = PatternPreset::load($preset->id());
    self::assertEmpty($loaded->getContexts());
  }

  /**
   * Data provider for testGetContextsMissingKeys().
   *
   * @return iterable
   *   The data to test.
   */
  public static function providerGetContextsMissingKeys(): iterable {
    yield 'missing source_id' => [['source' => ['value' => 'foo']]];

    yield 'missing source' => [['source_id' => 'textfield']];

    yield 'empty sources' => [[]];
  }

  /**
   * Tests getContexts() returns empty when the source plugin does not exist.
   *
   * Simulates a stale config scenario (e.g. after config import when the plugin
   * was removed) by using reflection to set an invalid source_id directly on a
   * loaded entity, bypassing calculateDependencies() which runs during save().
   */
  public function testGetContextsUnknownPlugin(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_unknown',
      'sources' => [
        'source_id' => 'textfield',
        'source' => ['value' => 'foo'],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_unknown');

    (new \ReflectionProperty($loaded, 'sources'))->setValue($loaded, [
      'source_id' => 'nonexistent_plugin_xyz_abc',
      'source' => [],
    ]);

    self::assertEmpty($loaded->getContexts());
  }

  /**
   * Tests getContexts() returns required context definitions from a source.
   */
  public function testGetContextsReturnsRequiredContextDefinitions(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_defs',
      'sources' => [
        'source_id' => 'test_context_source',
        'source' => ['value' => ''],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_defs');
    $contexts = $loaded->getContexts();
    // Only required contexts are returned; optional ones are filtered out.
    self::assertArrayHasKey('entity', $contexts);
    self::assertTrue($contexts['entity']->isRequired());
    self::assertArrayNotHasKey('optional_entity', $contexts);
  }

  /**
   * Tests getContexts() via a SourceWithSlotsInterface with no prop contexts.
   */
  public function testGetContextsFromSlotSourceEmpty(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_slot_empty',
      'sources' => [
        'source_id' => 'test_slot_source',
        'source' => [],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_slot_empty');
    self::assertEmpty($loaded->getContexts());
  }

  /**
   * Tests getContexts() via SourceWithSlotsInterface finds contexts in props.
   */
  public function testGetContextsFromSlotSourceWithPropContexts(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_slot_props',
      'sources' => [
        'source_id' => 'test_slot_source',
        'source' => [
          'component' => [
            'props' => [
              [
                'source_id' => 'test_context_source',
                'source' => ['value' => ''],
              ],
            ],
          ],
        ],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_slot_props');
    $contexts = $loaded->getContexts();
    self::assertArrayHasKey('entity', $contexts);
    self::assertTrue($contexts['entity']->isRequired());
  }

  /**
   * Tests getContexts() skips invalid items in slot source props.
   *
   * @param array $props
   *   Invalid props items.
   */
  #[DataProvider('providerGetContextsFromSlotSourceInvalidProps')]
  public function testGetContextsFromSlotSourceInvalidProps(array $props): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_slot_invalid_' . \bin2hex(\random_bytes(8)),
      'sources' => [
        'source_id' => 'test_slot_source',
        'source' => [
          'component' => ['props' => $props],
        ],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load($preset->id());
    self::assertEmpty($loaded->getContexts());
  }

  /**
   * Data provider for testGetContextsFromSlotSourceInvalidProps().
   *
   * @return iterable
   *   The data to test.
   */
  public static function providerGetContextsFromSlotSourceInvalidProps(): iterable {
    yield 'non-array item' => [['just_a_string']];

    yield 'missing source_id key' => [[['source' => ['value' => '']]]];

    yield 'missing source key' => [[['source_id' => 'test_context_source']]];

    yield 'empty source array' => [[['source_id' => 'test_context_source', 'source' => []]]];

    yield 'empty source_id string' => [[['source_id' => '', 'source' => ['value' => '']]]];
  }

  /**
   * Tests areContextsSatisfied() when the preset has no context definitions.
   */
  public function testAreContextsSatisfiedNoRequirements(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_sat_none',
      'sources' => [
        'source_id' => 'textfield',
        'source' => ['value' => 'foo'],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_sat_none');
    self::assertTrue($loaded->areContextsSatisfied([]));
  }

  /**
   * Tests areContextsSatisfied() false when required context is absent.
   */
  public function testAreContextsSatisfiedMissingRequired(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_sat_miss',
      'sources' => [
        'source_id' => 'test_context_source',
        'source' => ['value' => ''],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_sat_miss');
    self::assertFalse($loaded->areContextsSatisfied([]));
  }

  /**
   * Tests areContextsSatisfied() true when required context is provided.
   *
   * Also verifies that optional contexts missing from the array are ignored.
   */
  public function testAreContextsSatisfiedWithSatisfiedContext(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_sat_ok',
      'sources' => [
        'source_id' => 'test_context_source',
        'source' => ['value' => ''],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_sat_ok');

    $user = User::create(['name' => 'test_ctx_user_' . \bin2hex(\random_bytes(8))]);
    $user->save();
    $entityContext = new Context(EntityContextDefinition::fromEntityTypeId('user'), $user);

    // Only the required 'entity' key is provided; 'optional_entity' is absent.
    self::assertTrue($loaded->areContextsSatisfied(['entity' => $entityContext]));
  }

  /**
   * Tests areContextsSatisfied() false when context type does not match.
   */
  public function testAreContextsSatisfiedWrongType(): void {
    $preset = PatternPreset::create([
      'id' => 'test_preset_ctx_sat_type',
      'sources' => [
        'source_id' => 'test_context_source',
        'source' => ['value' => ''],
      ],
    ]);
    $preset->save();
    $loaded = PatternPreset::load('test_preset_ctx_sat_type');

    $wrongContext = new Context(new ContextDefinition('string'), 'not_an_entity');
    self::assertFalse($loaded->areContextsSatisfied(['entity' => $wrongContext]));
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
        'source_id' => 'textfield',
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
