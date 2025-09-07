<?php

declare(strict_types=1);

namespace Drupal\display_builder\Event;

/**
 * Defines events for the display builder.
 *
 * @see \Drupal\display_builder\DisplayBuilderEvent
 */
final class DisplayBuilderEvents {

  public const ON_UPDATE = 'onUpdate';

  public const ON_ATTACH_TO_ROOT = 'onAttachToRoot';

  public const ON_ATTACH_TO_SLOT = 'onAttachToSlot';

  public const ON_MOVE = 'onMove';

  public const ON_ACTIVE = 'onActive';

  public const ON_DELETE = 'onDelete';

  public const ON_HISTORY_CHANGE = 'onHistoryChange';

  public const ON_PRESET_SAVE = 'onPresetSave';

  public const ON_SAVE = 'onSave';

  public const ON_CONTENT_UPDATE = 'onContentUpdate';

}
