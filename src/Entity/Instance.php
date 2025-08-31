<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Entity\Attribute\EntityType;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\InstanceAccessControlHandler;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\InstanceStorage;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Defines the display builder instance entity class.
 */
#[EntityType(
  id: 'display_builder_instance',
  label: new TranslatableMarkup('Display Builder instance'),
  label_collection: new TranslatableMarkup('Display builder instances'),
  label_singular: new TranslatableMarkup('display builder instance'),
  label_plural: new TranslatableMarkup('display builder instances'),
  entity_keys: [
    'label' => 'id',
  ],
  handlers: [
    'access' => InstanceAccessControlHandler::class,
    'storage' => InstanceStorage::class,
  ],
  label_count: [
    'singular' => '@count instance',
    'plural' => '@count instances',
  ],
)]
class Instance extends EntityBase implements InstanceInterface {

  /**
   * Profile ID injected next time the entity is saved.
   *
   * This is a temporary mechanism, useful when Instance is still a facade to
   * StateManager.
   *
   * @see \Drupal\display_builder\InstanceStorage::doSave()
   */
  protected string $profileId = '';

  /**
   * Data injected next time the entity is saved.
   *
   * This is a temporary mechanism, useful when Instance is still a facade to
   * StateManager.
   *
   * @see \Drupal\display_builder\InstanceStorage::doSave()
   */
  protected array $data = [];

  /**
   * Contexts injected next time the entity is saved.
   *
   * This is a temporary mechanism, useful when Instance is still a facade to
   * StateManager.
   *
   * @see \Drupal\display_builder\InstanceStorage::doSave()
   */
  protected array $contexts = [];

  /**
   * Saved status injected next time the entity is saved.
   *
   * This is a temporary mechanism, useful when Instance is still a facade to
   * StateManager.
   *
   * @see \Drupal\display_builder\InstanceStorage::doSave()
   */
  protected bool $saved = FALSE;

  /**
   * Log instance with be used next time the entity is saved.
   *
   * This is a temporary mechanism, useful when Instance is still a facade to
   * StateManager.
   *
   * @see \Drupal\display_builder\InstanceStorage::doSave()
   */
  protected string $logMessage = '';

  /**
   * Entity ID.
   */
  protected string $id;

  /**
   * State manager.
   */
  protected StateManagerInterface $stateManager;

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return $this->stateManager()->load($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?DisplayBuilderInterface {
    $profile_id = $this->stateManager()->getEntityConfigId($this->id);
    $this->profileId = $profile_id;
    /** @var \Drupal\display_builder\DisplayBuilderInterface $profile */
    $profile = $this->entityTypeManager()->getStorage('display_builder')->load($profile_id);

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeProfileId(): string {
    return $this->profileId;
  }

  /**
   * {@inheritdoc}
   */
  public function setRuntimeProfileId(string $profile_id): void {
    $this->profileId = $profile_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeData(): array {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function setRuntimeData(array $data): void {
    $this->data = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(): array {
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function setRuntimeContexts(array $contexts): void {
    $this->contexts = $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeSaved(): bool {
    return $this->saved;
  }

  /**
   * {@inheritdoc}
   */
  public function setRuntimeSaved(bool $saved): void {
    $this->saved = $saved;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogMessage(): string {
    return $this->logMessage;
  }

  /**
   * {@inheritdoc}
   */
  public function setLogMessage(string $message): void {
    $this->logMessage = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentHash(): string {
    return $this->stateManager()->getCurrentHash($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function moveToRoot(string $instance_id, int $position): bool {
    return $this->stateManager()->moveToRoot($this->id, $instance_id, $position);
  }

  /**
   * {@inheritdoc}
   */
  public function moveToSlot(string $instance_id, string $parent_id, string $slot_id, int $position): bool {
    return $this->stateManager()->moveToSlot($this->id, $instance_id, $parent_id, $slot_id, $position);
  }

  /**
   * {@inheritdoc}
   */
  public function attachSourceToRoot(int $position, string $source_id, array $data, array $third_party_settings = []): string {
    return $this->stateManager()->attachSourceToRoot($this->id, $position, $source_id, $data, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function attachSourceToSlot(string $parent_id, string $slot_id, int $position, string $source_id, array $data, array $third_party_settings = []): string {
    return $this->stateManager()->attachSourceToSlot($this->id, $parent_id, $slot_id, $position, $source_id, $data, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $instance_id): array {
    return $this->stateManager()->get($this->id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentState(): array {
    return $this->stateManager()->getCurrentState($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(array $root, string $instance_id): string {
    return $this->stateManager()->getParentId($this->id, $root, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(string $instance_id, string $source_id, array $data): void {
    $this->stateManager()->setSource($this->id, $instance_id, $source_id, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySettings(string $instance_id, string $island_id, array $data): void {
    $this->stateManager()->setThirdPartySettings($this->id, $instance_id, $island_id, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(string $instance_id): void {
    $this->stateManager()->remove($this->id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(): ?array {
    return $this->stateManager()->getContexts($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function setSave(array $save_data): void {
    $this->stateManager()->setSave($this->id, $save_data);
  }

  /**
   * {@inheritdoc}
   */
  public function restore(): void {
    $this->stateManager()->restore($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function undo(): void {
    $this->stateManager()->undo($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function redo(): void {
    $this->stateManager()->redo($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function clear(): void {
    $this->stateManager()->clear($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function getPathIndex(array $root = []): array {
    return $this->stateManager()->getPathIndex($this->id, $root);
  }

  /**
   * {@inheritdoc}
   */
  public function getCountPast(): int {
    return $this->stateManager()->getCountPast($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function getCountFuture(): int {
    return $this->stateManager()->getCountFuture($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers(): array {
    return $this->stateManager()->getUsers($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function canSaveContextsRequirement(?array $contexts = NULL): bool {
    return $this->stateManager()->canSaveContextsRequirement($this->id, $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function hasSaveContextsRequirement(string $key, array $contexts = []): bool {
    return $this->stateManager()->hasSaveContextsRequirement($this->id, $key, $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function hasSave(): bool {
    return $this->stateManager()->hasSave($this->id);
  }

  /**
   * {@inheritdoc}
   */
  public function saveIsCurrent(): bool {
    return $this->stateManager()->saveIsCurrent($this->id);
  }

  /**
   * Get the state manager.
   *
   * @return \Drupal\display_builder\StateManager\StateManagerInterface
   *   The state manager.
   */
  protected function stateManager(): StateManagerInterface {
    return $this->stateManager ??= \Drupal::service('display_builder.state_manager');
  }

}
