<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant;
use Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test how the page variant injects the page title into the source tree.
 *
 * A plain string title becomes a themeable `page_title` render element
 * instead of a hardcoded `<h1 class="title page-title">` string: the same
 * element core's own page_title_block builds, so every theme's own
 * page-title.html.twig - not this class - decides the markup and classes.
 *
 * @internal
 */
#[CoversClass(PageLayoutPageVariant::class)]
#[Group('display_builder')]
#[Group('display_builder_page_layout')]
#[RunTestsInSeparateProcesses]
final class PageLayoutPageVariantTest extends KernelTestBase {

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
   * A plain string title is wrapped as a themeable page_title element.
   */
  public function testStringTitleBecomesPageTitleElement(): void {
    $data = $this->replaceTitleAndContent('My page title');

    self::assertSame(
      ['#type' => 'page_title', '#title' => 'My page title'],
      $data['node']['source'][PageRegionSourceBase::PAGE_PROVIDED],
    );
  }

  /**
   * A render array title (already formatted) passes through untouched.
   */
  public function testRenderArrayTitlePassesThroughUnchanged(): void {
    $title = ['#type' => 'inline_template', '#template' => '<em>{{ title }}</em>', '#context' => ['title' => 'Formatted']];

    $data = $this->replaceTitleAndContent($title);

    self::assertSame($title, $data['node']['source'][PageRegionSourceBase::PAGE_PROVIDED]);
  }

  /**
   * Calls the private ::replaceTitleAndContent() against a minimal tree.
   *
   * @param mixed $title
   *   The title to inject.
   *
   * @return array
   *   The mutated source tree.
   */
  private function replaceTitleAndContent(mixed $title): array {
    $data = [
      'node' => ['source_id' => 'page_title', 'source' => []],
    ];

    /** @var \Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant $variant */
    $variant = PageLayoutPageVariant::create($this->container, [], 'display_builder_page_layout', []);

    $method = new \ReflectionMethod($variant, 'replaceTitleAndContent');
    $method->invokeArgs($variant, [&$data, $title, NULL]);

    return $data;
  }

}
