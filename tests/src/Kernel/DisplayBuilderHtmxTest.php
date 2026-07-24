<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderHtmx;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the DisplayBuilderHtmx helper built on top of core's Htmx class.
 *
 * @internal
 */
#[CoversClass(DisplayBuilderHtmx::class)]
#[Group('display_builder')]
final class DisplayBuilderHtmxTest extends KernelTestBase {

  /**
   * Tests ::outOfBand() wraps a renderable in a swap-oob div.
   */
  public function testOutOfBandWrapsRenderableWithSwapOob(): void {
    $renderable = ['#markup' => 'content'];

    $wrapper = DisplayBuilderHtmx::outOfBand($renderable, '#target', 'innerHTML');

    self::assertSame('html_tag', $wrapper['#type']);
    self::assertSame('div', $wrapper['#tag']);
    self::assertSame($renderable, $wrapper['content']);
    self::assertSame('innerHTML:#target', (string) $wrapper['#attributes']['data-hx-swap-oob']);
  }

  /**
   * Tests ::makeOutOfBand() alters a renderable in place.
   */
  public function testMakeOutOfBandAltersInPlace(): void {
    $renderable = ['#markup' => 'content'];

    $altered = DisplayBuilderHtmx::makeOutOfBand($renderable, '#target', 'outerHTML');

    self::assertSame('content', $altered['#markup']);
    self::assertSame('outerHTML:#target', (string) $altered['#attributes']['data-hx-swap-oob']);
  }

  /**
   * Tests ::request() builds the expected request/trigger/swap attributes.
   */
  public function testRequestBuildsExpectedAttributes(): void {
    $url = Url::fromUri('base:/display-builder-htmx-test');

    $htmx = DisplayBuilderHtmx::request('post', $url, 'click consume');
    $build = [];
    $htmx->applyTo($build);

    self::assertStringEndsWith('/display-builder-htmx-test', (string) $build['#attributes']['data-hx-post']);
    self::assertSame('click consume', (string) $build['#attributes']['data-hx-trigger']);
    self::assertSame('none ignoreTitle:true', (string) $build['#attributes']['data-hx-swap']);
    self::assertContains('core/drupal.htmx', $build['#attached']['library']);
  }

}
