<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Counts entity updates, so a test can assert what an action costs in saves.
 *
 * A publish that saves its host entity twice leaves the same final state as
 * one that saves it once, so nothing loaded back afterwards can tell them
 * apart. The hook count can. Kept in memory: the hook and the kernel test
 * run in the same process, and this module is also installed on the
 * functional and Playwright sites, where a State write per entity save
 * would be paid for nothing.
 */
final class EntitySaveCounter {

  /**
   * Updates seen so far, keyed by entity type ID.
   *
   * @var array<string, int>
   */
  private array $updates = [];

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $type = $entity->getEntityTypeId();
    $this->updates[$type] = ($this->updates[$type] ?? 0) + 1;
  }

  /**
   * How many times entities of a type were updated so far.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return int
   *   The update count, 0 when none was seen.
   */
  public function count(string $entity_type_id): int {
    return $this->updates[$entity_type_id] ?? 0;
  }

}
