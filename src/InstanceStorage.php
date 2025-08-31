<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for content entity storage handlers.
 */
class InstanceStorage extends EntityStorageBase implements EntityStorageInterface {

  /**
   * State manager.
   */
  protected StateManagerInterface $stateManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, MemoryCacheInterface $memory_cache, StateManagerInterface $state_manager) {
    $this->stateManager = $state_manager;
    parent::__construct($entity_type, $memory_cache);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.memory_cache'),
      $container->get('display_builder.state_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createFromImplementation(WithDisplayBuilderInterface $implementation): EntityInterface {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->create(['id' => $implementation->getInstanceId()]);
    $instance->setRuntimeProfileId((string) $implementation->getDisplayBuilder()->id());
    $instance->setRuntimeData($implementation->getInitialSources());
    // If we get the data directly from config or content, the data is
    // considered as already saved.
    // If we convert it from other tools, or import it from other places, the
    // user needs to save it themselves after retrieval.
    $instance->setRuntimeSaved((bool) $implementation->getSources());
    $instance->setRuntimeContexts($implementation->getInitialContext());

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    if ($entity->isNew()) {
      return FALSE;
    }

    return (bool) $this->stateManager->load((string) $id);
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'display_builder.instances';
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(?array $ids = NULL) {
    $entities = [];

    if ($ids === NULL) {
      $ids = \array_keys($this->stateManager->loadAll());
    }

    foreach ($ids as $id) {
      if ($this->stateManager->load($id)) {
        $entities[$id] = Instance::create([
          'id' => $id,
        ]);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values): EntityInterface {
    // We create only the object here. According to the Entity API, we don't
    // save to storage during creation.
    $builder_id = $values['id'] ?? $values['builder_id'] ?? '';

    return parent::doCreate(['id' => $builder_id]);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity): bool|int {
    /** @var \Drupal\display_builder\InstanceInterface $entity */

    $data = $entity->getRuntimeData();

    if ($this->stateManager->load((string) $id)) {
      $this->stateManager->save((string) $entity->id(), $data, $entity->getLogMessage());

      return TRUE;
    }
    // First save.
    $this->stateManager->create((string) $id, $entity->getRuntimeProfileId(), $data, $entity->getRuntimeContexts());

    if ($entity->getRuntimeSaved()) {
      $this->stateManager->setSave((string) $id, $data);
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities): void {
    foreach ($entities as $entity) {
      $this->stateManager->delete((string) $entity->id());
    }
  }

}
