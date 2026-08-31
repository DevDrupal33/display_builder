<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a sample entity cannot take down the display around it.
 *
 * An entity view display has no entity behind it, so it is built against a
 * sample one, which is never saved and therefore has no ID. A formatter that
 * needs an ID has nothing to work with, and the comment field is the sharp
 * case: its "Add comment" form loads the commented entity by ID and asserts
 * its way out on NULL. That throw is not contained where it happens - the
 * Preview judges whole root nodes, so it swallowed the component holding the
 * field and everything else inside it.
 *
 * @internal
 */
#[CoversClass(DisplayBuilderHelpers::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
#[RunTestsInSeparateProcesses]
final class SamplePreviewRenderTest extends EntityKernelTestBase {

  use CommentTestTrait;
  use UserCreationTrait;

  /**
   * The text of the node sitting beside the comment field.
   */
  private const SIBLING = 'Comments heading';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'comment',
    'display_builder',
    'display_builder_entity_view',
    'display_builder_test',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_field_formatters',
  ];

  /**
   * The display the sample entity is built for.
   */
  private EntityViewDisplay $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')->install(['display_builder_theme_test']);
    $this->config('system.theme')->set('default', 'display_builder_theme_test')->save();
    $this->installConfig(['display_builder', 'display_builder_test', 'comment']);
    $this->installEntitySchema('display_builder_instance');
    $this->installEntitySchema('comment');
    $this->installSchema('comment', ['comment_entity_statistics']);

    // The form is only offered to someone who could post one, so without these
    // the formatter emits no lazy builder and there is nothing to reproduce.
    $this->setUpCurrentUser([], ['access comments', 'post comments']);

    $this->addDefaultCommentField('entity_test', 'entity_test', 'comment', CommentItemInterface::OPEN);

    // ::addDefaultCommentField() created it on the way in.
    $this->display = $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test');

    $this->seedSampleEntity();
  }

  /**
   * A comment field beside other content leaves that content standing.
   */
  public function testCommentFieldDoesNotEmptyItsComponent(): void {
    $build = $this->container->get('ui_patterns.component_element_builder')
      ->buildSource([], 'content', [], $this->sourceTree(), $this->runtimeContexts());
    $build = $build['#slots']['content'][0] ?? [];

    $html = (string) $this->container->get('renderer')->renderInIsolation($build);

    self::assertStringContainsString(self::SIBLING, $html);
  }

  /**
   * Puts a sample entity with commenting open in the generator's store.
   *
   * ::createWithSampleValues() would otherwise pick the commenting status at
   * random, and a closed one asks for no form at all, which is the half of
   * the time this test would prove nothing.
   */
  private function seedSampleEntity(): void {
    $sample = EntityTest::create(['type' => 'entity_test', 'name' => 'Sample']);
    $sample->set('comment', ['status' => CommentItemInterface::OPEN]);

    $this->container->get('tempstore.shared')
      ->get('ui_patterns.sample_entity')
      ->set('entity_test.entity_test', $sample);
  }

  /**
   * The contexts the entity view buildable hands its sources.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The contexts.
   */
  private function runtimeContexts(): array {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance('entity_view', [
      'display' => $this->display,
    ]);

    return $buildable->getRuntimeContexts([]);
  }

  /**
   * One component holding a plain text node and the comment field.
   *
   * @return array
   *   The UI Patterns form state data for the root node.
   */
  private function sourceTree(): array {
    $derivable = 'field:entity_test:entity_test:comment';

    return [
      'node_id' => 'root',
      'source_id' => 'component',
      'source' => [
        'component' => [
          'component_id' => 'display_builder_theme_test:test_simple',
          'slots' => [
            'slot_1' => [
              'sources' => [
                [
                  'node_id' => 'sibling',
                  'source_id' => 'textfield',
                  'source' => ['value' => self::SIBLING],
                ],
                [
                  'node_id' => 'comment',
                  'source_id' => 'entity_field',
                  'source' => [
                    'derivable_context' => $derivable,
                    $derivable => [
                      'value' => [
                        'sources' => [
                          [
                            'source_id' => 'field_formatter:entity_test:entity_test:comment',
                            'source' => [
                              'type' => 'comment_default',
                              'settings' => [],
                              'third_party_settings' => [],
                            ],
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
