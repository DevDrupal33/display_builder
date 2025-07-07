<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\EventSubscriber;

use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_page_layout\DisplayBuilderPageLayout;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
    private StateManagerInterface $stateManager,
    private DisplayBuilderPageLayout $pageLayoutManager,
    private CachedDiscoveryClearerInterface $pluginCacheClearer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DisplayBuilderEvents::ON_SAVE => 'onSave',
    ];
  }

  /**
   * Event handler for when a display builder is saved.
   *
   * Dispatch the save process if this is the main page layout or a builder
   * associated to a page manager variant.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onSave(DisplayBuilderEvent $event): void {
    $builder_id = $event->getBuilderId();
    $contexts = $event->getData();

    // Context requirements is set for page manager as page layout and page
    // manager to allow SourcePlugin when editing. We need a precedence when
    // saving.
    // @todo perhaps we need a third context.
    if ($this->stateManager->hasSaveContextsRequirement($builder_id, DisplayBuilderPageLayout::PAGE_MANAGER_CONTEXT_REQUIREMENT, $contexts)) {
      $this->pageLayoutManager->savePageManagerCurrentState($builder_id, $contexts);
      // Plugin cache seems enough to get the new layout.
      $this->pluginCacheClearer->clearCachedDefinitions();
    }
    elseif ($this->stateManager->hasSaveContextsRequirement($builder_id, DisplayBuilderPageLayout::PAGE_LAYOUT_CONTEXT_REQUIREMENT, $contexts)) {
      $this->pageLayoutManager->savePageLayoutCurrentState($builder_id);

      // Plugin cache seems enough to get the new layout.
      $this->pluginCacheClearer->clearCachedDefinitions();

      return;
    }
  }

}
