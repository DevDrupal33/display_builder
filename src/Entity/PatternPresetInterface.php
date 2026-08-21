<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Pattern preset entity type.
 */
interface PatternPresetInterface extends ConfigEntityInterface {

  /**
   * Get the preset group.
   *
   * @return string|null
   *   The group name of the preset or null if not set.
   */
  public function getGroup(): ?string;

  /**
   * Return the ready to use sources.
   *
   * This is not the same as DisplayBuildableInterface::getSources() because
   * the root level is a single nestable source plugin instead of a list.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   (Optional) Contexts for the sources.
   * @param bool $fillNodeId
   *   (Optional) Set instance_id on all children. Default to TRUE.
   *
   * @return array
   *   The preset data.
   */
  public function getSources(array $contexts = [], bool $fillNodeId = TRUE): array;

  /**
   * Get summary.
   *
   * The summary of a preset is the summary of its root source.
   *
   * @return string
   *   The summary of the preset.
   */
  public function getSummary(): string;

  /**
   * Get the contexts from the sources stored in the preset.
   *
   * @return \Drupal\Core\Plugin\Context\ContextDefinition[]
   *   Context definitions of the sources.
   */
  public function getContextDefinitions(): array;

  /**
   * Check if the pattern context is compatible with the display.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   Contexts if already accessible, keyed by context name.
   *
   * @return bool
   *   True if required, False otherwise.
   */
  public function areContextsSatisfied(array $contexts): bool;

}
