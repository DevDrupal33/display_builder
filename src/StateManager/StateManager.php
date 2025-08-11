<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * The business logic of the state management.
 */
class StateManager implements StateManagerInterface {

  use StringTranslationTrait;

  /**
   * Path index.
   *
   * For each builder, a mapping where each key is an slot source instance ID
   * and each value is the path where this instance is located in the data
   * state.
   */
  protected array $pathIndex = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected StorageInterface $stateStorage,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SampleEntityGeneratorInterface $sampleEntityGenerator,
    protected SlotSourceProxy $slotSourceProxy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function create(string $builder_id, string $entity_config_id, ?array $data, array $contexts): array {
    $this->pathIndex[$builder_id] = [];

    if ($data === NULL) {
      $current_data = $this->getCurrentState($builder_id);

      if (!empty($current_data)) {
        $data = $current_data;
      }
      else {
        $data = [];
      }
    }

    // @todo we should enforce the validity of $data before building in case of
    // invalid data. For example an imported config.
    $data = $this->buildIndexFromSlot($builder_id, [], $data);
    // We do not change the contexts before passing to the storage.
    // We could have modified the entity contexts
    // to store only the entity id, type and bundle.
    // entity id null would mean sample entity.
    // But instead, we will only modify the contexts
    // when loaded from the storage.
    $this->stateStorage->init($builder_id, $entity_config_id, $data, $contexts);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityConfigId(string $builder_id): string {
    return $this->stateStorage->getEntityConfigId($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(string $builder_id): ?array {
    $contexts = $this->stateStorage->getContexts($builder_id);

    return \is_array($contexts) ? $this->refreshContexts($contexts) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityConfigId(string $builder_id, string $entity_config_id): void {
    $this->stateStorage->setEntityConfigId($builder_id, $entity_config_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setContexts(string $builder_id, array $contexts): void {
    $this->stateStorage->setContexts($builder_id, $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function canSaveContextsRequirement(string $builder_id, ?array $contexts = NULL): bool {
    $contexts ??= $this->getContexts($builder_id);

    if (!\array_key_exists('context_requirements', $contexts)
      || !($contexts['context_requirements'] instanceof RequirementsContext)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasSaveContextsRequirement(string $builder_id, string $key, ?array $contexts = NULL): bool {
    $contexts ??= $this->getContexts($builder_id);

    if (!\array_key_exists('context_requirements', $contexts)
      || !($contexts['context_requirements'] instanceof RequirementsContext)
      || !$contexts['context_requirements']->hasValue($key)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $builder_id): ?array {
    return $this->stateStorage->load($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAll(): array {
    return $this->stateStorage->loadAll();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $builder_id): void {
    $this->stateStorage->delete($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->stateStorage->deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrent(string $builder_id): array {
    return $this->stateStorage->getCurrent($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentHash(string $builder_id): string {
    return $this->stateStorage->getCurrentHash($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentState(string $builder_id): array {
    return $this->stateStorage->getCurrentState($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function hasSave(string $builder_id): bool {
    return $this->stateStorage->hasSave($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setSave(string $builder_id, array $save_data): void {
    $this->stateStorage->setSave($builder_id, $save_data);
  }

  /**
   * {@inheritdoc}
   */
  public function saveIsCurrent(string $builder_id): bool {
    return $this->stateStorage->saveIsCurrent($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCountPast(string $builder_id): int {
    return \count($this->stateStorage->getPast($builder_id));
  }

  /**
   * {@inheritdoc}
   */
  public function getCountFuture(string $builder_id): int {
    return \count($this->stateStorage->getFuture($builder_id));
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $builder_id, string $instance_id): array {
    $root = $this->getCurrentState($builder_id);
    $path = $this->getPath($builder_id, $root, $instance_id);
    $value = NestedArray::getValue($root, $path);

    return $value ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPathIndex(string $builder_id, array $root = []): array {
    if (empty($root)) {
      // When called from the outside, root is not already retrieved.
      // When called from an other method, it is better to pass an already
      // retrieved root, for performance.
      $root = $this->getCurrentState($builder_id);
    }
    // It may be slow to rebuild the index every time we request it. But it is
    // very difficult to maintain an index synchronized with the state storage
    // history.
    $this->buildIndexFromSlot($builder_id, [], $root);

    return $this->pathIndex[$builder_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function attachSourceToRoot(string $builder_id, int $position, string $source_id, array $data, ?array $third_party_settings = NULL): string {
    $data = [
      '_instance_id' => \uniqid(),
      'source_id' => $source_id,
      'source' => $data,
      '_third_party_settings' => $third_party_settings,
    ];

    if ($third_party_settings) {
      $data['_third_party_settings'] = $third_party_settings;
    }

    $root = $this->getCurrentState($builder_id);
    $root = $this->attachToRoot($root, $position, $data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($data, $this->getContexts($builder_id) ?? []);

    $log = $this->t('%instance @source_id has been attached to root', [
      '%instance' => $labelWithSummaryInstance['summary'],
      '@source_id' => $source_id,
    ]);
    $this->stateStorage->setNewPresent($builder_id, $root, $log, FALSE);

    return $data['_instance_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function attachSourceToSlot(string $builder_id, string $parent_id, string $slot_id, int $position, string $source_id, array $data, ?array $third_party_settings = NULL): string {
    $root = $this->getCurrentState($builder_id);
    $data = [
      '_instance_id' => \uniqid(),
      'source_id' => $source_id,
      'source' => $data,
      '_third_party_settings' => $third_party_settings,
    ];

    if ($third_party_settings) {
      $data['_third_party_settings'] = $third_party_settings;
    }

    $root = $this->attachToSlot($builder_id, $root, $parent_id, $slot_id, $position, $data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($data, $this->getContexts($builder_id));
    $labelWithSummaryParent = $this->slotSourceProxy->getLabelWithSummary($this->get($builder_id, $parent_id));

    $log = $this->t("%instance @source_id has been attached to %parent's @slot_id", [
      '%instance' => $labelWithSummaryInstance['summary'],
      '@source_id' => $source_id,
      '%parent' => $labelWithSummaryParent['summary'],
      '@slot_id' => $slot_id,
    ]);
    $this->stateStorage->setNewPresent($builder_id, $root, $log);

    return $data['_instance_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function moveToRoot(string $builder_id, string $instance_id, int $position): bool {
    $root = $this->getCurrentState($builder_id);
    $path = $this->getPath($builder_id, $root, $instance_id);
    $data = NestedArray::getValue($root, $path);

    if (empty($data) || !isset($data['source_id'])) {
      return FALSE;
    }

    $root = $this->doRemove($builder_id, $root, $instance_id);
    $root = $this->attachToRoot($root, $position, $data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($data, $this->getContexts($builder_id));

    $log = $this->t('%instance @thingy has been moved to root', [
      '%instance' => $labelWithSummaryInstance['summary'],
      '@thingy' => $data['source_id'],
    ]);
    $this->stateStorage->setNewPresent($builder_id, $root, $log);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function moveToSlot(string $builder_id, string $instance_id, string $parent_id, string $slot_id, int $position): bool {
    $root = $this->getCurrentState($builder_id);
    $path = $this->getPath($builder_id, $root, $instance_id);
    $data = NestedArray::getValue($root, $path);

    if (empty($data) || !isset($data['source_id'])) {
      return FALSE;
    }

    $parent_slot = \array_slice($path, \count($path) - 3, 1)[0];

    if (($parent_id === $this->getParentId($builder_id, $root, $instance_id)) && ($slot_id === $parent_slot)) {
      // Moving to the same slot is tricky, because we don't want to remove a
      // sibling.
      $slot_path = \array_slice($path, 0, \count($path) - 1);
      $slot = NestedArray::getValue($root, $slot_path);
      $slot = $this->changeInstancePositionInSlot($slot, $instance_id, $position);
      NestedArray::setValue($root, $slot_path, $slot);
    }
    else {
      // Moving to a different slot is easier, we can first delete the previous
      // instance data, and attach it to the new position.
      $root = $this->doRemove($builder_id, $root, $instance_id);
      $root = $this->attachToSlot($builder_id, $root, $parent_id, $slot_id, $position, $data);
    }

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($data, $this->getContexts($builder_id));
    $labelWithSummaryParent = $this->slotSourceProxy->getLabelWithSummary($this->get($builder_id, $parent_id));

    $log = $this->t("%instance @thingy has been moved to %parent's @slot_id", [
      '%instance' => $labelWithSummaryInstance['summary'],
      '@thingy' => $data['source_id'],
      '%parent' => $labelWithSummaryParent['summary'],
      '@slot_id' => $slot_id,
    ]);

    $this->stateStorage->setNewPresent($builder_id, $root, $log);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySettings(string $builder_id, string $instance_id, string $island_id, array $data): void {
    $root = $this->getCurrentState($builder_id);
    $path = $this->getPath($builder_id, $root, $instance_id);
    $existing_data = NestedArray::getValue($root, $path);

    if (!isset($existing_data['_third_party_settings'])) {
      $existing_data['_third_party_settings'] = [];
    }
    $existing_data['_third_party_settings'][$island_id] = $data;
    NestedArray::setValue($root, $path, $existing_data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($existing_data, $this->getContexts($builder_id));

    $log = $this->t('%instance has been updated by @island_id', [
      '%instance' => $labelWithSummaryInstance['summary'],
      '@island_id' => $island_id,
    ]);
    $this->stateStorage->setNewPresent($builder_id, $root, $log);
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(string $builder_id, string $instance_id, string $source_id, array $data): void {
    $root = $this->getCurrentState($builder_id);
    $path = $this->getPath($builder_id, $root, $instance_id);
    $existing_data = NestedArray::getValue($root, $path) ?? [];

    if (!isset($existing_data['_instance_id']) || ($existing_data['_instance_id'] !== $instance_id)) {
      throw new \Exception('Instance ID mismatch');
    }
    $existing_data['source_id'] = $source_id;
    $existing_data['source'] = $data;
    NestedArray::setValue($root, $path, $existing_data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($existing_data, $this->getContexts($builder_id));

    $log = $this->t('%instance has been updated', [
      '%instance' => $labelWithSummaryInstance['summary'],
    ]);
    $this->stateStorage->setNewPresent($builder_id, $root, $log);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(string $builder_id, string $instance_id): void {
    $root = $this->getCurrentState($builder_id);
    $path = $this->getPath($builder_id, $root, $instance_id);
    $data = NestedArray::getValue($root, $path);
    $parent_id = $this->getParentId($builder_id, $root, $instance_id);
    $root = $this->doRemove($builder_id, $root, $instance_id);

    // Get friendly label to display in log instead of ids.
    $labelWithSummaryInstance = $this->slotSourceProxy->getLabelWithSummary($data, $this->getContexts($builder_id));
    $labelWithSummaryParent = empty($parent_id) ? ['summary' => $this->t('root')] : $this->slotSourceProxy->getLabelWithSummary($this->get($builder_id, $parent_id), $this->getContexts($builder_id));

    $log = $this->t('%instance has been removed from %parent', [
      '%instance' => $labelWithSummaryInstance['summary'],
      '%parent' => $labelWithSummaryParent['summary'],
    ]);
    $this->stateStorage->setNewPresent($builder_id, $root, $log, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function undo(string $builder_id): void {
    $this->stateStorage->undo($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function redo(string $builder_id): void {
    $this->stateStorage->redo($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function restore(string $builder_id): void {
    $this->stateStorage->restore($builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(string $builder_id): void {
    $this->stateStorage->clear($builder_id);
  }

  /**
   * In which parent component is the instance?
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $root
   *   The root state.
   * @param string $instance_id
   *   The instance id.
   *
   * @return string
   *   The parent instance id.
   */
  public function getParentId(string $builder_id, array $root, string $instance_id): string {
    $path = $this->getPath($builder_id, $root, $instance_id);
    $length = \count(['source', 'component', 'slots', '{slot_id}', 'sources', '{position}']);
    $parent_path = \array_slice($path, 0, \count($path) - $length);

    return $this->getInstanceId($builder_id, $parent_path);
  }

  /**
   * {@inheritdoc}
   */
  public function save(string $builder_id, array $builder_data, string|TranslatableMarkup $log_message = ''): void {
    $this->stateStorage->setNewPresent($builder_id, $builder_data, $log_message);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers(string $builder_id): array {
    $users = [];
    $storage = $this->stateStorage->load($builder_id);
    $steps = \array_merge(
      $storage['past'],
      [$storage['present']],
      $storage['future']
    );

    foreach ($steps as $step) {
      $user_id = $step['user'] ?? NULL;

      if ($user_id && ($users[$user_id] ?? $step['time'] > 0)) {
        $users[$user_id] = $step['time'];
      }
    }

    return $users;
  }

  /**
   * Get the path to an instance.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $root
   *   The root state.
   * @param string $instance_id
   *   The instance id.
   *
   * @return array
   *   The path, one array item by level.
   */
  public function getPath(string $builder_id, array $root, string $instance_id): array {
    return $this->getPathIndex($builder_id, $root)[$instance_id] ?? [];
  }

  /**
   * Refresh contexts after loaded from storage.
   *
   * @param array $contexts
   *   The contexts.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return array
   *   The refreshed contexts or NULL if no context.
   */
  protected function refreshContexts(array $contexts): ?array {
    foreach ($contexts as &$context) {
      if ($context instanceof EntityContext) {
        // @todo We should use cache entries here
        // with the corresponding cache contexts in it.
        // This may avoid some unnecessary entity loads or generation.
        $entity = $context->getContextValue();

        // Check if sample entity.
        if ($entity->id()) {
          $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->load($entity->id());
        }
        else {
          $entity = $this->sampleEntityGenerator->get($entity->getEntityTypeId(), $entity->bundle());
        }
        $context = (\get_class($context))::fromEntity($entity);
      }
    }

    return $contexts;
  }

  /**
   * Change the position of an instance in a slot.
   *
   * @param array $slot
   *   The slot.
   * @param string $instance_id
   *   The instance id.
   * @param int $to
   *   The new position.
   *
   * @return array
   *   The updated slot.
   */
  protected function changeInstancePositionInSlot(array $slot, string $instance_id, int $to): array {
    foreach ($slot as $position => $source) {
      if ($source['_instance_id'] === $instance_id) {
        $p1 = \array_splice($slot, $position, 1);
        $p2 = \array_splice($slot, 0, $to);

        return \array_merge($p2, $p1, $slot);
      }
    }

    return $slot;
  }

  /**
   * Get the instance ID from a path.
   *
   * @todo may be slow.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $path
   *   The path to the slot.
   */
  protected function getInstanceId(string $builder_id, array $path): string {
    $index = $this->getPathIndex($builder_id);

    foreach ($index as $instance_id => $instance_path) {
      if ($path === $instance_path) {
        return $instance_id;
      }
    }

    return '';
  }

  /**
   * Build the index from a slot.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $path
   *   The path to the slot.
   * @param array $data
   *   (Optional) The slot data.
   *
   * @return array
   *   The slot data with the index updated.
   */
  protected function buildIndexFromSlot(string $builder_id, array $path, array $data = []): array {
    foreach ($data as $index => $source) {
      $source_path = \array_merge($path, [$index]);
      $data[$index] = $this->buildIndexFromInstance($builder_id, $source_path, $source);
    }

    return $data;
  }

  /**
   * Add path to index and add instance ID.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $path
   *   The path to the slot.
   * @param array $data
   *   (Optional) The slot data.
   *
   * @return array
   *   The slot data with the index updated.
   */
  protected function buildIndexFromInstance(string $builder_id, array $path, array $data = []): array {
    // First job: Add missing _instance_id keys.
    $instance_id = $data['_instance_id'] ?? \uniqid();
    $data['_instance_id'] = $instance_id;
    // Second job: Save the path to the index.
    $this->pathIndex[$builder_id][$instance_id] = $path;

    if (!isset($data['source_id'])) {
      return $data;
    }

    // Let's continue the exploration.
    if ($data['source_id'] !== 'component') {
      return $data;
    }

    if (!isset($data['source']['component']['slots'])) {
      return $data;
    }

    foreach ($data['source']['component']['slots'] as $slot_id => $slot) {
      if (!isset($slot['sources'])) {
        continue;
      }
      $slot_path = \array_merge($path, ['source', 'component', 'slots', $slot_id, 'sources']);
      $slot['sources'] = $this->buildIndexFromSlot($builder_id, $slot_path, $slot['sources']);
      $data['source']['component']['slots'][$slot_id] = $slot;
    }

    return $data;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param array $root
   *   The root state.
   * @param int $position
   *   The position where to insert the data.
   * @param array $data
   *   The data to insert.
   *
   * @return array
   *   The updated root state
   */
  private function attachToRoot(array $root, int $position, array $data): array {
    \array_splice($root, $position, 0, [$data]);

    return $root;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param array $root
   *   The root state.
   * @param string $parent_id
   *   The ID of the parent instance.
   * @param string $slot_id
   *   The ID of the slot where to insert the data.
   * @param int $position
   *   The position where to insert the data.
   * @param array $data
   *   The data to insert.
   *
   * @return array
   *   The updated root state
   */
  private function attachToSlot(string $builder_id, array $root, string $parent_id, string $slot_id, int $position, array $data): array {
    $parent_path = $this->getPath($builder_id, $root, $parent_id);
    $slot_path = \array_merge($parent_path, ['source', 'component', 'slots', $slot_id, 'sources']);
    $slot = NestedArray::getValue($root, $slot_path) ?? [];
    \array_splice($slot, $position, 0, [$data]);
    NestedArray::setValue($root, $slot_path, $slot);

    return $root;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $root
   *   The root state.
   * @param string $instance_id
   *   The instance id.
   *
   * @return array
   *   The updated root state
   */
  private function doRemove(string $builder_id, array $root, string $instance_id): array {
    $path = $this->getPath($builder_id, $root, $instance_id);
    NestedArray::unsetValue($root, $path);
    // To avoid non consecutive array keys, we rebuild the value list.
    $slot_path = \array_slice($path, 0, \count($path) - 1);
    $slot = NestedArray::getValue($root, $slot_path);
    NestedArray::setValue($root, $slot_path, \array_values($slot));

    return $root;
  }

}
