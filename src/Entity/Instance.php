<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\HistoryStep;
use Drupal\display_builder\InstanceAccessControlHandler;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\InstanceStorage;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\display_builder_ui\InstanceListBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourceInterface;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Defines the display builder instance entity class.
 */
#[ContentEntityType(
  id: 'display_builder_instance',
  label: new TranslatableMarkup('Display Builder instance'),
  label_collection: new TranslatableMarkup('Display builder instances'),
  label_singular: new TranslatableMarkup('display builder instance'),
  label_plural: new TranslatableMarkup('display builder instances'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'access' => InstanceAccessControlHandler::class,
    'storage' => InstanceStorage::class,
    // Managed by display_builder_ui.
    'list_builder' => InstanceListBuilder::class,
  ],
  links: [
    // Managed by display_builder_ui.
    'collection' => '/admin/structure/display-builder/instances',
  ],
  label_count: [
    'singular' => '@count instance',
    'plural' => '@count instances',
  ],
)]
class Instance extends ContentEntityBase implements InstanceInterface {

  private const MAX_HISTORY = 10;

  /**
   * Entity ID.
   *
   * Public because injected by ContentEntityStorageBase::initFieldValues().
   */
  public string $id;

  /**
   * Entity label.
   *
   * Public because injected by ContentEntityStorageBase::initFieldValues().
   */
  public string $label;

  /**
   * Display Builder profile ID.
   *
   * Public because injected by ContentEntityStorageBase::initFieldValues().
   */
  public string $profileId = '';

  /**
   * Present step.
   */
  public ?HistoryStep $present = NULL;

  /**
   * Current user.
   */
  public AccountInterface $currentUser;

  /**
   * Saved step.
   */
  public ?HistoryStep $save = NULL;

  /**
   * Contexts.
   *
   * Public because injected by ContentEntityStorageBase::initFieldValues().
   *
   * @var \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of contexts, keyed by context name.
   */
  public array $contexts = [];

  /**
   * Past steps.
   *
   * @var \Drupal\display_builder\HistoryStep[]
   */
  protected array $past = [];

  /**
   * Future steps.
   *
   * @var \Drupal\display_builder\HistoryStep[]
   */
  protected array $future = [];

  /**
   * Path index.
   *
   * A mapping where each key is an slot source node ID and each value has
   * two properties:
   * - path: the path
   * - parent: the node ID of the parent. This is necessary because not every
   *   SourceWithSlotsInterface implementations has the same "deepness". For
   *   example, ComponentSource has 4 levels (component, slots, slot_id,
   *   'sources), LayoutSource has 2 levels (regions, slot_id), etc.
   */
  protected array $pathIndex = [];

  /**
   * Entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * Slot source proxy.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * Source plugin manager.
   */
  protected SourcePluginManager $sourceManager;

  /**
   * {@inheritdoc}
   *
   * @todo remove once we manage proper revisions and translations.
   */
  public function __construct(array $values, mixed $entity_type, mixed $bundle = FALSE, mixed $translations = []) {
    $this->entityTypeId = $entity_type;
    $this->entityKeys['bundle'] = $bundle ?: $this->entityTypeId;

    foreach ($values as $key => $value) {
      if (!$value) {
        continue;
      }

      if (\property_exists($this, $key)) {
        $this->$key = $value;
      }
      $values[$key] = [
        LanguageInterface::LANGCODE_DEFAULT => $value,
      ];
    }

    $this->values = $values;
    $this->translations = [
      LanguageInterface::LANGCODE_DEFAULT => [
        'entity' => $this,
        'status' => TRUE,
      ],
    ];

    $this->langcodeKey = '';
    $this->defaultLangcodeKey = '';
    $this->setDefaultLangcode();

    unset($translations);
  }

  /**
   * {@inheritdoc}
   */
  public function isNew(): bool {
    // We don't support enforceIsNew property because we have no practical
    // use of it and because it seems to break the invalidation of
    // ::getCacheTags().
    return !$this->id();
  }

  /**
   * {@inheritdoc}
   *
   * @todo some case require non empty id, like devel load. Remove once we
   * manage proper revisions and translations.
   */
  public function id() {
    return $this->id ?? '_none';
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Extract a human readable name from an instance id.
    // Example: "provider__my_display" -> "My display".
    $parts = \explode('__', (string) $this->id());

    if (\count($parts) > 1) {
      \array_shift($parts);

      return \ucfirst(\implode(' ', \str_replace('_', ' ', $parts)));
    }

    return (string) $this->id();
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\EntityInterface
   */
  public function toArray(): array {
    return [
      'id' => $this->id(),
      'profileId' => $this->profileId,
      'contexts' => $this->contexts,
      'past' => $this->past,
      'present' => $this->present,
      'future' => $this->future,
      'save' => $this->save,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\EntityInterface
   */
  public function postCreate(EntityStorageInterface $storage): void {
    if (!$this->present) {
      return;
    }

    $indexed = $this->buildIndexFromSlot([], $this->present->data ?? [], NULL);
    $hash = self::getUniqId($indexed);
    $this->present = new HistoryStep(
      $indexed,
      $hash,
      $this->present->log,
      $this->present->time,
      $this->present->user,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    /** @var \Drupal\display_builder\ProfileInterface $profile */
    $profile = $this->entityTypeManager()->getStorage('display_builder_profile')->load($this->profileId);

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function setProfile(string $profile_id): void {
    $this->profileId = $profile_id;
  }

  /**
   * {@inheritdoc}
   */
  public function moveToRoot(string $node_id, int $position): bool {
    $root = $this->getCurrentState();
    $path = $this->getPath($node_id);
    $data = NestedArray::getValue($root, $path);

    if (empty($data) || !isset($data['source_id'])) {
      return FALSE;
    }

    $root = $this->doRemove($root, $node_id);
    $root = $this->doAttachToRoot($root, $position, $data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $this->getContexts());

    $log = new FormattableMarkup('%node @thingy has been moved to root', [
      '%node' => $labelWithSummary['summary'],
      '@thingy' => $data['source_id'],
    ]);
    $this->setNewPresent($root, $log);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function moveToSlot(string $node_id, string $parent_id, string $slot_id, int $position): bool {
    $root = $this->getCurrentState();
    $path = $this->getPath($node_id);
    $data = NestedArray::getValue($root, $path);

    if (empty($data) || !isset($data['source_id'])) {
      return FALSE;
    }

    if ($this->isNodeAlreadyInSlot($parent_id, $slot_id, $node_id)) {
      // Moving to the same slot is tricky, because we don't want to remove a
      // sibling.
      $root = $this->doMoveToSameSlot($root, $node_id, $parent_id, $slot_id, $position);
    }
    else {
      // Moving to a different slot is easier, we can first delete the previous
      // node data, and attach it to the new position.
      $root = $this->doRemove($root, $node_id);
      $root = $this->doAttachToSlot($root, $parent_id, $slot_id, $position, $data);
    }

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $this->getContexts());
    $labelWithSummaryParent = $this->slotSourceProxy()->getLabelWithSummary($this->getNode($parent_id));

    $log = new FormattableMarkup("%node @thingy has been moved to %parent's @slot_id", [
      '%node' => $labelWithSummary['summary'],
      '@thingy' => $data['source_id'],
      '%parent' => $labelWithSummaryParent['summary'],
      '@slot_id' => $slot_id,
    ]);

    $this->setNewPresent($root, $log);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function attachToRoot(int $position, string $source_id, array $data, array $third_party_settings = []): string {
    $data = [
      'node_id' => \uniqid(),
      'source_id' => $source_id,
      'source' => $data,
    ];

    if ($third_party_settings) {
      $data['third_party_settings'] = $third_party_settings;
    }

    $root = $this->getCurrentState();
    $root = $this->doAttachToRoot($root, $position, $data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $this->getContexts() ?? []);

    $log = new FormattableMarkup('%node @source_id has been attached to root', [
      '%node' => $labelWithSummary['summary'],
      '@source_id' => $source_id,
    ]);
    $this->setNewPresent($root, $log, FALSE);

    return $data['node_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function attachToSlot(string $parent_id, string $slot_id, int $position, string $source_id, array $data, array $third_party_settings = []): string {
    $root = $this->getCurrentState();
    $data = [
      'node_id' => \uniqid(),
      'source_id' => $source_id,
      'source' => $data,
    ];

    if ($third_party_settings) {
      $data['third_party_settings'] = $third_party_settings;
    }

    $root = $this->doAttachToSlot($root, $parent_id, $slot_id, $position, $data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $this->getContexts() ?? []);
    $labelWithSummaryParent = $this->slotSourceProxy()->getLabelWithSummary($this->getNode($parent_id));

    $log = new FormattableMarkup("%node @source_id has been attached to %parent's @slot_id", [
      '%node' => $labelWithSummary['summary'],
      '@source_id' => $source_id,
      '%parent' => $labelWithSummaryParent['summary'],
      '@slot_id' => $slot_id,
    ]);
    $this->setNewPresent($root, $log);

    return $data['node_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getNode(string $node_id): array {
    $root = $this->getCurrentState();
    $path = $this->getPath($node_id);
    $value = NestedArray::getValue($root, $path);

    return $value ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(string $node_id): string {
    return $this->getPathIndex()[$node_id]['parent'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(string $node_id, string $source_id, array $data): void {
    $root = $this->getCurrentState();
    $path = $this->getPath($node_id);
    $existing_data = NestedArray::getValue($root, $path) ?? [];

    if (!isset($existing_data['node_id']) || ($existing_data['node_id'] !== $node_id)) {
      throw new \Exception('Node ID mismatch');
    }
    $existing_data['source_id'] = $source_id;
    $existing_data['source'] = $data;
    NestedArray::setValue($root, $path, $existing_data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($existing_data, $this->getContexts());

    $log = new FormattableMarkup('%source has been updated', [
      '%source' => $labelWithSummary['summary'],
    ]);
    $this->setNewPresent($root, $log);
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySettings(string $node_id, string $island_id, array $data): void {
    $root = $this->getCurrentState();
    $path = $this->getPath($node_id);
    $existing_data = NestedArray::getValue($root, $path);

    if (!isset($existing_data['third_party_settings'])) {
      $existing_data['third_party_settings'] = [];
    }
    $existing_data['third_party_settings'][$island_id] = $data;
    NestedArray::setValue($root, $path, $existing_data);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($existing_data, $this->getContexts());

    $log = new FormattableMarkup('%source has been updated by @island_id', [
      '%source' => $labelWithSummary['summary'],
      '@island_id' => $island_id,
    ]);
    $this->setNewPresent($root, $log);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(string $node_id): void {
    $root = $this->getCurrentState();
    $path = $this->getPath($node_id);
    $data = NestedArray::getValue($root, $path);
    $parent_id = $this->getParentId($node_id);
    $root = $this->doRemove($root, $node_id);

    $contexts = $this->getContexts() ?? [];

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $contexts);
    $labelWithSummaryParent = empty($parent_id) ? ['summary' => 'root'] : $this->slotSourceProxy()->getLabelWithSummary($this->getNode($parent_id), $contexts);

    $log = new FormattableMarkup('%node has been removed from %parent', [
      '%node' => $labelWithSummary['summary'],
      '%parent' => $labelWithSummaryParent['summary'],
    ]);
    $this->setNewPresent($root, $log, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(): ?array {
    return $this->refreshContexts($this->contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function setSave(array $save_data): void {
    $indexed = $this->buildIndexFromSlot([], $save_data, NULL);
    $hash = self::getUniqId($indexed);
    $this->save = new HistoryStep($indexed, $hash, NULL, \time(), NULL);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCurrentState(): array {
    return $this->getCurrent()->data ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function restore(): void {
    $this->setNewPresent($this->save->data, 'Back to saved data.');
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function undo(): void {
    $past = $this->past ?? [];

    if (empty($past)) {
      return;
    }

    $present = $this->present;
    // Remove the last element from the past.
    $last = \array_pop($past);
    $this->past = $past;
    // Set the present to the element we removed in the previous step.
    $this->present = $last;
    // Insert the old present state at the beginning of the future.
    $this->future = \array_merge([$present], $this->future);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function redo(): void {
    $future = $this->future ?? [];

    if (empty($future)) {
      return;
    }

    // Remove the first element from the future.
    $first = \array_shift($future);
    // Insert the old present state at the end of the past.
    $this->past = \array_merge($this->past, [$this->present]);
    // Set the present to the element we removed in the previous step.
    $this->present = $first;
    $this->future = $future;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function clear(): void {
    $this->past = [];
    $this->future = [];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCountPast(): int {
    return \count($this->past);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCountFuture(): int {
    return \count($this->future);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers(): array {
    $users = [];
    $steps = \array_merge($this->past, [$this->present], $this->future);

    foreach ($steps as $step) {
      if ($step === NULL) {
        continue;
      }
      $user_id = $step->user;

      if ($user_id !== NULL && (!isset($users[$user_id]) || $step->time > $users[$user_id])) {
        $users[$user_id] = $step->time;
      }
    }

    return $users;
  }

  /**
   * {@inheritdoc}
   */
  public function canSaveContextsRequirement(?array $contexts = NULL): bool {
    $contexts ??= $this->getContexts();

    if ($contexts === NULL) {
      return FALSE;
    }

    if (!\array_key_exists('context_requirements', $contexts)
      || !($contexts['context_requirements'] instanceof RequirementsContext)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasSaveContextsRequirement(string $key, array $contexts = []): bool {
    $contexts = empty($contexts) ? $this->getContexts() : $contexts;
    // Some strange edge cases where context is null.
    $contexts ??= [];

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
  public function hasSave(): bool {
    return !empty($this->save);
  }

  /**
   * {@inheritdoc}
   */
  public function saveIsCurrent(): bool {
    // If either present or save is null, they can't be equal unless both are
    // null.
    if ($this->present === NULL || $this->save === NULL) {
      return $this->present === NULL && $this->save === NULL;
    }

    return $this->present->hash === $this->save->hash;
  }

  /**
   * {@inheritdoc}
   */
  public function getPathIndex(): array {
    // It may be slow to rebuild the index every time we request it. But it is
    // very difficult to maintain an index synchronized with the state storage
    // history.
    $root = $this->getCurrentState();
    $this->pathIndex = [];
    $this->buildIndexFromSlot([], $root, NULL);

    return $this->pathIndex;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function setNewPresent(array $data, FormattableMarkup|string $log_message = '', bool $check_hash = TRUE): void {
    $hash = self::getUniqId($data);

    // Check if this present is the same to avoid duplicates, for example move
    // to the same place.
    if ($check_hash && $hash === $this->present?->hash) {
      return;
    }

    // 1. Insert the present at the end of the past.
    $this->past[] = $this->present;

    // Keep only the last x history.
    if (\count($this->past) > self::MAX_HISTORY) {
      \array_shift($this->past);
    }

    // 2. Set the present to the new state.
    $this->present = new HistoryStep(
      $data,
      $hash,
      $log_message,
      \time(),
      (int) $this->currentUser()->id(),
    );

    // 3. Clear the future.
    $this->future = [];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCurrent(): ?HistoryStep {
    return $this->present;
  }

  /**
   * {@inheritdoc}
   */
  public static function getUniqId(array $data): int {
    return \crc32((string) \serialize($data));
  }

  /**
   * Is the node already in the slot?
   *
   * @param string $parent_id
   *   The node id of the parent.
   * @param string $slot_id
   *   The parent slot.
   * @param string $node_id
   *   The node id of the source.
   */
  private function isNodeAlreadyInSlot(string $parent_id, string $slot_id, string $node_id): bool {
    if ($parent_id !== $this->getParentId($node_id)) {
      return FALSE;
    }
    $parent_data = $this->getNode($parent_id);
    $parent = $this->getSlotSourcePlugin($parent_data);

    if (!($parent instanceof SourceWithSlotsInterface)) {
      return FALSE;
    }

    foreach ($parent->getSlotValue($slot_id) as $source_data) {
      if ($source_data['node_id'] === $node_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Build the index from a slot.
   *
   * @param array $path
   *   The path to the slot.
   * @param array $data
   *   The slot data.
   * @param ?string $parent
   *   The node ID of the parent.
   *
   * @return array
   *   The slot data with the index updated.
   */
  private function buildIndexFromSlot(array $path, array $data, ?string $parent): array {
    // To avoid non consecutive array keys, we rebuild the value list.
    $data = \array_values($data);

    foreach ($data as $index => $source) {
      $source_path = \array_merge($path, [$index]);
      $data[$index] = $this->buildIndexFromSource($source_path, $source, $parent);
    }

    return $data;
  }

  /**
   * Sample entity generator.
   */
  private function sampleEntityGenerator(): SampleEntityGeneratorInterface {
    return $this->sampleEntityGenerator ??= \Drupal::service('ui_patterns.sample_entity_generator');
  }

  /**
   * Slot source proxy.
   */
  private function slotSourceProxy(): SlotSourceProxy {
    return $this->slotSourceProxy ??= \Drupal::service('display_builder.slot_sources_proxy');
  }

  /**
   * Slot source proxy.
   */
  private function currentUser(): AccountInterface {
    return $this->currentUser ??= \Drupal::service('current_user');
  }

  /**
   * Slot source proxy.
   */
  private function sourceManager(): SourcePluginManager {
    return $this->sourceManager ??= \Drupal::service('plugin.manager.ui_patterns_source');
  }

  /**
   * Refresh contexts after loaded from storage.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The contexts.
   *
   * @throws \Drupal\Component\Plugin\Exception\ContextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return array
   *   The refreshed contexts or NULL if no context.
   */
  private function refreshContexts(array $contexts): array {
    foreach ($contexts as &$context) {
      if ($context instanceof EntityContext) {
        // @todo We should use cache entries here
        // with the corresponding cache contexts in it.
        // This may avoid some unnecessary entity loads or generation.
        $entity = $context->getContextValue();

        // Check if sample entity.
        if ($entity->id()) {
          $entity = $this->entityTypeManager()->getStorage($entity->getEntityTypeId())->load($entity->id());
        }
        else {
          $entity = $this->sampleEntityGenerator()->get($entity->getEntityTypeId(), $entity->bundle());
        }

        // Edge case when the parent entity is deleted but not the builder
        // instance.
        if (!$entity) {
          return $contexts;
        }
        $context = (\get_class($context))::fromEntity($entity);
      }
    }

    return $contexts;
  }

  /**
   * Change the position of an source in a slot.
   *
   * @param array $slot
   *   The slot.
   * @param string $node_id
   *   The node id of the source.
   * @param int $to
   *   The new position.
   *
   * @return array
   *   The updated slot.
   */
  private function changeSourcePositionInSlot(array $slot, string $node_id, int $to): array {
    foreach ($slot as $position => $source) {
      if ($source['node_id'] === $node_id) {
        $p1 = \array_splice($slot, $position, 1);
        $p2 = \array_splice($slot, 0, $to);

        return \array_merge($p2, $p1, $slot);
      }
    }

    return $slot;
  }

  /**
   * Add path to index and add node ID to source.
   *
   * @param array $path
   *   The path to the slot.
   * @param array $data
   *   The slot data.
   * @param ?string $parent
   *   The node ID of the parent.
   *
   * @return array
   *   The slot data with the index updated.
   */
  private function buildIndexFromSource(array $path, array $data, ?string $parent): array {
    // First job: Add missing node_id keys.
    $node_id = empty($data['node_id']) ? \uniqid() : $data['node_id'];
    $data['node_id'] = $node_id;
    // Second job: Save the path to the index.
    $this->pathIndex[$node_id] = [
      'path' => $path,
      'parent' => $parent,
    ];

    if (!isset($data['source_id'])) {
      return $data;
    }

    $source = $this->getSlotSourcePlugin($data);

    if (!($source instanceof SourceWithSlotsInterface)) {
      return $data;
    }

    foreach ($source->getSlotValues() as $slot_id => $slot) {
      $slot_path = \array_merge($path, ['source'], $source::getSlotPath($slot_id));
      $slot = $this->buildIndexFromSlot($slot_path, $slot, $node_id);
      $data['source'] = $source->setSlotValue($slot_id, $slot);
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
  private function doAttachToRoot(array $root, int $position, array $data): array {
    \array_splice($root, $position, 0, [$data]);

    return $root;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param array $root
   *   The root state.
   * @param string $node_id
   *   The ID of the parent node.
   * @param string $parent_id
   *   The ID of the parent node.
   * @param string $slot_id
   *   The ID of the slot where to insert the data.
   * @param int $position
   *   The position where to insert the data.
   *
   * @return array
   *   The updated root state
   */
  private function doMoveToSameSlot(array $root, string $node_id, string $parent_id, string $slot_id, int $position): array {
    $parent_data = $this->getNode($parent_id);
    $source = $this->getSlotSourcePlugin($parent_data);

    if ($source instanceof SourceWithSlotsInterface) {
      $slot = $source->getSlotValue($slot_id);
      $slot = $this->changeSourcePositionInSlot($slot, $node_id, $position);
      $root = $this->doUpdateSlotValue($root, $parent_id, $source, $slot_id, $slot);
    }

    return $root;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param array $root
   *   The root state.
   * @param string $parent_id
   *   The ID of the parent node.
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
  private function doAttachToSlot(array $root, string $parent_id, string $slot_id, int $position, array $data): array {
    $parent_data = $this->getNode($parent_id);
    $source = $this->getSlotSourcePlugin($parent_data);

    if ($source instanceof SourceWithSlotsInterface) {
      $slot = $source->getSlotValue($slot_id);
      \array_splice($slot, $position, 0, [$data]);
      $root = $this->doUpdateSlotValue($root, $parent_id, $source, $slot_id, $slot);
    }

    return $root;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param array $root
   *   The root state.
   * @param string $node_id
   *   The ID of the node.
   * @param \Drupal\display_builder\SourceWithSlotsInterface $source
   *   The source plugin.
   * @param string $slot_id
   *   The ID of the slot where to insert the data.
   * @param array $slot_data
   *   The slot data to replace.
   *
   * @return array
   *   The updated root state
   */
  private function doUpdateSlotValue(array $root, string $node_id, SourceWithSlotsInterface $source, string $slot_id, array $slot_data): array {
    $path = \array_merge($this->getPath($node_id), ['source'], $source::getSlotPath($slot_id));
    NestedArray::setValue($root, $path, $slot_data);

    return $root;
  }

  /**
   * Internal atomic change of the root state.
   *
   * @param array $root
   *   The root state.
   * @param string $node_id
   *   The node id of the source.
   *
   * @return array
   *   The updated root state
   */
  private function doRemove(array $root, string $node_id): array {
    $path = $this->getPath($node_id);
    NestedArray::unsetValue($root, $path);

    return $root;
  }

  /**
   * Get the path to an source.
   *
   * @param string $node_id
   *   The node id of the source.
   *
   * @return array
   *   The path, one array item by level.
   */
  private function getPath(string $node_id): array {
    return $this->getPathIndex()[$node_id]['path'] ?? [];
  }

  /**
   * Get slot source plugin.
   *
   * @param array $data
   *   The node data from the tree.
   *
   * @return \Drupal\ui_patterns\SourceInterface
   *   The instantiated plugin.
   */
  private function getSlotSourcePlugin(array $data): SourceInterface {
    $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager()->getSlotPropType()]];
    /** @var \Drupal\ui_patterns\SourceInterface $source */
    $source = $this->sourceManager()->createInstance(
      $data['source_id'],
      SourcePluginBase::buildConfiguration('slot', $slot_definition, $data, [])
    );

    return $source;
  }

}
