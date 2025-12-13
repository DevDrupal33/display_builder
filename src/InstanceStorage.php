<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\display_builder\Entity\Instance;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for content entity storage handlers.
 */
class InstanceStorage extends EntityStorageBase implements RevisionableStorageInterface {

  private const STORAGE_PREFIX = 'display_builder_';

  private const STORAGE_INDEX = 'display_builder_index';

  /**
   * State API.
   */
  protected StateInterface $state;

  /**
   * Current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, MemoryCacheInterface $memory_cache, StateInterface $state, AccountInterface $current_user) {
    $this->state = $state;
    $this->currentUser = $current_user;
    parent::__construct($entity_type, $memory_cache);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.memory_cache'),
      $container->get('state'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createFromImplementation(DisplayBuildableInterface $implementation): EntityInterface {
    $data = $implementation->getInitialSources();
    $present = new HistoryStep(
      $data,
      Instance::getUniqId($data),
      'Initialization of the display builder.',
      \time(),
      (int) $this->currentUser->id(),
    );
    $data = [
      'id' => $implementation->getInstanceId(),
      'profileId' => $implementation->getProfile()->id(),
      'contexts' => $implementation->getInitialContext(),
      'present' => $present,
    ];

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->create($data);

    // If we get the data directly from config or content, the data is
    // considered as already saved.
    // If we convert it from other tools, or import it from other places, the
    // user needs to save it themselves after retrieval.
    if ($implementation->getSources()) {
      $instance->setSave($implementation->getSources());
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id) {
    $this->state->resetCache();

    return parent::loadUnchanged($id);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\RevisionableStorageInterface
   */
  public function createRevision(RevisionableInterface $entity, mixed $default = TRUE): RevisionableInterface {
    // @todo implements
    return $entity;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\RevisionableStorageInterface
   */
  public function loadRevision(mixed $revision_id): ?RevisionableInterface {
    return $this->loadMultipleRevisions([$revision_id])[0];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\RevisionableStorageInterface
   */
  public function loadMultipleRevisions(array $revision_ids): array {
    if (empty($revision_ids)) {
      return [];
    }
    $revisions = [];

    foreach ($this->doLoadMultiple() as $instance) {
      /** @var \Drupal\display_builder\Entity\Instance $instance */
      $data = $instance->toArray();
      $steps = \array_merge($data['past'], [$data['present']], $data['future']);

      foreach ($steps as $step) {
        if (!\in_array($step->hash, $revision_ids, TRUE)) {
          continue;
        }
        // @todo set the step/revision
        $revisions[] = $instance;
        $revision_ids = \array_filter($revision_ids, static function ($value) use ($step) {
          return $value !== $step->hash;
        });

        if (empty($revision_ids)) {
          return $revisions;
        }
      }
    }

    return $revisions;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\RevisionableStorageInterface
   */
  public function loadRevisionUnchanged(mixed $revision_id): ?EntityInterface {
    // @todo what is the difference with ::loadRevision()?
    return $this->loadRevision($revision_id);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\RevisionableStorageInterface
   */
  public function deleteRevision(mixed $revision_id): void {
    // @todo implements
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\RevisionableStorageInterface
   */
  public function getLatestRevisionId(mixed $entity_id): int|string|null {
    $data = $this->load($entity_id)->toArray();

    // @todo is it really the last item?
    return $data['future'][\array_key_last($data['future'])]->hash;
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    if ($entity->isNew()) {
      return FALSE;
    }

    return (bool) $this->state->get(self::STORAGE_PREFIX . $id, NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.null';
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) {
    $entities = [];

    if ($ids === NULL) {
      $ids = \array_keys($this->state->get(self::STORAGE_INDEX, []));
    }

    foreach ($ids as $id) {
      if ($data = $this->state->get(self::STORAGE_PREFIX . $id, NULL)) {
        $entities[$id] = Instance::create($data);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity): bool|int {
    /** @var \Drupal\display_builder\InstanceInterface $entity */

    $display_builder_list = $this->state->get(self::STORAGE_INDEX, []);

    if (!isset($display_builder_list[$entity->id()])) {
      $display_builder_list[$entity->id()] = '';
    }

    $this->state->set(self::STORAGE_INDEX, $display_builder_list);
    $this->state->set(self::STORAGE_PREFIX . $entity->id(), $entity->toArray());

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities): void {
    foreach ($entities as $entity) {
      $id = (string) $entity->id();
      $display_builder_list = $this->state->get(self::STORAGE_INDEX, []);
      unset($display_builder_list[$id]);

      $this->state->set(self::STORAGE_INDEX, $display_builder_list);
      $this->state->delete(self::STORAGE_PREFIX . $id);
    }
  }

}
