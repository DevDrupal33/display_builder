<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Entity;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_page_layout\AccessControlHandler;
use Drupal\display_builder_page_layout\Form\PageLayoutForm;
use Drupal\display_builder_page_layout\PageLayoutInterface;
use Drupal\display_builder_page_layout\PageLayoutListBuilder;
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
    DisplayBuildableInterface::PROFILE_PROPERTY,
    DisplayBuildableInterface::SOURCES_PROPERTY,
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
  protected ConditionPluginCollection $conditionPluginCollection;

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
  public function isDefault(): bool {
    return $this->getConditions()->count() === 0;
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
      $contexts = $instance->getAvailableContexts() ?? [];

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
    $this->displayBuildable()->initInstanceIfMissing();
    $instance = $this->getInstance();

    if ($this->isImpactingPageVariantDetection($update)) {
      // In DisplayBuilderPageVariant we add PageLayout::>getCacheTags() to the
      // page renderable but it works only for the pages already managed by
      // Display Builder.
      // In PageVariantSubscriber::onSelectPageDisplayVariant() we add a custom
      // tag for the others.
      /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
      $entity_type = $this->getEntityType();
      $this->cacheTagsInvalidator()->invalidateTags([$entity_type->getConfigPrefix()]);
    }

    // Reset the state once you import a configuration.
    if ($instance && $this->isSyncing()) {
      $log = new TranslatableMarkup('Synchronize display from imported configuration');
      $current_sources = $this->getSources();
      $instance->setNewPresent($current_sources, $log);
      $instance->save();
    }
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $profile_id = $this->get(DisplayBuildableInterface::PROFILE_PROPERTY);

    if (!$profile_id) {
      return NULL;
    }

    $storage = $this->entityTypeManager()->getStorage('display_builder_profile');
    /** @var \Drupal\display_builder\Entity\ProfileInterface $builder */
    $builder = $storage->load($profile_id);

    return $builder;
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
  public function setSources(array $sources): void {
    $this->sources = $sources;
  }

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  protected function getInstance(): ?InstanceInterface {
    if (!$this->displayBuildable()->getInstanceId()) {
      return NULL;
    }

    if (!isset($this->instance)) {
      $instance_id = $this->displayBuildable()->getInstanceId();
      /** @var \Drupal\display_builder\InstanceInterface|null $instance */
      $instance = $this->entityTypeManager()->getStorage('display_builder_instance')->load($instance_id);
      $this->instance = $instance;
    }

    return $this->instance;
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
   * Gets the display buildable manager.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The manager for display buildable.
   */
  private function displayBuildable(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildablePluginManager $manager */
    $manager = \Drupal::service('plugin.manager.display_buildable');
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $manager->createInstance('page_layout', ['entity' => $this]);

    return $buildable;
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

  /**
   * Gets the Cache Tags Invalidator service.
   *
   * @return \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   *   The cache tags invalidator service.
   */
  private function cacheTagsInvalidator(): CacheTagsInvalidatorInterface {
    return \Drupal::service('cache_tags.invalidator');
  }

}
