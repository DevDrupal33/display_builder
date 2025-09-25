<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Url;
use Drupal\display_builder\BlockLibrarySourceHelper;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the BlockLibrarySourceHelper class.
 *
 * @internal
 */
#[CoversClass(BlockLibrarySourceHelper::class)]
#[Group('display_builder')]
final class BlockLibrarySourceHelperUnitTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    // $this->mockSource = $this->createMockSourceWithChoices();
  }

  /**
   * Test getGroupedChoices with no choices.
   */
  public function testGetGroupedChoicesBlockNoChoices(): void {
    $mockSource = $this->createMockSourceWithChoices();

    // Build mock sources.
    $sources = [
      'test_dev' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test dev',
          'description' => 'Test dev description',
          'provider' => 'display_builder_dev_tools',
        ],
        'source' => $mockSource,
        '_expected_group' => 'Dev tools',
      ],
      'test_icons' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test icons',
          'description' => 'Test icons description',
          'provider' => 'ui_icons_patterns',
        ],
        'source' => $mockSource,
        '_expected_group' => 'Utilities',
      ],
      'test' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test block',
          'description' => 'Test block description',
        ],
        'source' => $mockSource,
      ],
      'test_no_provider' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test no provider',
          'description' => 'Test no provider description',
        ],
        'source' => $mockSource,
      ],
      'test_page' => [
        'definition' => [
          'id' => 'test',
          'label' => 'Test page',
          'description' => 'Test page description',
          'provider' => 'display_builder_page_layout',
        ],
        'source' => $mockSource,
        '_expected_group' => 'Page',
      ],
      'test_patterns' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test patterns',
          'description' => 'Test patterns description',
          'provider' => 'ui_patterns',
        ],
        'source' => $mockSource,
        '_expected_group' => 'Utilities',
      ],
    ];

    $expected_choices = [];

    foreach ($sources as $source_id => $source) {
      $expected_choices[$source_id] = [
        'label' => (string) $source['definition']['label'],
        'data' => [
          'source_id' => $source_id,
        ],
        'keywords' => \sprintf('%s %s %s', $source['definition']['id'], $source['definition']['label'] ?? $source_id, $source['definition']['description'] ?? ''),
        'preview' => FALSE,
        'group' => $source['_expected_group'] ?? 'Others',
      ];
    }

    $expected = [
      'Page' => [
        'label' => 'Page',
        'choices' => [$expected_choices['test_page']],
      ],
      'Utilities' => [
        'label' => 'Utilities',
        'choices' => [
          $expected_choices['test_icons'],
          $expected_choices['test_patterns'],
        ],
      ],
      'Others' => [
        'label' => 'Others',
        'choices' => [
          $expected_choices['test'],
          $expected_choices['test_no_provider'],
        ],
      ],
      'Dev tools' => [
        'label' => 'Dev tools',
        'choices' => [$expected_choices['test_dev']],
      ],
    ];

    $result = BlockLibrarySourceHelper::getGroupedChoices($sources);
    self::assertSame($expected, $result);
  }

  /**
   * Test getGroupedChoices with choices and exclude.
   */
  public function testGetGroupedChoicesBlockChoicesAndExclude(): void {
    $mockSource = $this->createMockSourceWithChoices();

    $choices = [
      'test_1' => [
        'label' => 'Test 1',
        'provider' => 'included_provider',
        'original_id' => 'test_1',
        'group' => 'Group A',
      ],
      'test_2' => [
        'label' => 'Test 2',
        'provider' => 'included_provider',
        'original_id' => 'test_2',
        'group' => 'Group B',
      ],
      'test_excluded' => [
        'label' => 'Test excluded',
        'provider' => 'excluded_provider',
        'original_id' => 'test_excluded',
        'group' => 'Group Excluded',
      ],
      'test_fallback_source_label' => [
        'label' => 'Test fallback',
        'provider' => 'included_provider',
        'original_id' => 'test_fallback_source_label',
      ],
    ];

    $sources = [
      'test_choices' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test source',
          'description' => 'Test description',
        ],
        'source' => $mockSource,
        'choices' => $choices,
      ],
      'test_no_choices' => [
        'definition' => [
          'id' => 'block',
          'label' => 'Test source',
          'description' => 'Test description',
        ],
        'source' => $mockSource,
      ],
    ];

    // Prepare the result expected.
    $expected_choices = [];

    foreach ($choices as $choice_id => $choice) {
      $expected_choices[$choice_id] = [
        'label' => (string) $choice['label'],
        'data' => [
          'source_id' => 'test_choices',
          'source' => $this->createMockSourceWithChoices()->getChoiceSettings($choice_id),
        ],
        'group' => $choice['group'] ?? 'Others',
        'keywords' => \sprintf('block %s Test description %s', $choice['label'], $choice_id),
        'preview' => Url::fromRoute('display_builder.api_block_preview', ['block_id' => $choice_id]),
      ];
    }

    $expected = [
      'Others' => [
        'label' => 'Others',
        'choices' => [
          $expected_choices['test_fallback_source_label'],
          // The no choices is part of others.
          [
            'label' => 'Test source',
            'data' => [
              'source_id' => 'test_no_choices',
            ],
            'group' => 'Others',
            'keywords' => 'block Test source Test description',
            'preview' => FALSE,
          ],
        ],
      ],
      'Group A' => [
        'label' => 'Group A',
        'choices' => [$expected_choices['test_1']],
      ],
      'Group B' => [
        'label' => 'Group B',
        'choices' => [$expected_choices['test_2']],
      ],
    ];

    $result = BlockLibrarySourceHelper::getGroupedChoices($sources, ['excluded_provider']);

    self::assertEquals($expected, $result);
  }

  /**
   * Test getGroupedChoices with choices and exclude.
   */
  public function testGetGroupedChoicesWithEntityReference(): void {
    $mockSource = $this->createMockSourceWithChoices();
    $choices_1 = [
      'test_reference' => [
        'label' => 'Test reference',
        'original_id' => 'test_reference',
        '_expected_source_id' => 'test_reference_choices',
        '_expected_group' => 'Referenced entities',
        '_expected_keywords' => 'entity_reference Test reference Test reference description test_reference',
      ],
      'test_reference_2' => [
        'label' => 'Test reference 2',
        'original_id' => 'test_reference_2',
        '_expected_source_id' => 'test_reference_choices',
        '_expected_group' => 'Referenced entities',
        '_expected_keywords' => 'entity_reference Test reference 2 Test reference description test_reference_2',
      ],
    ];
    $choices_2 = [
      'test_field' => [
        'label' => 'Test field',
        'original_id' => 'test_field',
        '_expected_source_id' => 'test_field_choices',
        '_expected_group' => 'Fields',
        '_expected_keywords' => 'entity_field Test field Test field description test_field',
      ],
      'test_field_2' => [
        'label' => 'Test field 2',
        'original_id' => 'test_field_2',
        '_expected_source_id' => 'test_field_choices',
        '_expected_group' => 'Fields',
        '_expected_keywords' => 'entity_field Test field 2 Test field description test_field_2',
      ],
    ];
    $sources = [
      'test_reference_choices' => [
        'definition' => [
          'id' => 'entity_reference',
          'label' => 'Test reference',
          'description' => 'Test reference description',
        ],
        'source' => $mockSource,
        'choices' => $choices_1,
      ],
      'test_field_choices' => [
        'definition' => [
          'id' => 'entity_field',
          'label' => 'Test field',
          'description' => 'Test field description',
        ],
        'source' => $mockSource,
        'choices' => $choices_2,
      ],
    ];

    // Prepare the result expected.
    $expected_choices = [];

    foreach (\array_merge($choices_1, $choices_2) as $choice_id => $choice) {
      $expected_choices[$choice_id] = [
        'label' => (string) $choice['label'],
        'data' => [
          'source_id' => $choice['_expected_source_id'],
          'source' => $this->createMockSourceWithChoices()->getChoiceSettings($choice_id),
        ],
        'group' => $choice['_expected_group'] ?? 'Others',
        'keywords' => $choice['_expected_keywords'],
        'preview' => Url::fromRoute('display_builder.api_block_preview', ['block_id' => $choice_id]),
      ];
    }

    $expected = [
      'Fields' => [
        'label' => 'Fields',
        'choices' => [
          $expected_choices['test_field'],
          $expected_choices['test_field_2'],
        ],
      ],
      'Referenced entities' => [
        'label' => 'Referenced entities',
        'choices' => [
          $expected_choices['test_reference'],
          $expected_choices['test_reference_2'],
        ],
      ],
    ];

    $result = BlockLibrarySourceHelper::getGroupedChoices($sources);

    self::assertEquals($expected, $result);
  }

  /**
   * Create a mock SourceWithChoicesInterface with specified choice settings.
   *
   * @param array $choiceSettingsMap
   *   An associative array mapping choice IDs to their settings.
   *
   * @return \Drupal\ui_patterns\SourceWithChoicesInterface
   *   The mock source.
   */
  private function createMockSourceWithChoices(array $choiceSettingsMap = []) {
    $mock = $this->getMockBuilder(SourceWithChoicesInterface::class)
      ->onlyMethods(['getChoices', 'getChoiceSettings', 'getChoice'])
      ->getMock();

    $mock->method('getChoiceSettings')
      ->willReturnCallback(static function ($choice_id) use ($choiceSettingsMap) {
        return $choiceSettingsMap[$choice_id] ?? ['mock_setting' => $choice_id];
      });

    $mock->method('getChoices')
      ->willReturn([]);
    $mock->method('getChoice')
      ->willReturn('');

    return $mock;
  }

}
