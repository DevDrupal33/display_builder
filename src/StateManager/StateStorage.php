<?php

declare(strict_types=1);

namespace Drupal\display_builder\StateManager;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Save displays in the State.
 */
class StateStorage implements StorageInterface {

  use StringTranslationTrait;

  private const MAX_HISTORY = 10;

  private const STORAGE_PREFIX = 'display_builder_';

  private const STORAGE_INDEX = 'display_builder_index';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected StateInterface $state,
    protected AccountInterface $user,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function init(string $builder_id, string $entity_config_id, array $builder_data, array $contexts): void {
    // @todo DTO to handle this data.
    $builder_data = [
      'id' => $builder_id,
      'entity_config_id' => $entity_config_id,
      'contexts' => $contexts,
      'past' => [],
      'present' => [
        'data' => $builder_data,
        'hash' => self::getUniqId($builder_data),
        'log' => $this->t('Initialization of the display builder.'),
        'time' => \time(),
        'user' => $this->user->id(),
      ],
      'future' => [],
      'save' => NULL,
    ];

    $this->saveData($builder_id, $builder_data);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityConfigId(string $builder_id): string {
    return $this->load($builder_id)['entity_config_id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(string $builder_id): ?array {
    return $this->load($builder_id)['contexts'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityConfigId(string $builder_id, string $entity_config_id): void {
    $builder_data = $this->load($builder_id);
    $builder_data['entity_config_id'] = $entity_config_id;
    $this->saveData($builder_id, $builder_data);
  }

  /**
   * {@inheritdoc}
   */
  public function setContexts(string $builder_id, array $contexts): void {
    $builder_data = $this->load($builder_id);
    $builder_data['contexts'] = $contexts;
    $this->saveData($builder_id, $builder_data);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrent(string $builder_id): array {
    return $this->load($builder_id)['present'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentHash(string $builder_id): string {
    return $this->state->get(self::STORAGE_PREFIX . $builder_id . '_hash', '');
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentState(string $builder_id): array {
    return $this->getCurrent($builder_id)['data'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFuture(string $builder_id): array {
    return $this->load($builder_id)['future'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPast(string $builder_id): array {
    return $this->load($builder_id)['past'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function hasSave(string $builder_id): bool {
    $save = $this->load($builder_id)['save'] ?? [];

    return empty($save) ? FALSE : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setSave(string $builder_id, array $save_data): void {
    $builder_data = $this->load($builder_id);

    $hash = self::getUniqId($save_data);
    $builder_data['save'] = [
      'data' => $save_data,
      'hash' => $hash,
      'time' => \time(),
    ];

    $this->saveData($builder_id, $builder_data);
  }

  /**
   * {@inheritdoc}
   */
  public function saveIsCurrent(string $builder_id): bool {
    $builder_data = $this->load($builder_id);
    $current_hash = $builder_data['present']['hash'] ?? '';
    $save_hash = $builder_data['save']['hash'] ?? NULL;

    return $current_hash === $save_hash;
  }

  /**
   * {@inheritdoc}
   */
  public function setNewPresent(string $builder_id, array $data, string|TranslatableMarkup $log_message = '', bool $check_hash = TRUE): void {
    $builder_data = $this->load($builder_id);
    $hash = self::getUniqId($data);

    // Check if this present is the same to avoid duplicates, for example move
    // to the same place.
    if ($check_hash && $hash === $builder_data['present']['hash']) {
      return;
    }

    // 1. Insert the present at the end of the past.
    $builder_data['past'][] = $builder_data['present'];

    // Keep only the last x history.
    if (\count($builder_data['past']) > self::MAX_HISTORY) {
      \array_shift($builder_data['past']);
    }

    // 2. Set the present to the new state.
    $builder_data['present'] = [
      'data' => $data,
      'hash' => $hash,
      'log' => $log_message,
      'time' => \time(),
      'user' => $this->user->id(),
    ];

    // 3. Clear the future.
    $builder_data['future'] = [];

    $this->saveData($builder_id, $builder_data);
  }

  /**
   * {@inheritdoc}
   */
  public function undo(string $builder_id): void {
    $builder_data = $this->load($builder_id);
    $past = $builder_data['past'] ?? [];

    if (empty($past)) {
      return;
    }

    // Remove the last element from the past.
    $last = \array_pop($past);
    // @todo just reassign to avoid copy?
    $builder = [
      'id' => $builder_data['id'],
      'entity_config_id' => $builder_data['entity_config_id'],
      'contexts' => $builder_data['contexts'],
      'past' => $past,
      // Set the present to the element we removed in the previous step.
      'present' => $last,
      // Insert the old present state at the beginning of the future.
      'future' => \array_merge([$builder_data['present']], $builder_data['future']),
      'save' => $builder_data['save'],
    ];

    $this->saveData($builder_id, $builder);
  }

  /**
   * {@inheritdoc}
   */
  public function redo(string $builder_id): void {
    $builder_data = $this->load($builder_id);
    $future = $builder_data['future'] ?? [];

    if (empty($future)) {
      return;
    }

    // Remove the first element from the future.
    $first = \array_shift($future);
    // @todo just reassign to avoid copy?
    $builder = [
      'id' => $builder_data['id'],
      'entity_config_id' => $builder_data['entity_config_id'],
      'contexts' => $builder_data['contexts'],
      // Insert the old present state at the end of the past.
      'past' => \array_merge($builder_data['past'], [$builder_data['present']]),
      // Set the present to the element we removed in the previous step.
      'present' => $first,
      'future' => $future,
      'save' => $builder_data['save'],
    ];

    $this->saveData($builder_id, $builder);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(string $builder_id): void {
    $builder_data = $this->load($builder_id);

    // @todo just reassign to avoid copy?
    $builder = [
      'id' => $builder_data['id'],
      'entity_config_id' => $builder_data['entity_config_id'],
      'contexts' => $builder_data['contexts'],
      'past' => [],
      'present' => $builder_data['present'],
      'future' => [],
      'save' => $builder_data['save'],
    ];

    $this->saveData($builder_id, $builder);
  }

  /**
   * {@inheritdoc}
   */
  public function restore(string $builder_id): void {
    $builder_data = $this->load($builder_id);

    // @todo just reassign to avoid copy?
    $builder = [
      'id' => $builder_data['id'],
      'entity_config_id' => $builder_data['entity_config_id'],
      'contexts' => $builder_data['contexts'],
      'past' => $builder_data['past'],
      'present' => $builder_data['save'],
      'future' => $builder_data['future'],
      'save' => $builder_data['save'],
    ];

    $this->saveData($builder_id, $builder);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAll(): array {
    return $this->state->get(self::STORAGE_INDEX, []);
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $builder_id): ?array {
    return $this->state->get(self::STORAGE_PREFIX . $builder_id, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $builder_id): void {
    $display_builder_list = $this->loadAll();
    unset($display_builder_list[$builder_id]);

    $this->state->set(self::STORAGE_INDEX, $display_builder_list);
    $this->state->delete(self::STORAGE_PREFIX . $builder_id);
    $this->state->delete(self::STORAGE_PREFIX . $builder_id . '_hash');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    foreach (\array_keys($this->loadAll()) as $builder_id) {
      $this->delete($builder_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveData(string $builder_id, array $builder_data): void {
    $builder_data['id'] = $builder_id;
    $display_builder_list = $this->loadAll();

    if (!isset($display_builder_list[$builder_id])) {
      $display_builder_list[$builder_id] = '';
    }

    $this->state->set(self::STORAGE_INDEX, $display_builder_list);
    $this->state->set(self::STORAGE_PREFIX . $builder_id, $builder_data);
    $this->state->set(self::STORAGE_PREFIX . $builder_id . '_hash', $builder_data['present']['hash'] ?? '');
  }

  /**
   * Get a hash for this data as uniq id reference.
   *
   * @param array $data
   *   The data to generate uniq id for.
   *
   * @return string
   *   The uniq id value.
   */
  private static function getUniqId(array $data): string {
    // Return hash('xxh3', (string) serialize($data));
    return (string) \crc32((string) \serialize($data));
  }

}
