<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\EventSubscriber;

use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\Event\DisplayBuilderDataEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder views.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
    private DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DisplayBuilderEvents::ON_PUBLISH => 'onPublish',
    ];
  }

  /**
   * Event handler for when a display builder is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderDataEvent $event
   *   The event object.
   */
  public function onPublish(DisplayBuilderDataEvent $event): void {
    $contexts = $event->getData();
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $event->getInstance();

    if (!$instance->hasSaveContextsRequirement(ViewDisplay::getContextRequirement(), $contexts)) {
      return;
    }

    /** @var \Drupal\views\Entity\View $view */
    $view = $contexts['ui_patterns_views:view_entity']->getContextValue() ?? NULL;

    if (!$view) {
      return;
    }
    $display_id = ViewDisplay::checkInstanceId((string) $instance->id())['display'];
    $view->getExecutable()->setDisplay($display_id);
    $extenders = $view->getExecutable()->getDisplay()->getExtenders();

    if (!isset($extenders['display_builder'])) {
      return;
    }
    /** @var \Drupal\views\Plugin\views\PluginBase $extender */
    $extender = $extenders['display_builder'];
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('view_display', ['extender' => $extender]);
    $buildable->saveSources();
  }

}
