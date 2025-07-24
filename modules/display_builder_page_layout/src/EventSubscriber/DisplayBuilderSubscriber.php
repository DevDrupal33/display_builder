<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
    private StateManagerInterface $stateManager,
    private CachedDiscoveryClearerInterface $pluginCacheClearer,
    private EntityTypeManagerInterface $entityTypeManager,
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
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onSave(DisplayBuilderEvent $event): void {
    $builder_id = $event->getBuilderId();
    $contexts = $event->getData();
    // @see Drupal\display_builder_page_layout\Entity\PageLayout::getInstance()
    $prefix = 'page_layout__';

    if (!\str_starts_with($builder_id, $prefix)) {
      return;
    }

    // Context requirements is set to allow SourcePlugin when editing. We need
    // a precedence when saving.
    // @todo perhaps we need a third context.
    if (!$this->stateManager->hasSaveContextsRequirement($builder_id, PageLayout::getContextRequirement(), $contexts)) {
      return;
    }
    $page_layout_id = \substr($builder_id, \strlen($prefix));
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
    $page_layout = $this->entityTypeManager->getStorage('page_layout')->load($page_layout_id);
    $page_layout->saveSources();
    // Clearing plugin cache seems enough to get the new layout.
    // @todo It looks very costly. Check if it is still needed.
    $this->pluginCacheClearer->clearCachedDefinitions();
  }

}
