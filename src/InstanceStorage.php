<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\display_builder\Entity\Instance;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for content entity storage handlers.
 */
class InstanceStorage extends EntityStorageBase implements EntityStorageInterface {

  private const STORAGE_PREFIX = 'display_builder_';

  private const STORAGE_INDEX = 'display_builder_index';

  /**
   * State API.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, MemoryCacheInterface $memory_cache, StateInterface $state) {
    $this->state = $state;
    parent::__construct($entity_type, $memory_cache);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.memory_cache'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createFromImplementation(WithDisplayBuilderInterface $implementation): EntityInterface {
    $data = [
      'id' => $implementation->getInstanceId(),
      'profileId' => $implementation->getDisplayBuilder()->id(),
      'contexts' => $implementation->getInitialContext(),
    ];

    $present = $implementation->getInitialSources();
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->create($data);
    $instance->setNewPresent($present, 'Initialization of the display builder.');

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
    $this->state->set(self::STORAGE_PREFIX . $entity->id() . '_hash', $entity->getCurrent()->hash ?? '');

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
      $this->state->delete(self::STORAGE_PREFIX . $id . '_hash');
    }
  }

}
