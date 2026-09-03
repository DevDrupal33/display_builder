<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayReference;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder_page_layout\BuilderDataConverter;
use Drupal\display_builder_page_layout\PageLayoutInterface;
use Drupal\display_builder_page_layout\StartingPointType;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'page_layout',
  label: new TranslatableMarkup('Page layout'),
  instance_prefix: 'page_layout__',
)]
final class PageLayout extends DisplayBuildablePluginBase {

  /**
   * The page layout entity, once passed in or loaded by ::getEntity().
   */
  protected ?PageLayoutInterface $entity = NULL;

  /**
   * The builder data converter.
   */
  protected BuilderDataConverter $dataConverter;

  /**
   * The current path stack.
   */
  protected CurrentPathStack $currentPath;

  /**
   * The plugin cache clearer.
   */
  protected CachedDiscoveryClearerInterface $pluginCacheClearer;

  /**
   * {@inheritdoc}
   *
   * Configuration, as stored in the Instance entity:
   * - entity_id (string): Page layout entity ID.
   *
   * The page layout itself may also be passed as 'entity', to work on an
   * object the caller already holds rather than a reloaded copy of it.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset($configuration['entity'])) {
      return;
    }
    $this->entity = $configuration['entity'];
    unset($this->configuration['entity']);
    $this->configuration['entity_id'] = $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dataConverter = $container->get('display_builder_page_layout.builder_data_converter');
    $instance->currentPath = $container->get('path.current');
    $instance->pluginCacheClearer = $container->get('plugin.cache_clearer');

    return $instance;
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
   *
   * A page layout has no parent to disambiguate it: its own label is already
   * the whole name.
   */
  public function getDisplayLabel(): ?string {
    // Through ::getEntity(), not the property: the layout is resolved lazily,
    // so reading it raw only names a plugin somebody handed the object to. A
    // plugin built from a stored entity_id - which is how Instance::label()
    // builds it - would fall back to the ID-derived name for a layout that is
    // right there in config.
    $label = $this->getEntity()?->label();

    return $label === NULL ? NULL : (string) $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.page_layout.display_builder', ['page_layout' => $this->getEntity()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionUrl(): Url {
    return Url::fromRoute('entity.page_layout.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getAddUrl(): Url {
    return Url::fromRoute('entity.page_layout.add_form');
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
    return $this->getEntity()?->getProfile();
  }

  /**
   * {@inheritdoc}
   *
   * A page layout has no canonical URL - it is applied to pages by conditions.
   * Only when a single, concrete request_path pins it to one page is there an
   * unambiguous page to preview it on.
   */
  public function getPreviewPagePath(): ?string {
    $conditions = $this->getEntity()->getConditions();

    // Conditions are keyed by plugin ID, so there is at most one of these. The
    // others (role, language, ...) don't constrain the path.
    if (!$conditions->has('request_path')) {
      return NULL;
    }
    $condition = $conditions->get('request_path');

    if ($condition->isNegated()) {
      // "Not on this page" gives no page to preview on.
      return NULL;
    }
    $lines = \preg_split('/\R/', (string) ($condition->getConfiguration()['pages'] ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    $paths = \array_values(\array_filter(\array_map('trim', $lines)));

    if (\count($paths) !== 1) {
      return NULL;
    }
    $path = $paths[0];

    // Wildcards and non-rooted paths are not a single addressable page.
    if ($path !== '<front>' && (\str_contains($path, '*') || !\str_starts_with($path, '/'))) {
      return NULL;
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   *
   * A page layout draws its own header and footer; wrapping its preview in
   * another page's chrome would show both twice.
   */
  public function previewWithChrome(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    // Drop the memoized entity so ::getEntity() reloads it: the Drupal cache
    // is very strong on this data.
    $this->entity = NULL;

    return $this->getEntity()->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $entity = $this->getEntity();
    $entity->setSources($this->getInstance()->getCurrentState());
    $entity->save();
    // Clearing plugin cache seems enough to get the new layout.
    // @todo It looks very costly. Check if it is still needed.
    $this->pluginCacheClearer->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function collectInstances(): array {
    $instances = [];
    $entities = $this->entityTypeManager->getStorage('page_layout')->loadMultiple();

    foreach ($entities as $page_layout) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);
      $buildable->initInstanceIfMissing();
      $instance_id = $buildable->getInstanceId();
      $instances[$instance_id] = $buildable->getInstance();
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   *
   * A page layout is always a Display Builder display, so there is no
   * "not built" case here. Sources are read off the entity rather than through
   * ::getSources(), which reloads from storage on every call.
   */
  public function collectDisplays(array $options = []): array {
    $references = [];

    foreach ($this->entityTypeManager->getStorage('page_layout')->loadMultiple() as $page_layout) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);
      $instance_id = $buildable->getInstanceId();

      if ($instance_id === NULL) {
        continue;
      }

      $sources = $page_layout->getSources();

      $references[] = new DisplayReference(
        instanceId: $instance_id,
        kind: $buildable->label(),
        label: $buildable->getDisplayLabel() ?? $instance_id,
        url: $buildable->getBuilderUrl(),
        empty: empty($sources),
        disabled: !$page_layout->status(),
        settingsUrl: self::getDisplayUrlFromInstanceId($instance_id),
        publishedHash: Instance::getUniqId($sources),
      );
    }

    return $references;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->getEntity()->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', self::getPrefix(), $this->getEntity()->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    $contexts = [];
    $contexts = RequirementsContext::addToContext(['page'], $contexts);
    // UI Patterns is replacing context_requirements with real contexts, matched
    // by array key against the sources' context_definitions. Both are provided
    // until the requirement is dropped upstream. Nothing reads the value yet,
    // and its URI form is not settled upstream either.
    //
    // @see https://www.drupal.org/i/3608162
    $contexts['page'] = new Context(new ContextDefinition('uri', new TranslatableMarkup('Page')), $this->currentPath->getPath());

    return $contexts;
  }

  /**
   * {@inheritdoc}
   *
   * A layout is seeded once, when it is created, and the starting points differ
   * on a single axis: how much of what already exists is kept. Only the theme
   * import keeps the page template of the front-end theme.
   *
   * Without a starting point this is an already seeded layout, so the stored
   * sources are the initial ones.
   *
   * @param \Drupal\display_builder_page_layout\StartingPointType|null $starting_point
   *   (Optional) The way the layout is seeded.
   *
   * @see \Drupal\display_builder_page_layout\Form\PageLayoutForm::buildStartingPointForm()
   */
  public function getInitialSources(?StartingPointType $starting_point = NULL): array {
    return match ($starting_point) {
      StartingPointType::Theme => [$this->dataConverter->convertPage()],
      StartingPointType::Minimal => $this->getMinimalSources(),
      StartingPointType::Blank => [],
      default => $this->getSources(),
    };
  }

  /**
   * Gets the page layout entity this plugin builds.
   *
   * Loaded on demand: the constructor runs before ::create() has injected the
   * entity type manager.
   *
   * @return \Drupal\display_builder_page_layout\PageLayoutInterface|null
   *   The page layout, or NULL if the configured one no longer exists.
   */
  protected function getEntity(): ?PageLayoutInterface {
    if ($this->entity === NULL) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface|null $entity */
      $entity = $this->entityTypeManager
        ->getStorage('page_layout')
        ->load($this->configuration['entity_id'] ?? '');
      $this->entity = $entity;
    }

    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    return $this->t('Initialize display from existing Page Layout configuration');
  }

  /**
   * Gets the sources of the minimal Drupal page.
   *
   * The theme page shell is deliberately left out: it is what makes the
   * starting points a gradient instead of two flavors of the same thing. The
   * cost is that a minimal page looks raw on a bare theme.
   *
   * @return array
   *   A list of sources, in page order.
   */
  private function getMinimalSources(): array {
    $sources = [
      $this->buildBlockSource('system_breadcrumb_block'),
      $this->buildBlockSource('system_messages_block'),
    ];

    if ($this->moduleHandler->moduleExists('help')) {
      $sources[] = $this->buildBlockSource('help_block');
    }

    $sources[] = ['source_id' => 'page_title', 'source' => []];
    $sources[] = $this->buildBlockSource('local_tasks_block', [
      'primary' => TRUE,
      'secondary' => TRUE,
    ]);
    $sources[] = $this->buildBlockSource('local_actions_block');
    $sources[] = ['source_id' => 'main_page_content', 'source' => []];

    return $sources;
  }

  /**
   * Builds a block source data structure.
   *
   * @param string $block_id
   *   The block plugin ID.
   * @param array $configuration
   *   (Optional) The block plugin configuration.
   *
   * @return array
   *   A single UI Patterns source data.
   */
  private function buildBlockSource(string $block_id, array $configuration = []): array {
    return [
      'source_id' => 'block',
      'source' => [
        'plugin_id' => $block_id,
        $block_id => $configuration,
      ],
    ];
  }

}
