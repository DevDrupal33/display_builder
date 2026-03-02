<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\display_builder\Entity\Instance;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for content entity storage handlers.
 */
class InstanceStorage extends ContentEntityStorageBase implements InstanceStorageInterface {

  private const STORAGE_INDEX = 'display_builder_index';

  private const STORAGE_PREFIX = 'display_builder_';

  /**
   * Current user.
   */
  protected AccountInterface $currentUser;

  /**
   * State API.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    StateInterface $state,
    AccountInterface $current_user,
  ) {
    $this->state = $state;
    $this->currentUser = $current_user;
    parent::__construct($entity_type, $entity_field_manager, $cache, $memory_cache, $entity_type_bundle_info);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
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
  public function loadUnchanged($id): ?EntityInterface {
    $this->state->resetCache();

    return parent::loadUnchanged($id);
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? FALSE : 0;
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

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL): array {
    $entities = [];

    if ($ids === NULL) {
      $ids = \array_keys($this->state->get(self::STORAGE_INDEX, []));
    }

    foreach ($ids as $id) {
      if ($data = $this->state->get(self::STORAGE_PREFIX . $id, NULL)) {
        // @todo update once we manage proper revisions and translations.
        $entities[$id] = new Instance($data, 'display_builder_instance');
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
  protected function getQueryServiceName(): string {
    return 'entity.query.null';
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity): bool {
    if ($entity->isNew()) {
      return FALSE;
    }

    return (bool) $this->state->get(self::STORAGE_PREFIX . $id, NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition): void {}

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, mixed $batch_size): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultipleRevisionsFieldItems(mixed $revision_ids): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems(mixed $entities): void {}

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision): void {}

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []): void {}

  /**
   * {@inheritdoc}
   */
  protected function doPreSave(EntityInterface $entity): int|string|null {
    return $entity->id();
  }

}
