<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder_page_layout\AccessControlHandler;
use Drupal\display_builder_page_layout\Form\PageLayoutForm;
use Drupal\display_builder_page_layout\PageLayoutInterface;
use Drupal\display_builder_page_layout\PageLayoutListBuilder;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Defines the page layout entity type.
 */
#[ConfigEntityType(
  id: 'page_layout',
  label: new TranslatableMarkup('Page layout'),
  label_collection: new TranslatableMarkup('Page layouts'),
  label_singular: new TranslatableMarkup('page layout'),
  label_plural: new TranslatableMarkup('page layouts'),
  config_prefix: 'page_layout',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'weight' => 'weight',
    'uuid' => 'uuid',
  ],
  handlers: [
    'access' => AccessControlHandler::class,
    'list_builder' => PageLayoutListBuilder::class,
    'form' => [
      'add' => PageLayoutForm::class,
      'edit' => PageLayoutForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/page-layout',
    'add-form' => '/admin/structure/page-layout/add',
    'edit-form' => '/admin/structure/page-layout/{page_layout}',
    'display-builder' => '/admin/structure/page-layout/{page_layout}/builder',
    'delete-form' => '/admin/structure/page-layout/{page_layout}/delete',
    'duplicate-form' => '/admin/structure/page-layout/{page_layout}/duplicate',
  ],
  admin_permission: 'administer page_layout',
  label_count: [
    'singular' => '@count page layout',
    'plural' => '@count page layouts',
  ],
  config_export: [
    'id',
    'label',
    'weight',
    ConfigFormBuilderInterface::PROFILE_PROPERTY,
    ConfigFormBuilderInterface::SOURCES_PROPERTY,
    'conditions',
  ],
)]
final class PageLayout extends ConfigEntityBase implements PageLayoutInterface {

  /**
   * The ID of the page layout entity.
   *
   * This property's type was changed from `string` to `?string` (nullable)
   * to support the entity duplication process. The original non-nullable type
   * would cause a fatal error, as a new, duplicated entity does not have an
   * ID until it is saved.
   *
   * @var string|null
   *   The unique identifier for the page layout.
   */
  protected ?string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * Weight of this page layout when negotiating the page variant.
   *
   * The first/lowest that is accessible according to conditions is loaded.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * Display Builder Profile ID.
   */
  protected string $profile = '';

  /**
   * A list of sources plugins.
   *
   * @var array
   */
  protected $sources = [];

  /**
   * Condition settings for storage.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * The conditions plugins for this page.
   */
  private ConditionPluginCollection $conditionPluginCollection;

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    return 'page_layout__';
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    return [
      'conditions' => $this->getConditions(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    return 'page';
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::getPrefix())) {
      return NULL;
    }
    [, $page_layout] = \explode('__', $instance_id);

    return [
      'page_layout' => $page_layout,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.page_layout.display_builder', ['page_layout' => $this->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      // Fallback to the list of instances.
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('entity.page_layout.display_builder', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      // Fallback to the list of instances.
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('entity.page_layout.edit_form', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $storage = $this->entityTypeManager()->getStorage('display_builder_profile');
    $profile_id = $this->get(ConfigFormBuilderInterface::PROFILE_PROPERTY);

    if (!$profile_id) {
      return NULL;
    }

    /** @var \Drupal\display_builder\ProfileInterface $builder */
    $builder = $storage->load($profile_id);

    return $builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', self::getPrefix(), $this->id());
  }

  /**
   * {@inheritdoc}
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    return $account->hasPermission('administer page_layout') ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceStorage $storage */
    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->load($this->getInstanceId());

    if (!$instance) {
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    $sources = $this->getSources();

    if (empty($sources)) {
      // Fallback to a fixture mimicking the standard page layout.
      $sources = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_page_layout', 'default_page_layout');
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $contexts = [];
    $contexts = RequirementsContext::addToContext([self::getContextRequirement()], $contexts);

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->sources;
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $this->sources = $this->getInstance()->getCurrentState();
    $this->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions(): ConditionPluginCollection {
    if (!isset($this->conditionPluginCollection)) {
      // Static call because EntityBase and descendants don't support
      // dependency injection.
      $manager = \Drupal::service('plugin.manager.condition');
      $this->conditionPluginCollection = new ConditionPluginCollection($manager, $this->get('conditions'));
    }

    return $this->conditionPluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): PageLayout {
    parent::calculateDependencies();
    $display_builder = $this->getProfile();
    $instance = $this->getInstance();

    if ($display_builder && $instance) {
      $this->addDependency('config', $display_builder->getConfigDependencyName());
      $contexts = $instance->getContexts() ?? [];

      foreach ($this->getSources() as $source_data) {
        /** @var \Drupal\ui_patterns\SourceInterface $source */
        $source = $this->sourceManager()->getSource('', [], $source_data, $contexts);
        $this->addDependencies($source->calculateDependencies());
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    if ($this->getInstance()) {
      $storage = $this->entityTypeManager()->getStorage('display_builder_instance');
      $storage->delete([$this->getInstance()]);
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    $this->initInstanceIfMissing();
    $instance = $this->getInstance();

    // Save the profile in the instance if changed.
    if ($instance->getProfile()->id() !== $this->profile) {
      $instance->setProfile($this->profile);
      $instance->save();
    }

    if ($this->isImpactingPageVariantDetection($update)) {
      // In DisplayBuilderPageVariant we add PageLayout::>getCacheTags() to the
      // page renderable but it works only for the pages already managed by
      // Display Builder.
      // In PageVariantSubscriber::onSelectPageDisplayVariant() we add a custom
      // tag for the others.
      /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
      $entity_type = $this->getEntityType();
      \Drupal::service('cache_tags.invalidator')->invalidateTags([$entity_type->getConfigPrefix()]);
    }

    parent::postSave($storage, $update);
  }

  /**
   * Does the page cache need to be flushed?
   *
   * Flushing a cache is something to be careful enough. Let's flush only when
   * needed.
   *
   * @param bool $update
   *   TRUE if the entity has been updated, or FALSE if it has been inserted.
   *
   * @return bool
   *   TRUE if the cache need to be flushed.
   */
  private function isImpactingPageVariantDetection(bool $update = TRUE): bool {
    // A new active page layout has been added.
    if (!$update && $this->status && !empty($this->sources)) {
      return TRUE;
    }

    // Other additions have no impact.
    if (!$update) {
      return FALSE;
    }

    $previous = $this->originalEntity;

    // Those properties are impacting AccessControlHandler logic and
    // PageVariantSubscriber results.
    foreach (['weight', 'conditions', 'status'] as $property) {
      if ($this->get($property) !== $previous->get($property)) {
        return TRUE;
      }
    }

    // A page layout with empty sources is skipped by AccessControlHandler.
    // This is also altering PageVariantSubscriber results.
    if (empty($this->sources) !== empty($previous->get('sources'))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  private function getInstance(): ?InstanceInterface {
    if (!$this->getInstanceId()) {
      return NULL;
    }

    if (!isset($this->instance)) {
      /** @var \Drupal\display_builder\InstanceInterface|null $instance */
      $instance = $this->entityTypeManager()->getStorage('display_builder_instance')->load($this->getInstanceId());
      $this->instance = $instance;
    }

    return $this->instance;
  }

  /**
   * Gets the UI Patterns Source plugins manager.
   *
   * @return \Drupal\ui_patterns\SourcePluginManager
   *   The manager for source plugins.
   */
  private function sourceManager(): SourcePluginManager {
    return \Drupal::service('plugin.manager.ui_patterns_source');
  }

}
