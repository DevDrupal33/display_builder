<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\InstanceAccessControlHandler;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\InstanceStorage;
use Drupal\display_builder\Plugin\Field\FieldType\HistoryStep;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\SourceTree;
use Drupal\display_builder_ui\InstanceListBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
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
   * Current user.
   */
  public AccountInterface $currentUser;

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
   * Cached normalized source tree for the current present state.
   *
   * Stays valid after mutations (index=FALSE path) and is cleared on undo/redo
   * when the present pointer jumps to a different history step.
   */
  private ?SourceTree $sourceTree = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, mixed $entity_type, mixed $bundle = FALSE, mixed $translations = []) {
    parent::__construct($values, $entity_type, $bundle, $translations);
    $fields = $this->fieldDefinitions;

    foreach ($values as $key => $value) {
      if (isset($fields[$key])) {
        $this->set($key, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Override from ContentEntityBase.
    $fields['id'] = BaseFieldDefinition::create('string');
    // @todo replace by entity_reference.
    $fields['profileId'] = BaseFieldDefinition::create('string');
    $fields['contexts'] = BaseFieldDefinition::create('map');
    // @todo replace by Revisions API.
    $fields['present'] = BaseFieldDefinition::create('step');
    $fields['past'] = BaseFieldDefinition::create('step')->setCardinality(-1);
    $fields['future'] = BaseFieldDefinition::create('step')->setCardinality(-1);
    $fields['save'] = BaseFieldDefinition::create('step');

    return $fields;
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
      'profileId' => $this->get('profileId')->getString(),
      'contexts' => $this->get('contexts')->first()?->getValue(),
      'past' => $this->get('past')->getValue(),
      'present' => $this->get('present')->first()?->getValue(),
      'future' => $this->get('future')->getValue(),
      'save' => $this->get('save')->first()?->getValue(),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\EntityInterface
   */
  public function postCreate(EntityStorageInterface $storage): void {
    if ($this->get('present')->isEmpty()) {
      return;
    }
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $present */
    $present = $this->get('present')->first();
    $this->sourceTree = new SourceTree($present->getData() ?? []);
    $indexed = $this->sourceTree->getTree();
    $hash = self::getUniqId($indexed);

    $this->set('present', [
      'data' => $indexed,
      'hash' => $hash,
      'log' => $present->getLog(),
      'time' => $present->getTime(),
      'user' => $present->getUser(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $profile_id = $this->get('profileId')->getString();
    /** @var \Drupal\display_builder\ProfileInterface $profile */
    $profile = $this->entityTypeManager()->getStorage('display_builder_profile')->load($profile_id);

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function setProfile(string $profile_id): void {
    $this->set('profileId', $profile_id);
  }

  /**
   * {@inheritdoc}
   */
  public function moveToRoot(string $node_id, int $position): bool {
    $tree = $this->getSourceTree();
    $data = $tree->getNodeData($node_id);

    if (!$data) {
      return FALSE;
    }

    if (!$tree->moveToRoot($node_id, $position)) {
      return FALSE;
    }

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $this->getContexts());

    $log = new FormattableMarkup('%node @thingy has been moved to root', [
      '%node' => $labelWithSummary['summary'],
      '@thingy' => $data['source_id'],
    ]);
    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function moveToSlot(string $node_id, string $parent_id, string $slot_id, int $position): bool {
    $tree = $this->getSourceTree();
    $data = $tree->getNodeData($node_id);

    if (!$data) {
      return FALSE;
    }

    if (!$tree->moveToSlot($node_id, $parent_id, $slot_id, $position)) {
      return FALSE;
    }

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $this->getContexts());
    $labelWithSummaryParent = $this->slotSourceProxy()->getLabelWithSummary($tree->getNodeData($parent_id));

    $log = new FormattableMarkup("%node @thingy has been moved to %parent's @slot_id", [
      '%node' => $labelWithSummary['summary'],
      '@thingy' => $data['source_id'],
      '%parent' => $labelWithSummaryParent['summary'],
      '@slot_id' => $slot_id,
    ]);

    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function attachToRoot(int $position, string $source_id, array $data, array $third_party_settings = []): string {
    $tree = $this->getSourceTree();
    $node_id = $tree->attachToRoot($position, $source_id, $data);

    if ($third_party_settings) {
      foreach ($third_party_settings as $island_id => $settings) {
        $tree->setThirdPartySettings($node_id, $island_id, $settings);
      }
    }

    $new_data = $tree->getNode($node_id);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($new_data, $this->getContexts() ?? []);

    $log = new FormattableMarkup('%node @source_id has been attached to root', [
      '%node' => $labelWithSummary['summary'],
      '@source_id' => $source_id,
    ]);
    $this->setNewPresent($tree->getTree(), $log, FALSE, FALSE);

    return $node_id;
  }

  /**
   * {@inheritdoc}
   */
  public function attachToSlot(string $parent_id, string $slot_id, int $position, string $source_id, array $data, array $third_party_settings = []): string {
    $tree = $this->getSourceTree();
    $node_id = $tree->attachToSlot($parent_id, $slot_id, $position, $source_id, $data);

    if (!$node_id) {
      throw new \Exception('Parent or slot not found');
    }

    if ($third_party_settings) {
      foreach ($third_party_settings as $island_id => $settings) {
        $tree->setThirdPartySettings($node_id, $island_id, $settings);
      }
    }

    $new_data = $tree->getNode($node_id);

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($new_data, $this->getContexts() ?? []);
    $labelWithSummaryParent = $this->slotSourceProxy()->getLabelWithSummary($tree->getNode($parent_id));

    $log = new FormattableMarkup("%node @source_id has been attached to %parent's @slot_id", [
      '%node' => $labelWithSummary['summary'],
      '@source_id' => $source_id,
      '%parent' => $labelWithSummaryParent['summary'],
      '@slot_id' => $slot_id,
    ]);
    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);

    return $node_id;
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
    $tree = $this->getSourceTree();

    if (!$tree->setSource($node_id, $source_id, $data)) {
      throw new \Exception('Node ID mismatch');
    }

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($tree->getNodeData($node_id), $this->getContexts());

    $log = new FormattableMarkup('%source has been updated', [
      '%source' => $labelWithSummary['summary'],
    ]);
    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySettings(string $node_id, string $island_id, array $data): void {
    $tree = $this->getSourceTree();

    if (!$tree->setThirdPartySettings($node_id, $island_id, $data)) {
      return;
    }

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($tree->getNodeData($node_id), $this->getContexts());

    $log = new FormattableMarkup('%source has been updated by @island_id', [
      '%source' => $labelWithSummary['summary'],
      '@island_id' => $island_id,
    ]);
    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function remove(string $node_id): void {
    $tree = $this->getSourceTree();
    $data = $tree->getNodeData($node_id);

    if (!$data) {
      return;
    }
    $parent_id = $tree->getParentId($node_id);

    $contexts = $this->getContexts() ?? [];

    // Get friendly label to display in log instead of ids.
    $labelWithSummary = $this->slotSourceProxy()->getLabelWithSummary($data, $contexts);
    $labelWithSummaryParent = empty($parent_id) ? ['summary' => 'root'] : $this->slotSourceProxy()->getLabelWithSummary($tree->getNodeData($parent_id), $contexts);

    $tree->remove($node_id);

    $log = new FormattableMarkup('%node has been removed from %parent', [
      '%node' => $labelWithSummary['summary'],
      '%parent' => $labelWithSummaryParent['summary'],
    ]);
    $this->setNewPresent($tree->getTree(), $log, FALSE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(): ?array {
    if ($this->get('contexts')->isEmpty()) {
      return [];
    }

    return $this->refreshContexts($this->get('contexts')->first()->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function setSave(array $save_data): void {
    $tree = new SourceTree($save_data);
    $indexed = $tree->getTree();
    $hash = self::getUniqId($indexed);
    $this->set('save', ['data' => $indexed, 'hash' => $hash, 'log' => NULL, 'time' => \time(), 'user' => NULL]);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCurrentState(): array {
    return $this->getCurrent()?->getData() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function restore(): void {
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep|null $first */
    $first = $this->get('save')->first();
    $this->setNewPresent($first->getData(), 'Back to saved data.');
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function undo(): void {
    $past = $this->get('past');

    if ($past->isEmpty()) {
      return;
    }

    $present_values = $this->get('present')->getValue();
    \assert(\array_is_list($present_values));

    // Remove the last element from the past.
    $past_values = $past->getValue();
    \assert(\array_is_list($past_values));
    $last = \array_pop($past_values);
    \assert(!\array_is_list($last));
    $this->set('past', $past_values);

    // Set the present to the element we removed in the previous step.
    $this->set('present', $last);
    // Insert the old present state at the beginning of the future.
    $this->set('future', \array_merge($present_values, $this->get('future')->getValue()));
    $this->sourceTree = NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function redo(): void {
    $future = $this->get('future');

    if ($future->isEmpty()) {
      return;
    }

    // Remove the first element from the future.
    $first = $future->first()->getValue();
    \assert(!\array_is_list($first));
    $future->removeItem(0);
    // Insert the old present state at the end of the past.
    $this->get('past')->appendItem($this->get('present')->first());
    // Set the present to the element we removed in the previous step.
    $this->set('present', $first);
    $this->set('future', $future->getValue());
    $this->sourceTree = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function clear(): void {
    $this->set('past', NULL);
    $this->set('future', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function isHistoryNew(): bool {
    return $this->present === NULL && empty($this->past) && empty($this->future);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCountPast(): int {
    return $this->get('past')->count();
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCountFuture(): int {
    return $this->get('future')->count();
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers(): array {
    $users = [];
    $steps = \array_merge($this->get('past')->getValue(), $this->get('present')->getValue(), $this->get('future')->getValue());

    foreach ($steps as $step) {
      if ($step === NULL) {
        continue;
      }
      $user_id = $step['user'];

      if ($user_id !== NULL && (!isset($users[$user_id]) || $step['time'] > $users[$user_id])) {
        $users[$user_id] = $step['time'];
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
    return !$this->get('save')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function saveIsCurrent(): bool {
    $present = $this->get('present');
    $save = $this->get('save');

    // If either present or save is null, they can't be equal unless both are
    // null.
    if ($present->isEmpty() || $save->isEmpty()) {
      return $present->isEmpty() && $save->isEmpty();
    }

    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep|null $present */
    $present = $present->first();
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep|null $save */
    $save = $save->first();

    return $present->getHash() === $save->getHash();
  }

  /**
   * {@inheritdoc}
   */
  public function getPathIndex(): array {
    return $this->getSourceTree()->getPathIndex();
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function setNewPresent(array $data, FormattableMarkup|string $log_message = '', bool $check_hash = TRUE, bool $index = TRUE): void {
    if ($index) {
      // Raw data needs normalizing; build tree and keep it as the new cache.
      $this->sourceTree = new SourceTree($data);
      $data = $this->sourceTree->getTree();
    }
    // When index=FALSE the data was produced by the cached tree's getTree(),
    // so $this->sourceTree already reflects the new state — no invalidation.
    $hash = self::getUniqId($data);

    if (!$this->get('present')->isEmpty()) {
      /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep|null $present */
      $present = $this->get('present')->first();

      // Check if this present is the same to avoid duplicates, for example move
      // to the same place.
      if ($check_hash && $hash === $present->getHash()) {
        return;
      }

      // 1. Insert the present at the end of the past.
      // If it's the very first action, we want a NULL in the past to be able to
      // undo to initial empty state.
      $this->get('past')->appendItem($present->getValue());
    }

    // Keep only the last x history.
    if ($this->get('past')->count() > self::MAX_HISTORY) {
      $this->get('past')->removeItem(0);
    }

    // 2. Set the present to the new state.
    $this->set('present', [
      'data' => $data,
      'hash' => $hash,
      'log' => $log_message,
      'time' => \time(),
      'user' => (int) $this->currentUser()->id(),
    ]);

    // 3. Clear the future.
    $this->set('future', []);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCurrent(): ?HistoryStep {
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep|null $step */
    $step = $this->get('present')->first();

    return $step;
  }

  /**
   * {@inheritdoc}
   */
  public static function getUniqId(array $data): int {
    return \crc32((string) \serialize($data));
  }

  /**
   * Get or create the cached source tree for the current present state.
   *
   * The cache is populated lazily on first access and remains valid until
   * undo() or redo() changes the present pointer to a different history step.
   * Mutations (index=FALSE path) keep it alive since the tree is the source
   * of the new present data. The index=TRUE path in setNewPresent() replaces
   * it with a freshly normalized tree.
   *
   * @return \Drupal\display_builder\SourceTree
   *   The source tree for the current state.
   */
  private function getSourceTree(): SourceTree {
    if ($this->sourceTree === NULL) {
      $this->sourceTree = new SourceTree($this->getCurrentState());
    }

    return $this->sourceTree;
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

}
