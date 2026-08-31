<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase;
use Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\MainPageContentSource;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the main content source's states.
 *
 * The settings of this source are not configuration, they are the payload:
 * PageLayoutPageVariant swaps the node's `source` for the page's main content
 * render array, wrapped in PageRegionSourceBase::PAGE_PROVIDED, before
 * anything renders, and it arrives here as the settings.
 *
 * Getting the states the wrong way round puts a placeholder on every real page
 * of the site, so all three are driven directly here. The empty-payload case
 * is the one a truthiness check gets wrong, and it is why the wrapper exists.
 * A page-render test does not pin any of this: the prop type reaching
 * ::getValue() depends on how a given layout nests the node, and one that
 * happens to pass proves nothing about another.
 *
 * @internal
 */
#[CoversClass(MainPageContentSource::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class MainPageContentSourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_page_layout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Source plugins resolve the current user context on instantiation.
    $this->installEntitySchema('user');
  }

  /**
   * Injected page content always wins, whatever the prop type.
   *
   * This is the regression that put "Main content" on every node page.
   */
  public function testInjectedContentAlwaysWins(): void {
    $content = ['#markup' => 'The real page content'];

    foreach ([NULL, $this->slotPropType()] as $prop_type) {
      $source = $this->injected($content);

      self::assertSame(
        $content,
        $source->getValue($prop_type),
        'The page content is returned rather than a placeholder.',
      );
    }
  }

  /**
   * A page that renders no main content is still a page.
   *
   * The one the truthiness check got wrong: an empty payload and an untouched
   * node both arrive as an empty array, so testing the payload put the
   * builder's placeholder on a real page. The wrapper is the signal.
   */
  public function testInjectedEmptyContentSkipsPlaceholder(): void {
    foreach ([NULL, $this->slotPropType()] as $prop_type) {
      self::assertSame(
        [],
        $this->injected([])->getValue($prop_type),
        'An empty injected payload renders as empty, not as a placeholder.',
      );
    }
  }

  /**
   * With nothing injected, the builder gets a placeholder to look at.
   */
  public function testEmptySettingsGiveThePlaceholder(): void {
    $build = $this->source([])->getValue($this->slotPropType());

    self::assertIsArray($build);
    self::assertSame('display_builder:placeholder', $build['#component'] ?? NULL);

    // A region, not a control: this stands for the whole content area of every
    // page the layout serves.
    self::assertSame('region', $build['#props']['variant'] ?? NULL);
    self::assertContains('db-placeholder-region--lg', $build['#attributes']['class'] ?? []);

    $content = $build['#slots']['content'];
    self::assertSame('Main content', (string) $content['title']['#value']);
    // No link is possible: which display fills the slot depends on the route
    // being rendered, so the wording only promises that the page fills it.
    self::assertStringContainsString('replaced by the page value', (string) $content['help']['#value']);
  }

  /**
   * Build the source plugin as the page pipeline leaves it.
   *
   * @param array $content
   *   The page's main content render array, which may legitimately be empty.
   *
   * @return \Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\MainPageContentSource
   *   The source plugin.
   *
   * @see \Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant::replaceTitleAndContent()
   */
  private function injected(array $content): MainPageContentSource {
    return $this->source([PageRegionSourceBase::PAGE_PROVIDED => $content]);
  }

  /**
   * Build the source plugin with the given settings.
   *
   * @param array $settings
   *   The node's `source` array, empty in the builder.
   *
   * @return \Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\MainPageContentSource
   *   The source plugin.
   */
  private function source(array $settings): MainPageContentSource {
    /** @var \Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\MainPageContentSource $source */
    $source = $this->container->get('plugin.manager.ui_patterns_source')->getSource(
      'content',
      [],
      ['source_id' => 'main_page_content', 'source' => $settings],
    );

    return $source;
  }

  /**
   * The slot prop type, as a layout nesting this node in a slot would pass.
   *
   * @return \Drupal\ui_patterns\PropTypeInterface
   *   The slot prop type.
   */
  private function slotPropType() {
    return $this->container->get('plugin.manager.ui_patterns_prop_type')->createInstance('slot');
  }

}
