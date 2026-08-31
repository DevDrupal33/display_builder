<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Plugin\display_builder\Island\ChromeIndicator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ChromeIndicator (Preview pane_header) island plugin.
 *
 * Pins the icon, its accessible label and its tooltip: the one place in the
 * builder UI that tells an editor whether the display they are looking at
 * previews wrapped in the site's real chrome or bare, mirroring the
 * instance's own ::previewWithChrome() answer.
 *
 * @internal
 */
#[CoversClass(ChromeIndicator::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ChromeIndicatorTest extends DisplayBuilderKernelTestBase {

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
   * Test the icon, its accessible label and modifier class follow the answer.
   *
   * @param bool $with_chrome
   *   What the instance's ::previewWithChrome() answers.
   */
  #[DataProvider('providerTestBuild')]
  public function testBuild(bool $with_chrome): void {
    $instance = $this->createMock(InstanceInterface::class);
    $instance->method('previewWithChrome')->willReturn($with_chrome);

    $build = $this->createIslandPlugin('chrome_indicator')->build($instance);

    if ($with_chrome) {
      $icon = $build['icon'];
      self::assertSame('sl-tooltip', $build['#tag']);
      self::assertSame('window', $icon['#attributes']['name']);
      self::assertSame('Full page preview', (string) $icon['#attributes']['label']);
      self::assertTrue(
        \in_array('db-chrome-indicator', $build['#attributes']['class'], TRUE),
      );
    }
    else {
      self::assertEmpty($build);
    }
  }

  /**
   * Provides test data for ::testBuild().
   *
   * @return array
   *   The data to test.
   */
  public static function providerTestBuild(): array {
    return [
      'with chrome' => [TRUE],
      'bare' => [FALSE],
    ];
  }

}
