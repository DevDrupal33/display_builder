<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

/**
 * Convenience trait combining structure and lifecycle reload event handlers.
 *
 * Composes IslandStructureReloadTrait and IslandLifecycleReloadTrait to provide
 * full reload-on-event behavior for the most common handler groups. All eight
 * implemented methods delegate to reloadWithGlobalData(), which the using
 * island plugin must provide.
 *
 * Plugins that also need to react to onPublish(), onPresetSave(), or
 * onActive() must implement those methods explicitly.
 *
 * This trait is kept as a convenience aggregate for the six island plugins that
 * already use it. New islands should prefer composing the focused sub-traits
 * (IslandStructureReloadTrait, IslandLifecycleReloadTrait) directly.
 *
 * @see \Drupal\display_builder\Island\IslandStructureReloadTrait
 * @see \Drupal\display_builder\Island\IslandLifecycleReloadTrait
 */
trait IslandReloadEventsTrait {

  use IslandStructureReloadTrait;
  use IslandLifecycleReloadTrait;

}
