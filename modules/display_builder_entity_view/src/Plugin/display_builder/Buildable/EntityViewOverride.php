<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\display_builder\Buildable;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildableOverrideInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayReference;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder_entity_view\BuilderDataConverter;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface;
use Drupal\display_builder_entity_view\EntityCanonicalRouteTrait;
use Drupal\display_builder_entity_view\EntityDisplayLabelTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'entity_view_override',
  label: new TranslatableMarkup('Entity view override'),
  instance_prefix: 'entity_override__',
)]
final class EntityViewOverride extends DisplayBuildablePluginBase implements DisplayBuildableOverrideInterface {

  use EntityDisplayLabelTrait;
  use EntityCanonicalRouteTrait;

  /**
   * How many recent overrides a listing returns per display, by default.
   *
   * Nobody navigates to an override through a panel, they arrive from the
   * node, so this is a "did I leave one somewhere" list, not an index.
   */
  private const DEFAULT_LIMIT = 25;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * The data converter from Manage Display and Layout Builder.
   */
  protected BuilderDataConverter $dataConverter;

  /**
   * The field items where the override is stored, once ::getField() ran.
   */
  protected ?FieldItemListInterface $field = NULL;

  /**
   * The fieldable entity owning the override field, when passed in.
   */
  protected ?ContentEntityInterface $entity = NULL;

  /**
   * The overridden display, once passed in or loaded by ::getDisplay().
   */
  protected ?DisplayBuilderEntityDisplayInterface $display = NULL;

  /**
   * {@inheritdoc}
   *
   * Configuration, as stored in the Instance entity:
   * - display_id (string)
   * - entity_id (string)
   *
   * The display and the overriding entity may also be passed as 'display' and
   * 'entity', to work on objects the caller already holds rather than reloaded
   * copies of them.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (isset($configuration['display'])) {
      $this->display = $configuration['display'];
      unset($this->configuration['display']);
      $this->configuration['display_id'] = $this->display->id();
    }

    if (isset($configuration['entity'])) {
      $this->entity = $configuration['entity'];
      unset($this->configuration['entity']);
      $this->configuration['entity_id'] = $this->entity->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->time = $container->get('datetime.time');
    $instance->dataConverter = $container->get('display_builder_entity_view.builder_data_converter');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Named after the entity, not just its bundle: two overrides of the same
   * view mode share a bundle and a view mode, so the entity's ID and label are
   * the only things telling them apart in a listing. The bundle still leads,
   * so overrides of the same type sort and scan together.
   */
  public function getDisplayLabel(): ?string {
    // Through ::getField(), not the property: the field is resolved lazily, so
    // reading it raw only works when something else happened to resolve it
    // first. ::collectDisplays() asks for the instance ID before the label and
    // hid this for a while.
    $entity = $this->getField()?->getEntity();

    if (!$entity || !$this->display) {
      return NULL;
    }

    $subject = \sprintf(
      '%s [%s]',
      $this->getBundleLabel($entity->getEntityTypeId(), $entity->bundle()),
      (string) $entity->id(),
    );

    return $this->composeDisplayLabel(
      $subject,
      $this->getViewModeLabel($this->display->getTargetEntityTypeId(), $this->display->getMode()),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $field = $this->getField();
    $display = $this->getDisplay();
    \assert(\is_string($field->getName()));
    $entity_type_id = $display->getTargetEntityTypeId();
    $parameters = [
      $entity_type_id => $field->getEntity()->id(),
      'view_mode_name' => $display->getMode(),
    ];

    return Url::fromRoute(\sprintf('entity.%s.display_builder.%s', $entity_type_id, $display->getMode()), $parameters);
  }

  /**
   * {@inheritdoc}
   *
   * No admin page lists overrides on their own - each is configured on the
   * content entity it belongs to. The Instances panel's group heading links
   * to the flat instance list instead, pre-filtered to this buildable's own
   * kind.
   */
  public function getCollectionUrl(): Url {
    return Url::fromRoute('entity.display_builder_instance.collection', [], [
      'query' => ['context' => $this->getPluginId()],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::getPrefix())) {
      return NULL;
    }
    [, $entity_type_id, $entity_id, $field_name] = \explode('__', $instance_id);

    return [
      'entity_type_id' => $entity_type_id,
      'entity_id' => $entity_id,
      'field_name' => $field_name,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    [, $entity_type_id, $entity_id, $field_name] = \explode('__', $instance_id);

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    if (!$entity) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    $display = self::getEntityViewDisplay($entity_type_id, $entity->bundle(), $field_name);

    if (!$display) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }
    $params = [
      $entity_type_id => $entity_id,
      'view_mode_name' => $display->getMode(),
    ];

    $route_name = \sprintf('entity.%s.display_builder.%s', $entity_type_id, $display->getMode());

    return Url::fromRoute($route_name, $params);
  }

  /**
   * {@inheritdoc}
   *
   * An override has no config entity of its own to point back to - the
   * closest equivalent is the entity it overrides.
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }
    $entity = \Drupal::entityTypeManager()->getStorage($params['entity_type_id'])->load($params['entity_id']);

    if (!$entity || !$entity->hasLinkTemplate('canonical')) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return $entity->toUrl('canonical');
  }

  /**
   * {@inheritdoc}
   *
   * An override belongs to one entity, so the entity's own page is the page it
   * appears on. That gives the preview the Page Layout the canonical path
   * really resolves to, the real title and breadcrumb, and the real entity
   * template - none of which a display rendered on its own can produce.
   *
   * Only what @see ::previewWithChrome() also treats as a full page. Every
   * other mode is a fragment shown inside some other page (a teaser in a
   * listing, a card in a block), and the canonical page never renders it, so
   * previewing it there would show the wrong display and claim it was right.
   */
  public function getPreviewPagePath(): ?string {
    $display = $this->getDisplay();

    if ($display === NULL || !$this->previewsFullPage($display->getTargetEntityTypeId(), $display->getTargetBundle(), $display->getMode())) {
      return NULL;
    }
    $entity = $this->getField()?->getEntity();

    if (!$entity instanceof ContentEntityInterface || $entity->isNew()) {
      return NULL;
    }

    if (!$entity->getEntityType()->hasLinkTemplate('canonical')) {
      return NULL;
    }
    $url = $entity->toUrl('canonical');

    // The internal path, not ::toString(): the contract is a site path, and
    // the caller resolves it with Url::fromUserInput(), which measures from
    // the site root. A base path baked in here routes nowhere on a
    // subdirectory install.
    return $url->isRouted() ? '/' . $url->getInternalPath() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $profile_id = $this->getDisplay()->getThirdPartySetting('display_builder', DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY);

    if ($profile_id === NULL) {
      return NULL;
    }

    /** @var \Drupal\display_builder\Entity\ProfileInterface $profile */
    $profile = $this->entityTypeManager->getStorage('display_builder_profile')->load($profile_id);

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->getField()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $data = $this->getInstance()->getCurrentState();
    $field = $this->getField();
    $entity = $field->getEntity();

    if ($entity instanceof ContentEntityInterface) {
      $this->setRevision($entity);
    }
    $entity->save();
    $field->setValue($data);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getOverridden(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance(
      'entity_view',
      ['display' => $this->getDisplay()]
    );

    return $buildable;
  }

  /**
   * {@inheritdoc}
   */
  public function revert(): array {
    $field = $this->getField();
    $field->setValue(NULL);
    $field->getEntity()->save();

    return $this->getDisplay()->getThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, []);
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCardinality(): int {
    $field = $this->getField();

    if (!$field) {
      return self::CARDINALITY_UNLIMITED;
    }

    return $field->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
  }

  /**
   * {@inheritdoc}
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    [, $entity_type_id, $entity_id] = \explode('__', $instance_id);
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    if (!$entity) {
      return AccessResult::neutral();
    }

    return $entity->access('update', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    $field = $this->getField();
    $entity = $field->getEntity();

    // Usually an entity is new if no ID exists for it yet.
    if ($entity->isNew()) {
      return NULL;
    }

    return \sprintf(
      '%s%s__%s__%s',
      self::getPrefix(),
      $entity->getEntityTypeId(),
      $entity->id(),
      $field->getName()
    );
  }

  /**
   * Set revision if appropriate.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to set revision if appropriate.
   */
  public function setRevision(ContentEntityInterface $entity): void {
    if (!$entity->getEntityType()->isRevisionable()) {
      return;
    }

    $bundle = $entity->getBundleEntity();

    if ($bundle instanceof RevisionableEntityBundleInterface
      && !$bundle->shouldCreateNewRevision()
    ) {
      return;
    }

    $entity->setNewRevision();

    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionLogMessage($this->t('Updated using Display Builder.')->render());
      $entity->setRevisionCreationTime($this->time->getCurrentTime());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function collectInstances(): array {
    $instances = [];
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    $entity_query = $entity_storage = [];

    foreach ($storage->loadMultiple() as $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $display_builder = $display->getThirdPartySettings('display_builder');

      if (!isset($display_builder[DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY], $display_builder[DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY])) {
        continue;
      }

      $entity_type = $display->getTargetEntityTypeId();

      if (!isset($display_builder[DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY])) {
        continue;
      }

      $field_name = $display_builder[DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY];
      $entity_storage[$entity_type] ??= $this->entityTypeManager->getStorage($entity_type);
      $entity_query[$entity_type] ??= $entity_storage[$entity_type]->getQuery()->accessCheck(FALSE);
      $instances = \array_merge($instances, $this->collectInstancesByField($field_name, $display, $entity_query[$entity_type]));
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   *
   * Bounded, unlike ::collectInstances(): that one queries every entity
   * carrying an override field with access checks off and loads each result,
   * so a site with a few thousand overridden nodes tries to list a few
   * thousand rows in a sidebar.
   *
   * Here the query is capped, ordered newest first where the entity type has a
   * changed field, and access checked. The result is "recent overrides", which
   * ::collectDisplaysBound() is what tells the user.
   */
  public function collectDisplays(array $options = []): array {
    $limit = (int) ($options['limit'] ?? self::DEFAULT_LIMIT);
    $references = [];

    foreach ($this->entityTypeManager->getStorage('entity_view_display')->loadMultiple() as $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $settings = $display->getThirdPartySettings('display_builder');

      if (!isset($settings[DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY], $settings[DisplayBuildableOverrideInterface::OVERRIDE_PROFILE_PROPERTY])) {
        continue;
      }

      $entity_type_id = $display->getTargetEntityTypeId();
      $field_name = $settings[DisplayBuildableOverrideInterface::OVERRIDE_FIELD_PROPERTY];
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

      $query = $storage->getQuery()
        ->accessCheck(TRUE)
        ->exists($field_name)
        ->range(0, $limit);

      // The bundle key is named after the entity type, and some types have no
      // bundles at all.
      $bundle_key = $entity_type->getKey('bundle');

      if ($bundle_key) {
        $query->condition($bundle_key, $display->getTargetBundle());
      }

      // Newest first, so a capped list is the useful half rather than an
      // arbitrary one. Not every entity type records a change date, so fall
      // back to the id, which at least correlates with creation order.
      $changed = $entity_type->entityClassImplements(EntityChangedInterface::class);
      $query->sort($changed ? 'changed' : (string) $entity_type->getKey('id'), 'DESC');

      /** @var array $ids */
      $ids = $query->execute();

      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      foreach ($storage->loadMultiple($ids) as $entity) {
        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('entity_view_override', [
          'display' => $display,
          'entity' => $entity,
        ]);
        $instance_id = $buildable->getInstanceId();

        if ($instance_id === NULL) {
          continue;
        }

        $references[] = new DisplayReference(
          instanceId: $instance_id,
          kind: $buildable->label(),
          label: $buildable->getDisplayLabel() ?? $instance_id,
          url: $buildable->getBuilderUrl(),
          empty: $entity->get($field_name)->isEmpty(),
          settingsUrl: self::entityEditUrl($entity),
          // The label names the bundle and the entity id, and an id tells a
          // human nothing about which node this is.
          detail: (string) $entity->label(),
          // Same array ::getSources() returns for this buildable, just read
          // straight off the field already loaded above.
          publishedHash: Instance::getUniqId($entity->get($field_name)->getValue()),
        );
      }
    }

    return $references;
  }

  /**
   * {@inheritdoc}
   *
   * The only bounded collection: one row per overridden entity is the one
   * count here that follows content rather than configuration, so it is the
   * one listing that cannot promise to be complete.
   */
  public function collectDisplaysBound(): TranslatableMarkup {
    return new TranslatableMarkup('Most recent @count per display.', ['@count' => self::DEFAULT_LIMIT]);
  }

  /**
   * Gets entity_view_display information grouped by entity type.
   *
   * @todo should be replaced by service, see #3542273
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   *
   * @return array
   *   An array of display information keyed with 'node', then 'modes' and
   *   'bundles':
   *
   *   @code
   *   [
   *     'node' => [
   *       'modes' => [
   *         'teaser' => 'Teaser',
   *       ],
   *      'bundles' => [
   *        'article' => [
   *          'teaser' => 'Teaser',
   *        ],
   *      ],
   *    ],
   *   ];
   *
   *   @endcode
   */
  public static function getDisplayInfos(EntityTypeManagerInterface $entityTypeManager): array {
    /** @var \Drupal\display_builder_entity_view\Entity\EntityViewDisplay[] $displays */
    $displays = $entityTypeManager
      ->getStorage('entity_view_display')
      ->loadMultiple();
    $view_mode_storage = $entityTypeManager->getStorage('entity_view_mode');
    $tabs_info = [];

    foreach ($displays as $display) {
      if (!$display->getDisplayBuilderOverrideField()) {
        continue;
      }

      $entity_type_id = $display->getTargetEntityTypeId();
      $view_mode = $view_mode_storage->load(\sprintf('%s.%s', $entity_type_id, $display->getMode()));
      $tabs_info[$entity_type_id]['modes'][$display->getMode()] = $view_mode?->label() ?? \t('Default');
      $tabs_info[$entity_type_id]['bundles'][$display->getTargetBundle()][$display->getMode()] = $view_mode?->label() ?? \t('Default');
    }

    return $tabs_info;
  }

  /**
   * {@inheritdoc}
   */
  public function previewWithChrome(): bool {
    $display = $this->getDisplay();

    return $this->previewsFullPage($display->getTargetEntityTypeId(), $display->getTargetBundle(), $display->getMode());
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    $contexts = [];
    $entity = $this->getField()->getEntity();
    $contexts['entity'] = EntityContext::fromEntity($entity);
    $contexts['view_mode'] = new Context(ContextDefinition::create('string'), $this->getDisplay()->getMode());
    $contexts['bundle'] = new Context(ContextDefinition::create('string'), $entity->bundle());
    return RequirementsContext::addToContext(['content'], $contexts);
  }

  /**
   * Gets the overridden entity view display.
   *
   * Loaded on demand: the constructor runs before ::create() has injected the
   * entity type manager.
   *
   * @return \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface|null
   *   The display, or NULL if the configured one no longer exists.
   */
  protected function getDisplay(): ?DisplayBuilderEntityDisplayInterface {
    if ($this->display === NULL) {
      /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface|null $display */
      $display = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($this->configuration['display_id'] ?? '');
      $this->display = $display;
    }

    return $this->display;
  }

  /**
   * Gets the field items holding the override.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|null
   *   The field items, or NULL if the overriding entity no longer exists.
   */
  protected function getField(): ?FieldItemListInterface {
    if ($this->field !== NULL) {
      return $this->field;
    }
    $display = $this->getDisplay();
    $entity = $this->entity ?? $this->entityTypeManager
      ->getStorage($display->getTargetEntityTypeId())
      ->load($this->configuration['entity_id']);

    if (!$entity instanceof ContentEntityInterface) {
      return NULL;
    }
    $this->field = $entity->get($display->getDisplayBuilderOverrideField());

    return $this->field;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    if ($this->initialDataSource === 'display_builder') {
      return $this->t('Copy display from Entity View Display configuration');
    }

    if ($this->initialDataSource === 'layout_builder_override') {
      return $this->t('Import display from Layout Builder override');
    }

    return $this->t('Initialize display from existing content');
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitialSources(): array {
    $sources = $this->getSources();

    // 1. Keep the existing override value if existing.
    if (\count($sources) > 0) {
      return $sources;
    }

    // 2. Convert the Layout Builder Override if exists.
    // There is always a single Layout Builder override per bundle: `default`.
    // There could be many Display Builder overrides per bundle, one for each
    // display, so we need to check.
    if ($this->getDisplay()->getMode() === 'default') {
      $entity = $this->getField()->getEntity();
      // @see: use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage::$FIELD_NAME
      $field_name = 'layout_builder__layout';

      if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
        $content = $entity->get($field_name)->first()->getValue();
        $this->initialDataSource = 'layout_builder_override';

        return $this->dataConverter->convertFromLayoutBuilder($content);
      }
    }

    // 3. Copy entity view display value.
    \assert(\is_string($this->getField()->getName()));
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->getOverridden();

    if ($buildable->getProfile() !== NULL) {
      $sources = $buildable->getSources();
    }
    $this->initialDataSource = 'display_builder';

    return $sources;
  }

  /**
   * Collect instances by field storage.
   *
   * @param string $field_name
   *   Field name.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   Entity view display.
   * @param \Drupal\Core\Entity\Query\QueryInterface $entity_query
   *   Entity query handler.
   *
   * @return \Drupal\display_builder\InstanceInterface[]
   *   A associative array of Instance entities.
   */
  protected function collectInstancesByField(string $field_name, EntityViewDisplayInterface $display, QueryInterface $entity_query): array {
    $instances = [];
    $entity_query->exists($field_name);
    // QueryInterface::execute() returns an integer for count queries or an
    // array of ids.
    /** @var array $ids */
    $ids = $entity_query->execute();

    foreach ($ids as $id) {
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance(
        'entity_view_override',
        [
          'entity_id' => $id,
          'display' => $display,
        ]
      );
      $buildable->initInstanceIfMissing();
      $instance = $buildable->getInstance();
      $instances[$instance->id()] = $instance;
    }

    return $instances;
  }

  /**
   * Where an override is configured: the entity's own edit form.
   *
   * An override belongs to one piece of content, so the page that owns it is
   * that content's edit form, not the parent display's Manage display. Manage
   * display is where somebody enabled the override field once; nobody returns
   * there holding a single node.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The overridden entity.
   *
   * @return \Drupal\Core\Url|null
   *   The URL, or NULL when the entity type has no edit form or this user may
   *   not open it.
   */
  private static function entityEditUrl(FieldableEntityInterface $entity): ?Url {
    if (!$entity->getEntityType()->hasLinkTemplate('edit-form')) {
      return NULL;
    }

    // The listing query is access checked for viewing, which says nothing
    // about editing: seeing an override in the panel and being allowed to
    // change the node it lives on are two different permissions.
    return $entity->access('update') ? $entity->toUrl('edit-form') : NULL;
  }

  /**
   * Get entity view display entity.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   * @param string $bundle
   *   Entity's bundle which support fields.
   * @param string $fieldName
   *   Field name of the display.
   *
   * @return \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface|null
   *   The corresponding entity view display.
   */
  private static function getEntityViewDisplay(string $entity_type_id, string $bundle, string $fieldName): ?DisplayBuilderEntityDisplayInterface {
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface[] $displays */
    $displays = \Drupal::entityTypeManager()->getStorage('entity_view_display')->loadByProperties([
      'targetEntityType' => $entity_type_id,
    ]);

    foreach ($displays as $display) {
      if ($display instanceof DisplayBuilderEntityDisplayInterface
        && $display->getDisplayBuilderOverrideField() === $fieldName
        && $display->getTargetEntityTypeId()
        && $display->getTargetBundle() === $bundle
      ) {
        return $display;
      }
    }

    return NULL;
  }

}
