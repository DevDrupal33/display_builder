<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\Core\Render\PageDisplayVariantSelectionEvent;
use Drupal\Core\Routing\RouteMatch;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Event\FullPageVariantSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Test the FullPageVariantSubscriber class.
 *
 * @internal
 */
#[CoversClass(FullPageVariantSubscriber::class)]
#[Group('display_builder')]
final class FullPageVariantSubscriberUnitTest extends UnitTestCase {

  /**
   * Test a preview of a buildable that wants chrome keeps the normal variant.
   */
  public function testPreviewWithChromeKeepsNormalVariant(): void {
    $instance = $this->createMock(Instance::class);
    $instance->method('previewWithChrome')->willReturn(TRUE);

    $event = $this->dispatchPreviewSelection($instance);

    self::assertSame('block_page', $event->getPluginId());
  }

  /**
   * Test a preview of a buildable that wants no chrome forces the bare variant.
   */
  public function testPreviewWithoutChromeForcesBareVariant(): void {
    $instance = $this->createMock(Instance::class);
    $instance->method('previewWithChrome')->willReturn(FALSE);

    $event = $this->dispatchPreviewSelection($instance);

    self::assertSame('display_builder_full', $event->getPluginId());
  }

  /**
   * Dispatch the selection event for the preview route with a given instance.
   *
   * @param \Drupal\display_builder\Entity\Instance $instance
   *   The instance carried by the route.
   *
   * @return \Drupal\Core\Render\PageDisplayVariantSelectionEvent
   *   The event, after the subscriber has run.
   */
  private function dispatchPreviewSelection(Instance $instance): PageDisplayVariantSelectionEvent {
    $route = new Route('/preview/{display_builder_instance}');
    $route_match = new RouteMatch('display_builder.preview_island', $route, [
      'display_builder_instance' => $instance,
    ]);
    $event = new PageDisplayVariantSelectionEvent('block_page', $route_match);

    (new FullPageVariantSubscriber())->onSelectPageDisplayVariant($event);

    return $event;
  }

}
