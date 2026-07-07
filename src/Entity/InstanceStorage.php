<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Instance entity storage handler.
 */
class InstanceStorage extends SqlContentEntityStorage {

  private const MAX_HISTORY = 20;

  /**
   * Load the last past revision as default.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   The default entity revision.
   */
  public function undo(RevisionableInterface $entity): RevisionableInterface {
    $past = $this->getPast($entity);

    if (\count($past) === 0) {
      return $entity;
    }

    // @todo Use array_last() once we drop Drupal 11 support.
    $previous = $past[\array_key_last($past)] ?? NULL;

    if ($previous) {
      $previous->isDefaultRevision(TRUE);
      $previous->save();
      $entity->isDefaultRevision(FALSE);
      $entity->save();
    }

    return $previous;
  }

  /**
   * Load the first future revision as default.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   The default entity revision.
   */
  public function redo(RevisionableInterface $entity): RevisionableInterface {
    $future = $this->getFuture($entity);

    if (\count($future) === 0) {
      return $entity;
    }

    // @todo Use array_first() once we drop Drupal 11 support.
    $next = $future[\array_key_first($future)] ?? NULL;

    if ($next) {
      $next->isDefaultRevision(TRUE);
      $next->save();
      $entity->isDefaultRevision(FALSE);
      $entity->save();
    }

    return $next;
  }

  /**
   * Delete all revisions except the default one.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity.
   */
  public function clear(RevisionableInterface $entity): void {
    $query = $this->getAllRevisionsQuery($entity);
    $query->condition('revision', (int) $entity->getRevisionId(), '<>');
    // QueryInterface::execute() returns an integer for count queries or an
    // array of ids.
    /** @var array $revisions */
    $revisions = $query->execute();

    foreach (\array_keys($revisions) as $vid) {
      $this->deleteRevision($vid);
    }

    $entity->save();
  }

  /**
   * Get the past revisions.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface[]
   *   An array of entity revisions keyed by their revision ID, or an empty
   *   array if none found.
   */
  public function getPast(RevisionableInterface $entity): array {
    $query = $this->getAllRevisionsQuery($entity);
    // Compare revision using vid, the older revision has smaller ID.
    $query->condition('revision', (int) $entity->getRevisionId(), '<');
    // QueryInterface::execute() returns an integer for count queries or an
    // array of ids.
    /** @var array $revisions */
    $revisions = $query->execute();
    $revisions = $this->loadMultipleRevisions(\array_keys($revisions));

    return $revisions;
  }

  /**
   * Get the future revisions.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface[]
   *   An array of entity revisions keyed by their revision ID, or an empty
   *   array if none found.
   */
  public function getFuture(RevisionableInterface $entity): array {
    $query = $this->getAllRevisionsQuery($entity);
    // Compare revision using vid, the newer revision has bigger ID.
    $query->condition('revision', (int) $entity->getRevisionId(), '>');
    // QueryInterface::execute() returns an integer for count queries or an
    // array of ids.
    /** @var array $revisions */
    $revisions = $query->execute();
    $revisions = $this->loadMultipleRevisions(\array_keys($revisions));

    return $revisions;
  }

  /**
   * Get users.
   *
   * All users which have authored a step in present, past or future, with the
   * most recent date of action.
   *
   * @param \Drupal\Core\Entity\RevisionLogInterface $entity
   *   A revisionable entity.
   *
   * @return array
   *   Each key is an User entity ID, each value is a timestamp.
   */
  public function getUsers(RevisionLogInterface $entity): array {
    // An associative array where keys are User IDs and values are timestamps.
    $users = [];
    // QueryInterface::execute() returns an integer for count queries or an
    // array of ids.
    /** @var array $revisions */
    $revisions = $this->getAllRevisionsQuery($entity)->execute();
    $revisions = $this->loadMultipleRevisions(\array_keys($revisions));

    foreach ($revisions as $step) {
      /** @var \Drupal\display_builder\InstanceInterface $step */
      // User ID is 0 when anonymous and NULL if not set or user was deleted.
      // Let's standardize around anonymous so every revision has an user.
      $user_id = (int) $step->getRevisionUserId();

      if (!isset($users[$user_id]) || $step->getRevisionCreationTime() > $users[$user_id]) {
        $users[$user_id] = (int) $step->getRevisionCreationTime();
      }
    }

    return $users;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity): mixed {
    /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
    $return = parent::doPreSave($entity);
    $isNewRevision = $entity->isNewRevision();
    $original = $entity->getOriginal();

    if ($original && $isNewRevision) {
      $this->clearFuture($original);
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  protected function doPostSave(EntityInterface $entity, $update): void {
    /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
    $isNewRevision = $entity->isNewRevision();
    parent::doPostSave($entity, $update);

    if ($isNewRevision) {
      $this->trimPast($entity);
    }
  }

  /**
   * Get query for all revisions.
   */
  private function getAllRevisionsQuery(RevisionableInterface $entity): QueryInterface {
    $query = $this->getQuery()->accessCheck(FALSE)->allRevisions();

    return $query->condition('id', $entity->id())->sort('revision', 'ASC');
  }

  /**
   * Delete all future revisions.
   */
  private function clearFuture(RevisionableInterface $entity): void {
    foreach (\array_keys($this->getFuture($entity)) as $vid) {
      $this->deleteRevision($vid);
    }
  }

  /**
   * Keep only the last x history.
   */
  private function trimPast(RevisionableInterface $entity): void {
    $past = $this->getPast($entity);

    if (\count($past) > self::MAX_HISTORY) {
      $oldest_revision_id = \array_key_first($past);
      $this->deleteRevision($oldest_revision_id);
    }
  }

}
