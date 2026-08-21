<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

/**
 * Defines events for the display builder.
 *
 * @see \Drupal\display_builder\Event\DisplayBuilderEvent
 * @see \Drupal\display_builder\Island\IslandEventSubscriberInterface
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
   * Fired when an entity view override is reverted to the base display.
   *
   * Handled by DisplayBuildableOverrideInterface; clears the override and
   * reloads sources from the entity view display configuration.
   */
  public const ON_REVERT = 'onRevert';

  /**
   * Fired when the builder state is saved to the permanent storage.
   */
  public const ON_PUBLISH = 'onPublish';

  /**
   * Fired when a node is saved as a reusable preset.
   */
  public const ON_PRESET_SAVE = 'onPresetSave';

}
