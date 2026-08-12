<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogEntityTrait;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\Exception\InvalidNodeException;
use Drupal\display_builder\InstanceAccessControlHandler;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder\SourceTree;
use Drupal\display_builder_ui\InstanceListBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

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
    'revision' => 'revision',
    'langcode' => 'langcode',
    'default_langcode' => 'default_langcode',
  ],
  handlers: [
    'access' => InstanceAccessControlHandler::class,
    'storage' => InstanceStorage::class,
    'storage_schema' => SqlContentEntityStorageSchema::class,
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
  translatable: TRUE,
  base_table: 'display_builder_instance',
  data_table: 'display_builder_instance_field_data',
  revision_table: 'display_builder_instance_revision',
  revision_data_table: 'display_builder_instance_field_revision',
  revision_metadata_keys: [
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
  ],
)]
class Instance extends ContentEntityBase implements InstanceInterface {

  use RevisionLogEntityTrait;

  /**
   * Current user.
   */
  public AccountInterface $currentUser;

  /**
   * Entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * Slot source proxy for resolving node labels.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * Cached normalized source tree for the current present state.
   *
   * Stays valid after mutations (index=FALSE path) and is cleared on undo/redo
   * when the present pointer jumps to a different history step.
   */
  protected ?SourceTree $sourceTree = NULL;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    // Add the revision metadata fields.
    $fields += static::revisionLogBaseFieldDefinitions($entity_type);
    // Override from ContentEntityBase.
    $fields['id'] = BaseFieldDefinition::create('string')->setRequired(TRUE)->setReadOnly(TRUE);
    $fields['buildable'] = BaseFieldDefinition::create('plugin')
      ->setSetting('plugin_manager_id', 'plugin.manager.display_buildable')
      ->setRequired(TRUE)
      ->setReadOnly(TRUE);
    $fields['sources'] = BaseFieldDefinition::create('ui_patterns_source')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $fields['hash'] = BaseFieldDefinition::create('integer')
      ->setRevisionable(TRUE)
      ->setSetting('size', 'big');
    $fields['published'] = BaseFieldDefinition::create('timestamp');

    return $fields;
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
  public function postCreate(EntityStorageInterface $storage): void {
    $sources = $this->get('sources')->getValue() ?? [];

    if (empty($sources)) {
      return;
    }

    $this->sourceTree = new SourceTree($sources);
    $indexed = $this->sourceTree->getTree();

    $hash = self::getUniqId($indexed);
    $this->set('sources', $indexed);
    $this->set('hash', $hash);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    return $this->getBuildablePlugin()->getProfile();
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

    $log = new TranslatableMarkup('Move @label to root', ['@label' => $this->nodeLabel($data)]);
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

    $parentData = $tree->getNodeData($parent_id);
    $log = new TranslatableMarkup('Move @label to slot @slot_id in @parent_label', [
      '@label' => $this->nodeLabel($data),
      '@slot_id' => $slot_id,
      '@parent_label' => $parentData ? $this->nodeLabel($parentData) : $parent_id,
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

    $nodeData = $tree->getNodeData($node_id);
    $log = new TranslatableMarkup('Attach @label to root', ['@label' => $this->nodeLabel($nodeData ?? [])]);
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
      throw new InvalidNodeException('Parent or slot not found');
    }

    if ($third_party_settings) {
      foreach ($third_party_settings as $island_id => $settings) {
        $tree->setThirdPartySettings($node_id, $island_id, $settings);
      }
    }

    $nodeData = $tree->getNodeData($node_id);
    $parentData = $tree->getNodeData($parent_id);
    $log = new TranslatableMarkup('Attach @label to slot @slot_id in @parent_label', [
      '@label' => $this->nodeLabel($nodeData ?? []),
      '@slot_id' => $slot_id,
      '@parent_label' => $parentData ? $this->nodeLabel($parentData) : $parent_id,
    ]);
    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);

    return $node_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getNode(string $node_id): array {
    return $this->getSourceTree()->getNode($node_id) ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(string $node_id): ?string {
    return $this->getSourceTree()->getParentId($node_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setSource(string $node_id, string $source_id, array $data): void {
    $tree = $this->getSourceTree();

    if (!$tree->setSource($node_id, $source_id, $data)) {
      throw new InvalidNodeException('Internal node ID mismatch');
    }

    $nodeData = $tree->getNodeData($node_id);
    $log = new TranslatableMarkup('Update @label configuration', ['@label' => $this->nodeLabel($nodeData ?? [])]);
    $this->setNewPresent($tree->getTree(), $log, TRUE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function setThirdPartySettings(string $node_id, string $island_id, array $data): void {
    $tree = $this->getSourceTree();
    $nodeData = $tree->getNodeData($node_id);

    if (!$tree->setThirdPartySettings($node_id, $island_id, $data)) {
      return;
    }

    $log = new TranslatableMarkup('Update @island_id configuration in @label', [
      '@label' => $this->nodeLabel($nodeData),
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

    $tree->remove($node_id);

    $parentData = $parent_id === NULL ? NULL : $tree->getNodeData($parent_id);
    $log = new TranslatableMarkup('Remove @label from @parent_label', [
      '@label' => $this->nodeLabel($data),
      '@parent_label' => $parentData ? $this->nodeLabel($parentData) : 'root',
    ]);
    $this->setNewPresent($tree->getTree(), $log, FALSE, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): ?array {
    $contexts = $this->getBuildablePlugin()->getRuntimeContexts($unqualified_context_ids) ?? [];

    return $this->refreshContexts($contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getCurrentState(): array {
    return $this->get('sources')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function publish(): void {
    $this->getBuildablePlugin()->saveSources();
    $this->set('published', \Drupal::time()->getRequestTime());
    $this->setNewRevision(FALSE);
    $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public function restore(): void {
    $this->setNewPresent($this->getBuildablePlugin()->getSources(), new TranslatableMarkup('Restore published data.'));
  }

  /**
   * {@inheritdoc}
   */
  public function revert(): void {
    $sources = $this->getBuildablePlugin()->revertSources();
    $this->setNewPresent($sources, new TranslatableMarkup('Revert to default display.'));
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getPast(): array {
    return $this->getStorage()->getPast($this);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function getFuture(): array {
    return $this->getStorage()->getFuture($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers(): array {
    return $this->getStorage()->getUsers($this);
  }

  /**
   * {@inheritdoc}
   */
  public function isPublishable(): bool {
    $contexts = $this->getAvailableContexts();

    if (!\array_key_exists('context_requirements', $contexts)
      || !($contexts['context_requirements'] instanceof RequirementsContext)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublishedHash(): ?int {
    $published_data = $this->getBuildablePlugin()->getSources();

    return $published_data ? self::getUniqId($published_data) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublishedTime(): ?int {
    $time = (int) $this->get('published')->getString();

    return ($time === 0) ? NULL : $time;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished(): bool {
    return (bool) $this->getBuildablePlugin()->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function isPublishedPresent(): bool {
    return $this->getHash() === $this->getPublishedHash();
  }

  /**
   * {@inheritdoc}
   */
  public function getPathIndex(): array {
    return $this->getSourceTree()->getPathIndex();
  }

  /**
   * {@inheritdoc}
   */
  public function getHash(): ?int {
    if ($hash = $this->get('hash')->first()?->getString()) {
      return (int) $hash;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\display_builder\HistoryInterface
   */
  public function setNewPresent(array $data, string|\Stringable $log_message = '', bool $check_hash = TRUE, bool $index = TRUE): void {
    if ($index) {
      // Raw data needs normalizing; build tree and keep it as the new cache.
      $this->sourceTree = new SourceTree($data);
      $data = $this->sourceTree->getTree();
    }
    // When index=FALSE the data was produced by the cached tree's getTree(),
    // so $this->sourceTree already reflects the new state — no invalidation.
    $hash = self::getUniqId($data);

    // Check if this present is the same to avoid duplicates, for example move
    // to the same place.
    if ($check_hash && $hash === $this->getHash()) {
      return;
    }

    // Root is always a list of sources.
    $data = \array_is_list($data) ? $data : [$data];

    $this->setNewRevision(TRUE);
    $this->set('sources', $data);
    $this->set('hash', $hash);
    $this->setRevisionLogMessage((string) $log_message);
    $this->setRevisionUserId((int) $this->currentUser()->id());
    $this->setRevisionCreationTime(\time());
    $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getUniqId(array $data): int {
    $data = self::normalizeRootLevel($data);

    return \crc32((string) \serialize($data));
  }

  /**
   * Get display buildable plugin.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   A display buildable plugin instance.
   */
  private function getBuildablePlugin(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\PluginItem $item */
    $item = $this->get('buildable')->first();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $item->getInstance();

    return $buildable;
  }

  /**
   * Normalize root level of the data tree.
   *
   * ::isPublishedPresent() is comparing hashes from two sources data trees:
   * - the one from permanent storage where the data is not altered: the hash
   * of the data stored is the hash of the data retrieved.
   * - the one from Instance entity where we use a UI Patterns Source field.
   * Each source is a field item, each property (node_id, source_id, source,
   * third_party_settings) is a field property. So, Field API can reorder the
   * properties and fill the missing properties with empty values, altering the
   * hash calculation and making this comparison difficult.
   *
   * To be used only in ::getUniqId().
   */
  private static function normalizeRootLevel(array $data): array {
    $data = \array_is_list($data) ? $data : [$data];

    foreach ($data as $index => $source) {
      \ksort($source);
      $data[$index] = \array_filter($source);
    }

    return $data;
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
   * Slot source proxy lazy loader.
   */
  private function slotSourceProxy(): SlotSourceProxy {
    return $this->slotSourceProxy ??= \Drupal::service('display_builder.slot_sources_proxy');
  }

  /**
   * Returns the human-readable label for a node, falling back to source_id.
   */
  private function nodeLabel(array $data): string {
    $label = $this->slotSourceProxy()->getLabelWithSummary($data, [], TRUE)['label'];

    return $label !== '' ? $label : ($data['source_id'] ?? '');
  }

  /**
   * Current user.
   */
  private function currentUser(): AccountInterface {
    return $this->currentUser ??= \Drupal::service('current_user');
  }

  /**
   * Get entity storage.
   */
  private function getStorage(): InstanceStorage {
    /** @var \Drupal\display_builder\Entity\InstanceStorage $storage */
    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');

    return $storage;
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

}
