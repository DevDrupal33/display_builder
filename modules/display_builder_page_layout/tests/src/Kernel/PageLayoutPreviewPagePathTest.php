<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout as PageLayoutBuildable;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel test for the page a layout can be previewed on.
 *
 * Only a layout pinned to one concrete page has an unambiguous page to preview
 * against; everything else previews its sources on their own. Getting this
 * wrong in the permissive direction would have a preview render a page the
 * layout does not actually apply to.
 *
 * @internal
 */
#[CoversClass(PageLayoutBuildable::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class PageLayoutPreviewPagePathTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Required by display_builder_page_layout.builder_data_converter.
    'block',
    'system',
    'user',
    'display_builder',
    'display_builder_page_layout',
    'ui_patterns',
    'ui_patterns_field',
    'path_alias',
  ];

  /**
   * A layout names the page it is pinned to, or nothing at all.
   *
   * @param array $conditions
   *   The layout's conditions.
   * @param string|null $expected
   *   The path the layout should offer to preview on.
   */
  #[DataProvider('providerPreviewPagePath')]
  public function testPreviewPagePath(array $conditions, ?string $expected): void {
    self::assertSame($expected, $this->getPreviewPagePath($conditions));
  }

  /**
   * Cases for ::testPreviewPagePath().
   *
   * @return array
   *   Test cases, keyed by what the layout's conditions say.
   */
  public static function providerPreviewPagePath(): array {
    return [
      'no condition at all' => [[], NULL],
      'the front page' => [self::requestPath('<front>'), '<front>'],
      'one rooted path' => [self::requestPath('/test/builder'), '/test/builder'],
      'surrounding blank lines' => [self::requestPath("\n  /test/builder  \n\n"), '/test/builder'],
      'negated' => [self::requestPath('/test/builder', TRUE), NULL],
      'a wildcard' => [self::requestPath('/test/*'), NULL],
      'several paths' => [self::requestPath("/test/one\n/test/two"), NULL],
      'a path with no leading slash' => [self::requestPath('test/builder'), NULL],
      'an empty page list' => [self::requestPath(''), NULL],
    ];
  }

  /**
   * Builds a request_path condition configuration.
   *
   * @param string $pages
   *   The condition's page list.
   * @param bool $negate
   *   (Optional) Whether the condition is negated.
   *
   * @return array
   *   A conditions array for a page layout.
   */
  private static function requestPath(string $pages, bool $negate = FALSE): array {
    return [
      'request_path' => [
        'id' => 'request_path',
        'negate' => $negate,
        'pages' => $pages,
      ],
    ];
  }

  /**
   * Gets the page a layout with these conditions would be previewed on.
   *
   * @param array $conditions
   *   The layout's conditions.
   *
   * @return string|null
   *   The pinned path, or NULL when the layout has no single page.
   */
  private function getPreviewPagePath(array $conditions): ?string {
    $entity = PageLayout::create([
      'id' => 'pinned',
      'label' => 'Pinned',
      'conditions' => $conditions,
    ]);
    /** @var \Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout $buildable */
    $buildable = \Drupal::service('plugin.manager.display_buildable')->createInstance('page_layout', ['entity' => $entity]);

    return $buildable->getPreviewPagePath();
  }

}
