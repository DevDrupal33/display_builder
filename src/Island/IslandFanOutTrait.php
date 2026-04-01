<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\display_builder\Event\DisplayBuilderEvent;

/**
 * Provides the canonical island fan-out algorithm for event subscribers.
 *
 * Use this trait in any Symfony event subscriber that needs to forward a
 * display builder event to every enabled island plugin. The using class must
 * expose an `IslandPluginManagerInterface $islandManager` property.
 *
 * The event name = island method name contract
 * ---------------------------------------------------
 * Every constant in DisplayBuilderEvents (and any submodule events class) is
 * intentionally set to the camelCase method name defined on
 * IslandEventSubscriberInterface (e.g. ON_PUBLISH = 'onPublish'). This
 * single convention lets dispatchToIslands() call the right island method
 * generically, without an explicit map. Any new event MUST follow this rule.
 *
 * Island method → required sub-interface map (METHOD_INTERFACE_MAP)
 * -------------------------------------------------------------------
 * dispatchToIslands() checks the map to determine which sub-interface an
 * island must implement before its method is called. Islands that extend
 * IslandPluginBase pass all checks automatically because IslandPluginBase
 * implements the full IslandEventSubscriberInterface aggregate. Custom islands
 * that implement only a subset of sub-interfaces are only invoked for their
 * covered events, making the fan-out selective and safe.
 *
 * Submodule extension
 * -------------------------------------------------------------------
 * For events defined in a submodule's own events class (e.g. a hypothetical
 * IslandFooEventInterface::onFoo), use dispatchCustomEventToIslands() instead.
 * It accepts an explicit $island_interface argument so the instanceof check
 * resolves to the correct submodule-defined interface.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvents
 * @see \Drupal\display_builder\Island\IslandEventSubscriberInterface
 * @see \Drupal\display_builder\Island\IslandStructureEventsInterface
 * @see \Drupal\display_builder\Island\IslandLifecycleEventsInterface
 * @see \Drupal\display_builder\Island\IslandSaveEventsInterface
 * @see \Drupal\display_builder\Island\IslandActiveEventInterface
 */
trait IslandFanOutTrait {

  /**
   * Maps each island method name to the sub-interface that declares it.
   *
   * This constant drives the instanceof check in dispatchToIslands(). Any
   * method not listed here is treated as unknown and a LogicException is
   * thrown, catching contract violations early at development time.
   *
   * Keep in sync with IslandEventSubscriberInterface sub-interfaces.
   *
   * @var array<string, class-string>
   */
  private const METHOD_INTERFACE_MAP = [
    // IslandStructureEventsInterface.
    'onAttachToRoot' => IslandStructureEventsInterface::class,
    'onAttachToSlot' => IslandStructureEventsInterface::class,
    'onMove' => IslandStructureEventsInterface::class,
    'onUpdate' => IslandStructureEventsInterface::class,
    'onDelete' => IslandStructureEventsInterface::class,
    // IslandLifecycleEventsInterface.
    'onHistoryChange' => IslandLifecycleEventsInterface::class,
    'onRestore' => IslandLifecycleEventsInterface::class,
    'onRevert' => IslandLifecycleEventsInterface::class,
    // IslandSaveEventsInterface.
    'onPublish' => IslandSaveEventsInterface::class,
    'onPresetSave' => IslandSaveEventsInterface::class,
    // IslandActiveEventInterface.
    'onActive' => IslandActiveEventInterface::class,
  ];

  /**
   * Forwards an event to every enabled island plugin.
   *
   * Each island is only invoked when it implements the sub-interface that
   * declares $method (see METHOD_INTERFACE_MAP). Islands extending
   * IslandPluginBase always pass this check because the base implements the
   * full IslandEventSubscriberInterface aggregate.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event being dispatched.
   * @param string $method
   *   The IslandEventSubscriberInterface method to invoke on each island.
   *   Must be listed in METHOD_INTERFACE_MAP.
   * @param array $parameters
   *   Extra arguments to pass after the instance (event-specific).
   *
   * @throws \LogicException
   *   Thrown when $method is not in METHOD_INTERFACE_MAP, catching event name
   *   / method name contract violations early at development time.
   */
  protected function dispatchToIslands(DisplayBuilderEvent $event, string $method, array $parameters = []): void {
    if (!\array_key_exists($method, self::METHOD_INTERFACE_MAP)) {
      throw new \LogicException(\sprintf(
        'Island fan-out called with method "%s" which is not listed in %s::METHOD_INTERFACE_MAP. Ensure the event constant value equals the island method name and add the method to the appropriate sub-interface.',
        $method,
        self::class,
      ));
    }

    $this->dispatchCustomEventToIslands($event, $method, self::METHOD_INTERFACE_MAP[$method], $parameters);
  }

  /**
   * Forwards an event to islands implementing a specific interface.
   *
   * Use this method in submodule event subscribers when the island method and
   * its required interface are defined outside of
   * IslandEventSubscriberInterface (e.g. a submodule-specific island capability
   * interface).
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event being dispatched.
   * @param string $method
   *   The method to invoke on each qualifying island.
   * @param class-string $island_interface
   *   Islands that do not implement this interface are skipped.
   * @param array $parameters
   *   Extra arguments to pass after the instance (event-specific).
   */
  protected function dispatchCustomEventToIslands(DisplayBuilderEvent $event, string $method, string $island_interface, array $parameters = []): void {
    $args = \array_merge([$event->getInstance()], $parameters);

    $configuration = $event->getIslandConfiguration();
    $contexts = $event->getInstance()->getContexts();
    $islands = $this->islandManager->createInstances($this->islandManager->getDefinitions(), $contexts, $configuration);
    $island_enabled = $event->getEnabledIslands();

    foreach ($islands as $island_id => $island) {
      if (!isset($island_enabled[$island_id])) {
        continue;
      }

      // Skip the island that triggered this HTMX event: it is already in the
      // expected state and does not need to be swapped out-of-band.
      // @see \Drupal\display_builder\Controller\ApiControllerBase::islandId
      if ($island_id === $event->getCurrentIslandId()) {
        continue;
      }

      if (!$island instanceof $island_interface) {
        continue;
      }

      $result = $island->{$method}(...$args);

      if ($result !== NULL) {
        $event->appendResult($island_id, $result);
      }
    }
  }

}
