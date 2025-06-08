<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\EventSubscriber;

use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_views\DisplayBuilderViewsManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder views.
 */
class DisplayBuilderViewsLayoutEventsSubscriber implements EventSubscriberInterface {

  public function __construct(
    private StateManagerInterface $stateManager,
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

    if (!$this->stateManager->hasSaveContextsRequirement($builder_id, DisplayBuilderViewsManager::VIEWS_CONTEXT_REQUIREMENT, $contexts)) {
      return;
    }

    /** @var \Drupal\views\Entity\View $view */
    $view = $contexts['ui_patterns_views:view_entity']->getContextValue() ?? NULL;

    if (!$view) {
      return;
    }

    $builder_data = $this->stateManager->getCurrentState($builder_id);

    foreach ($view->get('display') as $display_id => $display) {
      if (!isset($display['display_options']['display_extenders']['display_builder']['display_builder_id'])) {
        continue;
      }

      $display_builder_id = $display['display_options']['display_extenders']['display_builder']['display_builder_id'];

      if ($display_builder_id !== $builder_id) {
        continue;
      }

      $display = &$view->getDisplay($display_id);
      $display['display_options']['display_extenders']['display_builder']['sources'] = $builder_data;
      $view->save();

      $view->invalidateCaches();

      // Seems invalidate is not enough, clear views cache, but again seems not
      // enough as we have a Page not found.
      // @todo find a way to clear only views cache.
      // views_invalidate_cache();
      drupal_flush_all_caches();

      continue;
    }
  }

}
