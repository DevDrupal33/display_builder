<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\display_builder\Island\IslandPluginManager;
use Drupal\display_builder\Plugin\UiPatterns\Source\BlockSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the block source standing in for page chrome.
 *
 * The whole design rests on one gate: a chrome block is replaced inside a
 * builder and nowhere else. Losing that in the permissive direction would put
 * a placeholder on every visitor's page where their status messages belong.
 *
 * @internal
 */
#[CoversClass(BlockSource::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class BlockSourceTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
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
  }

  /**
   * Inside a builder the chrome block is replaced by a named region.
   */
  public function testInsideBuilderTheBlockIsReplaced(): void {
    $build = $this->buildBlock('system_messages_block', TRUE);

    self::assertSame('display_builder:placeholder', $build['#component'] ?? NULL);
    self::assertSame('region', $build['#props']['variant'] ?? NULL);
    self::assertSame('Messages', (string) $build['#slots']['content']['title']['#value']);
  }

  /**
   * Everywhere else the block renders for real.
   *
   * A display previewed on its own page goes through here: the sub-request
   * runs the real page pipeline and builds no island, so the requirement is
   * absent and the visitor's own messages and tabs are what shows.
   */
  public function testOutsideBuilderTheBlockRendersForReal(): void {
    $build = $this->buildBlock('system_messages_block', FALSE);

    self::assertArrayNotHasKey('#component', $build);
  }

  /**
   * A block nobody singled out is left alone in a builder too.
   */
  public function testOrdinaryBlockIsLeftAlone(): void {
    $build = $this->buildBlock('display_builder_test_correct', TRUE);

    self::assertArrayNotHasKey('#component', $build);
  }

  /**
   * Builds one block the way a slot would.
   *
   * @param string $plugin_id
   *   The block plugin to place.
   * @param bool $in_builder
   *   Whether to mark the contexts as a builder rendering this.
   *
   * @return array
   *   What the source resolved to.
   */
  private function buildBlock(string $plugin_id, bool $in_builder): array {
    $contexts = [];

    if ($in_builder) {
      $contexts[IslandPluginManager::IN_PREVIEW_CONTEXT] = new Context(
        new ContextDefinition('boolean'),
        TRUE,
      );
    }
    $source = [
      'node_id' => 'block_1',
      'source_id' => 'block',
      'source' => ['plugin_id' => $plugin_id],
    ];
    $build = $this->container->get('ui_patterns.component_element_builder')
      ->buildSource([], 'content', [], $source, $contexts);

    return $build['#slots']['content'][0] ?? [];
  }

}
