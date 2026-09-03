<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\Render\PreviewRenderCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kernel test for the render cache decorator that previews stand down.
 *
 * This decorates a core service, so it sits on the render path of every
 * request the site serves once the module is installed. That is only
 * defensible while it is provably inert outside the one sub-request it exists
 * for, which is what the first half of this asserts. The second half asserts
 * it is not inert inside that sub-request, because a warm cache there hands
 * the editor the saved display back and silently drops their unsaved edits.
 *
 * @internal
 */
#[CoversClass(PreviewRenderCache::class)]
#[Group('display_builder')]
final class PreviewRenderCacheTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'display_builder',
  ];

  /**
   * The decorated service, as the container hands it out.
   */
  private RenderCacheInterface $renderCache;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->renderCache = $this->container->get('render_cache');
  }

  /**
   * The decorator is really in front of core's render cache.
   */
  public function testTheDecoratorIsWired(): void {
    self::assertInstanceOf(PreviewRenderCache::class, $this->renderCache);
  }

  /**
   * On an ordinary request every call reaches the decorated service.
   */
  public function testOrdinaryRequestIsUntouched(): void {
    $elements = $this->cacheableElements();
    $this->renderCache->set($elements, $elements);

    self::assertNotFalse($this->renderCache->get($elements), 'The write and the read both reached the real cache.');
    self::assertNotEmpty($this->renderCache->getMultiple([$elements]));
  }

  /**
   * Inside a preview sub-request reads and writes are both dropped.
   */
  public function testPreviewSubRequestIsNotCached(): void {
    $elements = $this->cacheableElements();
    $this->renderCache->set($elements, $elements);
    $this->enterPreviewSubRequest();

    self::assertFalse($this->renderCache->get($elements), 'A warm entry is not served.');
    self::assertSame([], $this->renderCache->getMultiple([$elements]));
    self::assertFalse($this->renderCache->set($elements, $elements), 'The draft is not stored.');
  }

  /**
   * The draft rendered in the sub-request never lands in the real cache.
   *
   * The sub-request is left before asking, because asking from inside it is
   * answered by the dropped read and would pass whether or not the write went
   * through. The ordinary request underneath is the one that must come up
   * empty: it is the one an ordinary visitor is served from.
   */
  public function testTheDraftIsNotLeftBehind(): void {
    $draft = $this->cacheableElements('draft');
    $this->enterPreviewSubRequest();
    $this->renderCache->set($draft, $draft);
    $this->container->get('request_stack')->pop();

    self::assertFalse($this->renderCache->get($draft), 'Nothing was written through.');
  }

  /**
   * Pushes a request carrying the previewed instance attribute.
   *
   * A sub-request inherits the session of the request it was opened from, and
   * so must this one: KernelTestBase reaches for it while tearing down, and a
   * request stack topped by a session-less request fails the test after it
   * has already passed.
   */
  private function enterPreviewSubRequest(): void {
    $stack = $this->container->get('request_stack');
    $request = Request::create('/');
    $request->setSession($stack->getCurrentRequest()->getSession());
    $request->attributes->set(DisplayBuildableInterface::PREVIEW_INSTANCE_ATTRIBUTE, 'standalone__test');
    $stack->push($request);
  }

  /**
   * A minimal cacheable render array, distinct per key.
   *
   * Built by hand rather than by rendering it: the renderer warms the cache
   * on the way through, which would put the entry there before the decorator
   * ever got a say.
   *
   * @param string $key
   *   What makes this entry its own, so two calls do not collide.
   *
   * @return array
   *   The render array, carrying the cache metadata ::set() needs.
   */
  private function cacheableElements(string $key = 'default'): array {
    return [
      '#markup' => Markup::create('cached'),
      '#attached' => [],
      '#cache' => [
        'keys' => ['display_builder_test', $key],
        'contexts' => [],
        'tags' => [],
        'max-age' => Cache::PERMANENT,
      ],
    ];
  }

}
