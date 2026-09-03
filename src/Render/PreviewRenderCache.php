<?php

declare(strict_types=1);

namespace Drupal\display_builder\Render;

use Drupal\Core\Render\RenderCacheInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Takes the render cache out of the loop while previewing on a real page.
 *
 * A display pinned to a page previews as that page, rendered in a sub-request
 * carrying the previewed instance. Everything on that page is ordinary
 * front-end markup, so a warm render cache answers it without ever asking the
 * display that is being edited: the editor gets the saved version back and no
 * unsaved edit ever reaches the screen.
 *
 * Reads are dropped so the draft is really built, and writes are dropped so
 * the draft cannot be handed to an ordinary visitor afterwards. Both only
 * while that one sub-request is being served.
 *
 * @see \Drupal\display_builder\Controller\ApiPreviewController::renderOnPinnedPage()
 * @see \Drupal\display_builder\PageCache\DenyPreviewSubRequest
 */
final class PreviewRenderCache implements RenderCacheInterface {

  public function __construct(
    private readonly RenderCacheInterface $inner,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function get(array $elements) {
    return $this->isPreviewSubRequest() ? FALSE : $this->inner->get($elements);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $multiple_elements): array {
    return $this->isPreviewSubRequest() ? [] : $this->inner->getMultiple($multiple_elements);
  }

  /**
   * {@inheritdoc}
   */
  public function set(array &$elements, array $pre_bubbling_elements) {
    return $this->isPreviewSubRequest() ? FALSE : $this->inner->set($elements, $pre_bubbling_elements);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableRenderArray(array $elements) {
    return $this->inner->getCacheableRenderArray($elements);
  }

  /**
   * Is a display being previewed on the page this request is rendering?
   */
  private function isPreviewSubRequest(): bool {
    return DisplayBuilderHelpers::previewedInstanceId($this->requestStack->getCurrentRequest()) !== NULL;
  }

}
