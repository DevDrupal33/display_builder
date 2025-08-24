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
use Drupal\display_builder\StateManager\StateManagerInterface;
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
    protected StateManagerInterface $stateManager,
    protected EventDispatcherInterface $eventDispatcher,
    protected MemoryCacheInterface $memoryCache,
    protected RendererInterface $renderer,
    protected TimeInterface $time,
    #[Autowire(service: 'tempstore.shared')] protected SharedTempStoreFactory $sharedTempStoreFactory,
    protected SessionInterface $session,
  ) {}

  /**
   * Returns the display builder by builder ID.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return \Drupal\display_builder\DisplayBuilderInterface
   *   The display builder instance.
   */
  protected function getDisplayBuilder(string $builder_id): DisplayBuilderInterface {
    if ($this->displayBuilder !== NULL) {
      return $this->displayBuilder;
    }
    $builder_config_id = $this->stateManager->getEntityConfigId($builder_id);
    $display_builder = $this->entityTypeManager()->getStorage('display_builder')
      ->load($builder_config_id);
    // dpm($builder_config_id);
    // dpm($display_builder);
    \assert($display_builder instanceof DisplayBuilderInterface);
    $this->displayBuilder = $display_builder;

    return $this->displayBuilder;
  }

  /**
   * Creates a display builder event with enabled islands only.
   *
   * Use a cache to avoid loading all the builder configuration.
   *
   * @param string $event_id
   *   The event ID.
   * @param string $builder_id
   *   The builder ID.
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
  protected function createEventWithEnabledIsland($event_id, $builder_id, $data, $instance_id, $parent_id, $current_island_id): DisplayBuilderEvent {
    $key = \sprintf('db_%s_island_enable', $builder_id);
    $island_configuration_key = \sprintf('db_%s_island_configuration', $builder_id);
    $island_enabled = $this->memoryCache->get($key);
    $island_configuration = $this->memoryCache->get($island_configuration_key);

    if ($island_configuration === FALSE) {
      $island_configuration = $this->getDisplayBuilder($builder_id)->getIslandConfigurations();
      $this->memoryCache->set($island_configuration_key, $island_configuration);
    }
    else {
      $island_configuration = $island_configuration->data;
    }

    if ($island_enabled === FALSE) {
      $island_enabled = $this->getDisplayBuilder($builder_id)->getIslandEnabled();
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
   * @param string $builder_id
   *   The builder ID.
   */
  protected function saveSseData(string $event_id, string $builder_id): void {
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
      'instanceId' => $builder_id,
    ];
    $collection = $this->sharedTempStoreFactory->get($this::SSE_COLLECTION);
    $collection->set(\sprintf('%s_latest', $builder_id), $state);
  }

}
