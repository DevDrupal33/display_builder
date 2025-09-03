<?php

declare(strict_types=1);

namespace Drupal\display_builder\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\display_builder\DisplayBuilderInterface;
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
   */
  public const array SSE_EVENTS = [
    DisplayBuilderEvents::ON_ATTACH_TO_ROOT,
    DisplayBuilderEvents::ON_ATTACH_TO_SLOT,
    DisplayBuilderEvents::ON_DELETE,
    DisplayBuilderEvents::ON_HISTORY_CHANGE,
    DisplayBuilderEvents::ON_MOVE,
    DisplayBuilderEvents::ON_PRESET_SAVE,
    DisplayBuilderEvents::ON_SAVE,
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
  protected ?DisplayBuilderInterface $displayBuilder = NULL;

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected MemoryCacheInterface $memoryCache,
    protected RendererInterface $renderer,
    protected TimeInterface $time,
    #[Autowire(service: 'tempstore.shared')] protected SharedTempStoreFactory $sharedTempStoreFactory,
    protected SessionInterface $session,
  ) {}

  /**
   * Creates a display builder event with enabled islands only.
   *
   * Use a cache to avoid loading all the builder configuration.
   *
   * @param string $event_id
   *   The event ID.
   * @param array|null $data
   *   The data.
   * @param string|null $instance_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   *
   * @return \Drupal\display_builder\Event\DisplayBuilderEvent
   *   The event.
   */
  protected function createEventWithEnabledIsland($event_id, $data, $instance_id, $parent_id): DisplayBuilderEvent {
    $builder_id = (string) $this->builder->id();
    $key = \sprintf('db_%s_island_enable', $builder_id);
    $island_configuration_key = \sprintf('db_%s_island_configuration', $builder_id);
    $island_enabled = $this->memoryCache->get($key);
    $island_configuration = $this->memoryCache->get($island_configuration_key);

    if ($island_configuration === FALSE) {
      $island_configuration = $this->builder->getProfile()->getIslandConfigurations();
      $this->memoryCache->set($island_configuration_key, $island_configuration);
    }
    else {
      $island_configuration = $island_configuration->data;
    }

    if ($island_enabled === FALSE) {
      $island_enabled = $this->builder->getProfile()->getIslandEnabled();
      $this->memoryCache->set($key, $island_enabled);
    }
    else {
      $island_enabled = $island_enabled->data;
    }

    $event = new DisplayBuilderEvent($builder_id, $island_enabled, $island_configuration, $data, $instance_id, $parent_id, $this->islandId);
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
      // instanceId here is the entry in the State API (so the equivalent of
      // builderId in the State Manager), not the specific node we are
      // manipulating in the sources data tree.
      // @see https://www.drupal.org/project/display_builder/issues/3538360
      'instanceId' => (string) $this->builder->id(),
    ];
    $collection = $this->sharedTempStoreFactory->get($this::SSE_COLLECTION);
    $collection->set(\sprintf('%s_latest', (string) $this->builder->id()), $state);
  }

}
