<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns responses for Display builder routes.
 */
abstract class ApiControllerBase extends ControllerBase {

  public const string SSE_COLLECTION = 'display_builder_sse';

  /**
   * The list of DB events which triggers SSE refresh.
   *
   * ON_ACTIVE is intentionally excluded: it is a client-side presence signal
   * that must not trigger a full SSE broadcast to avoid feedback loops.
   */
  public const array SSE_EVENTS = [
    DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
    DisplayBuilderEvents::ON_ATTACH_TO_SLOT,
    DisplayBuilderEvents::ON_DELETE,
    DisplayBuilderEvents::ON_HISTORY_CHANGE,
    DisplayBuilderEvents::ON_MOVE,
    DisplayBuilderEvents::ON_PRESET_SAVE,
    DisplayBuilderEvents::ON_PUBLISH,
    DisplayBuilderEvents::ON_RESTORE,
    DisplayBuilderEvents::ON_REVERT,
    DisplayBuilderEvents::ON_UPDATE,
  ];

  /**
   * The Display Builder instance triggering the action.
   */
  protected InstanceInterface $builder;

  /**
   * Plugin ID of the island triggering the HTMX event.
   *
   * If not NULL, the island will be skipped from the event dispatch. Useful to
   * avoid swapping the content of an island which is already in the expected
   * state. For examples, if we move an instance in Builder, Layers or Tree
   * panels, if we change the settings in InstanceForm.
   *
   * @see \Drupal\display_builder\Event\DisplayBuilderEventsSubscriber::dispatchToIslands()
   * @see \Drupal\display_builder\HtmxEvents
   */
  protected ?string $islandId = NULL;

  /**
   * The lazy loaded display builder.
   */
  protected ?ProfileInterface $displayBuilder = NULL;

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected RendererInterface $renderer,
    protected TimeInterface $time,
    #[Autowire(service: 'tempstore.shared')]
    protected SharedTempStoreFactory $sharedTempStoreFactory,
    protected SessionInterface $session,
  ) {}

  /**
   * Dispatches a display builder event.
   *
   * @param string $event_id
   *   The event ID.
   * @param array|null $data
   *   The data.
   * @param string|null $node_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return array
   *   A renderable array.
   */
  protected function dispatchDisplayBuilderEvent(
    string $event_id,
    ?array $data = NULL,
    ?string $node_id = NULL,
    ?string $parent_id = NULL,
  ): array {
    $event = $this->createEventWithEnabledIsland($event_id, $data, $node_id, $parent_id);
    $this->saveSseData($event_id);

    return $event->getResult();
  }

  /**
   * Creates a display builder event with enabled islands only.
   *
   * Use a cache to avoid loading all the builder configuration.
   *
   * @param string $event_id
   *   The event ID.
   * @param array|null $data
   *   The data.
   * @param string|null $node_id
   *   Optional Instance entity ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return \Drupal\display_builder\Event\DisplayBuilderEvent
   *   The event.
   */
  protected function createEventWithEnabledIsland(string $event_id, ?array $data, ?string $node_id, ?string $parent_id): DisplayBuilderEvent {
    $event = new DisplayBuilderEvent($this->builder, $data, $node_id, $parent_id, $this->islandId);
    $this->eventDispatcher->dispatch($event, $event_id);

    return $event;
  }

  /**
   * Save data for SSE.
   *
   * @param string $event_id
   *   The event ID.
   */
  protected function saveSseData(string $event_id): void {
    if (!\in_array($event_id, $this::SSE_EVENTS, TRUE)) {
      return;
    }

    $state = [
      'sessionId' => $this->session->getId(),
      'timestamp' => $this->time->getRequestTime(),
      // instanceId here is the ID of a display_builder_instance entity.
      'instanceId' => (string) $this->builder->id(),
    ];
    $collection = $this->sharedTempStoreFactory->get($this::SSE_COLLECTION);
    $collection->set(\sprintf('%s_latest', (string) $this->builder->id()), $state);
  }

}
