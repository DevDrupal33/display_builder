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

    $tree = new SourceTree($this->present->data ?? []);
    $indexed = $tree->getTree();
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
    $tree = new SourceTree($this->getCurrentState());
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
    $tree = new SourceTree($this->getCurrentState());
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
    $tree = new SourceTree($this->getCurrentState());
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
    $tree = new SourceTree($this->getCurrentState());
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
    $tree = new SourceTree($this->getCurrentState());

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
    $tree = new SourceTree($this->getCurrentState());

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
    $tree = new SourceTree($this->getCurrentState());
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
    return $this->refreshContexts($this->contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function setSave(array $save_data): void {
    $tree = new SourceTree($save_data);
    $indexed = $tree->getTree();
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
    $past = \array_filter($this->past ?? []);

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
    $future = \array_filter($this->future ?? []);

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
   */
  public function clear(): void {
    $this->past = [];
    $this->future = [];
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
    return \count(\array_filter($this->past));
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCountFuture(): int {
    return \count(\array_filter($this->future));
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
    $tree = new SourceTree($this->getCurrentState());

    return $tree->getPathIndex();
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function setNewPresent(array $data, FormattableMarkup|string $log_message = '', bool $check_hash = TRUE, bool $index = TRUE): void {
    if ($index) {
      $tree = new SourceTree($data);
      $data = $tree->getTree();
    }
    $hash = self::getUniqId($data);

    // Check if this present is the same to avoid duplicates, for example move
    // to the same place.
    if ($check_hash && $hash === $this->present?->hash) {
      return;
    }

    // 1. Insert the present at the end of the past.
    // If it's the very first action, we want a NULL in the past to be able to
    // undo to initial empty state.
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
