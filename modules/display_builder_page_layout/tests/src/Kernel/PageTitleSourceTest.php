<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase;
use Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\PageTitleSource;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\PropTypeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the page title source in a slot.
 *
 * The title is a string on a real page, so this source declares the string
 * prop type. A slot is where it breaks: UI Patterns converts a string source
 * for a slot with SlotPropType::convertFrom(), which wraps what it is given in
 * '#plain_text'. Both things this returns in a slot are render arrays, and a
 * render array put through that conversion is escaped into the page as text,
 * which throws before the user sees anything.
 *
 * Declaring the slot prop type natively is what stops the conversion, so both
 * states are driven through the slot prop type here.
 *
 * @internal
 */
#[CoversClass(PageTitleSource::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class PageTitleSourceTest extends KernelTestBase {

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
   * The page's own title reaches a slot as the render array it is.
   */
  public function testInjectedTitleReachesTheSlotUnconverted(): void {
    $title = ['#markup' => '<h1>The real page title</h1>'];

    self::assertSame(
      $title,
      $this->source([PageRegionSourceBase::PAGE_PROVIDED => $title])->getValue($this->slotPropType()),
      'The page title is returned rather than escaped into text.',
    );
  }

  /**
   * With nothing injected, the slot gets the region placeholder.
   */
  public function testEmptySettingsGiveThePlaceholder(): void {
    $build = $this->source([])->getValue($this->slotPropType());

    self::assertIsArray($build);
    self::assertSame('display_builder:placeholder', $build['#component'] ?? NULL);
    self::assertSame('region', $build['#props']['variant'] ?? NULL);
    self::assertSame('Page title', (string) $build['#slots']['content']['title']['#value']);
  }

  /**
   * Build the source plugin with the given settings.
   *
   * @param array $settings
   *   The node's `source` array, empty in the builder.
   *
   * @return \Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\PageTitleSource
   *   The source plugin.
   */
  private function source(array $settings): PageTitleSource {
    /** @var \Drupal\display_builder_page_layout\Plugin\UiPatterns\Source\PageTitleSource $source */
    $source = $this->container->get('plugin.manager.ui_patterns_source')->getSource(
      'content',
      [],
      ['source_id' => 'page_title', 'source' => $settings],
    );

    return $source;
  }

  /**
   * The slot prop type, as a layout nesting this node in a slot would pass.
   *
   * @return \Drupal\ui_patterns\PropTypeInterface
   *   The slot prop type.
   */
  private function slotPropType(): PropTypeInterface {
    return $this->container->get('plugin.manager.ui_patterns_prop_type')->createInstance('slot');
  }

}
