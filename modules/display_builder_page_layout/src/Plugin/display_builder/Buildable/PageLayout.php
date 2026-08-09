<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder_page_layout\BuilderDataConverter;
use Drupal\display_builder_page_layout\Entity\PageLayout as PageLayoutEntity;
use Drupal\display_builder_page_layout\PageLayoutInterface;
use Drupal\display_builder_page_layout\StartingPointType;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'page_layout',
  label: new TranslatableMarkup('Page layout'),
  instance_prefix: 'page_layout__',
  // A page layout is the page: its own sources draw the header and the footer.
  renders_full_page: TRUE,
)]
final class PageLayout extends DisplayBuildablePluginBase {

  /**
   * The page layout entity storing the display.
   */
  public ?PageLayoutInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (isset($configuration['entity'])) {
      $this->entity = $configuration['entity'];
      // Configuration to store in the Instance entity.
      unset($this->configuration['entity']);
      $this->configuration['entity_id'] = $this->entity->id();

      return;
    }

    // Configuration stored in Instance entity:
    // - entity_id (string): Page layout entity ID.
    // No dependency injection in plugin constructors.
    $this->entity = PageLayoutEntity::load($configuration['entity_id']);
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
    return Url::fromRoute('entity.page_layout.display_builder', ['page_layout' => $this->entity->id()]);
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
    return $this->entity?->getProfile();
  }

  /**
   * {@inheritdoc}
   *
   * A page layout has no canonical URL - it is applied to pages by conditions.
   * Only when a single, concrete request_path pins it to one page is there an
   * unambiguous page to preview it on.
   */
  public function getPreviewPagePath(): ?string {
    $conditions = $this->entity->getConditions();

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
   */
  public function getSources(): array {
    // We reload because the Drupal cache is very strong on this data.
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = $this->entityTypeManager->getStorage('page_layout')->load($this->entity->id());
    $this->entity = $entity;

    return $this->entity->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $this->entity->setSources($this->getInstance()->getCurrentState());
    $this->entity->save();
    // Clearing plugin cache seems enough to get the new layout.
    // @todo It looks very costly. Check if it is still needed.
    $this->pluginCacheClearer()->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function collectInstances(): array {
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $displayBuildableManager = \Drupal::service('plugin.manager.display_buildable');

    $instances = [];
    $entities = $entityTypeManager->getStorage('page_layout')->loadMultiple();

    foreach ($entities as $page_layout) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);
      $buildable->initInstanceIfMissing();
      $instance_id = $buildable->getInstanceId();
      $instances[$instance_id] = $buildable->getInstance();
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->entity->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', self::getPrefix(), $this->entity->id());
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
    $contexts['page'] = new Context(new ContextDefinition('uri', new TranslatableMarkup('Page')), $this->currentPath()->getPath());

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
      StartingPointType::Theme => [$this->converter()->convertPage()],
      StartingPointType::Minimal => $this->getMinimalSources(),
      StartingPointType::Blank => [],
      default => $this->getSources(),
    };
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

  /**
   * Gets the builder data converter.
   *
   * @return \Drupal\display_builder_page_layout\BuilderDataConverter
   *   The builder data converter.
   */
  private function converter(): BuilderDataConverter {
    return \Drupal::service('display_builder_page_layout.builder_data_converter');
  }

  /**
   * Gets the current path stack.
   *
   * @return \Drupal\Core\Path\CurrentPathStack
   *   The current path stack.
   */
  private function currentPath(): CurrentPathStack {
    return \Drupal::service('path.current');
  }

  /**
   * Gets the plugin cache clearer.
   *
   * @return \Drupal\Core\Plugin\CachedDiscoveryClearerInterface
   *   The plugin cache clearer.
   */
  private function pluginCacheClearer(): CachedDiscoveryClearerInterface {
    return \Drupal::service('plugin.cache_clearer');
  }

}
