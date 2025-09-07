<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder views.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
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
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);

    if (!$instance->hasSaveContextsRequirement(DisplayExtender::getContextRequirement(), $contexts)) {
      return;
    }

    /** @var \Drupal\views\Entity\View $view */
    $view = $contexts['ui_patterns_views:view_entity']->getContextValue() ?? NULL;

    if (!$view) {
      return;
    }
    $display_id = DisplayExtender::checkInstanceId($builder_id)['display'];
    $view->getExecutable()->setDisplay($display_id);
    $extenders = $view->getExecutable()->getDisplay()->getExtenders();

    if (!isset($extenders['display_builder'])) {
      return;
    }
    /** @var \Drupal\display_builder\DisplayBuildableInterface $extender */
    $extender = $extenders['display_builder'];
    $extender->saveSources();
  }

}
