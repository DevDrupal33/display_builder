<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Entity;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\InstanceInterface;
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
   * The example ID.
   */
  protected string $id;

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
   * Display Builder ID.
   */
  protected string $display_builder = '';

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
    if (!\str_starts_with($instance_id, 'page_layout__')) {
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

    return Url::fromRoute('entity.page_layout.display_builder', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    return Url::fromRoute('entity.page_layout.edit_form', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface {
    $storage = $this->entityTypeManager()->getStorage('display_builder');
    $profile_id = $this->get(ConfigFormBuilderInterface::PROFILE_PROPERTY);

    if (!$profile_id) {
      return NULL;
    }

    /** @var \Drupal\display_builder\DisplayBuilderInterface $builder */
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

    // Examples: page_layout__default, page_layout__users.
    return 'page_layout__' . $this->id();
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
    $display_builder = $this->getDisplayBuilder();
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
    parent::postSave($storage, $update);
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
