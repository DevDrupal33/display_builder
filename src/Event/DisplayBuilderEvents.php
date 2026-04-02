<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

/**
 * Defines events for the display builder.
 *
 * Event name = island method name contract
 * ----------------------------------------
 * Every constant value in this class MUST equal the camelCase method name
 * defined on IslandEventSubscriberInterface. For example:
 *
 * @code
 *   public const ON_PUBLISH = 'onPublish';
 *   // IslandEventSubscriberInterface::onPublish() must exist.
 *
 * @endcode
 *
 * This convention lets IslandFanOutTrait::dispatchToIslands() invoke the
 * correct island method generically using __FUNCTION__ — no explicit map
 * needed. Any new event added here or in a submodule events class MUST
 * follow the same rule; a LogicException is thrown at runtime otherwise.
 *
 * Typed event classes
 * -------------------
 * Each event is dispatched as a typed subclass of DisplayBuilderEvent that
 * carries only the data relevant to that event:
 * - DisplayBuilderEvent
 *   - single node_id (ON_ATTACH_TO_ROOT, ON_MOVE, ON_UPDATE)
 * - DisplayBuilderEvent
 *   — node_id + parent_id (ON_ATTACH_TO_SLOT)
 * - DisplayBuilderEvent
 *   — optional parent_id (ON_DELETE)
 * - DisplayBuilderEvent
 *   — data array (ON_ACTIVE, ON_PUBLISH)
 * - DisplayBuilderEvent
 *   — instance only (ON_HISTORY_CHANGE, ON_RESTORE, ON_REVERT, ON_PRESET_SAVE)
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvent
 * @see \Drupal\display_builder\Island\IslandEventSubscriberInterface
 * @see \Drupal\display_builder\Island\IslandFanOutTrait
 */
final class DisplayBuilderEvents {

  /**
   * Fired when a node's source configuration is updated in-place.
   */
  public const ON_UPDATE = 'onUpdate';

  /**
   * Fired when a new node is attached directly at root level.
   */
  public const ON_ATTACH_TO_ROOT = 'onAttachToRoot';

  /**
   * Fired when a new node is attached into a component slot.
   */
  public const ON_ATTACH_TO_SLOT = 'onAttachToSlot';

  /**
   * Fired when an existing node is moved (to root or into a slot).
   */
  public const ON_MOVE = 'onMove';

  /**
   * Fired when a node becomes the active/focused node in the UI.
   */
  public const ON_ACTIVE = 'onActive';

  /**
   * Fired when a node is removed from the tree.
   */
  public const ON_DELETE = 'onDelete';

  /**
   * Fired when the history pointer changes (undo / redo).
   */
  public const ON_HISTORY_CHANGE = 'onHistoryChange';

  /**
   * Fired when the builder is restored to its last saved state.
   *
   * Discards all unsaved changes since the last explicit save.
   */
  public const ON_RESTORE = 'onRestore';

  /**
   * Fired when an entity view override is reverted to the base display config.
   *
   * Handled by display_builder_entity_view; clears the field override and
   * reloads sources from the entity view display configuration.
   */
  public const ON_REVERT = 'onRevert';

  /**
   * Fired when the builder state is saved to the backing config entity.
   */
  public const ON_PUBLISH = 'onPublish';

  /**
   * Fired when a node is saved as a reusable preset.
   */
  public const ON_PRESET_SAVE = 'onPresetSave';

}
