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
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param array|null $data
   *   The data.
   * @param string|null $instance_id
   *   Optional instance ID.
   * @param string|null $parent_id
   *   Optional parent ID.
   * @param string|null $current_island_id
   *   Current island ID which trigger action.
   *
   * @return \Drupal\display_builder\Event\DisplayBuilderEvent
   *   The event.
   */
  protected function createEventWithEnabledIsland($event_id, InstanceInterface $builder, $data, $instance_id, $parent_id, $current_island_id): DisplayBuilderEvent {
    $builder_id = (string) $builder->id();
    $key = \sprintf('db_%s_island_enable', $builder_id);
    $island_configuration_key = \sprintf('db_%s_island_configuration', $builder_id);
    $island_enabled = $this->memoryCache->get($key);
    $island_configuration = $this->memoryCache->get($island_configuration_key);

    if ($island_configuration === FALSE) {
      $island_configuration = $builder->getProfile()->getIslandConfigurations();
      $this->memoryCache->set($island_configuration_key, $island_configuration);
    }
    else {
      $island_configuration = $island_configuration->data;
    }

    if ($island_enabled === FALSE) {
      $island_enabled = $builder->getProfile()->getIslandEnabled();
      $this->memoryCache->set($key, $island_enabled);
    }
    else {
      $island_enabled = $island_enabled->data;
    }

    $event = new DisplayBuilderEvent($builder_id, $island_enabled, $island_configuration, $data, $instance_id, $parent_id, $current_island_id);
    $this->eventDispatcher->dispatch($event, $event_id);

    return $event;
  }

  /**
   * Save data for SSE.
   *
   * @param string $event_id
   *   The event ID.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   */
  protected function saveSseData(string $event_id, InstanceInterface $builder): void {
    if (!\in_array($event_id, $this::SSE_EVENTS, TRUE)) {
      return;
    }

    $state = [
      'username' => (string) $this->currentUser()->getDisplayName(),
      'sessionId' => $this->session->getId(),
      'timestamp' => $this->time->getRequestTime(),
      // instanceId here is the entry in the State API (so the equivalent of
      // builderId in the State Manager), not the specific node we are
      // manipulating in the sources data tree.
      // @see https://www.drupal.org/project/display_builder/issues/3538360
      'instanceId' => (string) $builder->id(),
    ];
    $collection = $this->sharedTempStoreFactory->get($this::SSE_COLLECTION);
    $collection->set(\sprintf('%s_latest', (string) $builder->id()), $state);
  }

}
