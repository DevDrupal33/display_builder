<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\DisplayVariant;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Registry;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A variant for pages managed by Display Builder Page Layout.
 */
#[PageDisplayVariant(
  id: 'display_builder_page_layout',
  admin_label: new TranslatableMarkup('Display Builder Page Layout')
)]
class PageLayoutPageVariant extends VariantBase implements ContainerFactoryPluginInterface, PageVariantInterface {

  private const SOURCE_CONTENT_ID = 'main_page_content';

  private const SOURCE_TITLE_ID = 'page_title';

  /**
   * The render array representing the main content.
   */
  protected array $mainContent;

  /**
   * The page title.
   *
   * Can be a string (plain title), Markup or a render array (formatted title).
   */
  protected array|MarkupInterface|string $title;

  /**
   * Component element builder.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The theme registry.
   */
  protected Registry $themeRegistry;

  /**
   * The list of modules.
   */
  protected ExtensionList $modules;

  /**
   * The display buildable plugin manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ComponentElementBuilder $component_element_builder,
    EntityTypeManagerInterface $entity_type_manager,
    Registry $theme_registry,
    ExtensionList $modules,
    DisplayBuildablePluginManager $display_buildable_manager,
    RequestStack $request_stack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->componentElementBuilder = $component_element_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->themeRegistry = $theme_registry;
    $this->modules = $modules;
    $this->displayBuildableManager = $display_buildable_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ui_patterns.component_element_builder'),
      $container->get('entity_type.manager'),
      $container->get('theme.registry'),
      $container->get('extension.list.module'),
      $container->get('plugin.manager.display_buildable'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    /** @var \Drupal\display_builder_page_layout\AccessControlHandler $access_control */
    $access_control = $this->entityTypeManager->getAccessControlHandler('page_layout');
    $page_layout = $access_control->loadCurrentPageLayout();

    if (!$page_layout) {
      // This is not supposed to happen. PageVariantSubscriber must not load
      // this plugin, and fallback to Block Layout if there is no suitable
      // Page Layout entities.
      // @todo raise an error
      return [];
    }

    // We alter the registry runtime here instead of implementing
    // hook_theme_registry_alter in order keep the alteration specific to each
    // page.
    $theme_registry = $this->themeRegistry->get();
    $template_uri = $this->modules->getPath('display_builder_page_layout') . '/templates';
    $runtime = $this->themeRegistry->getRuntime();
    $theme_registry['page']['path'] = $template_uri;
    $runtime->set('page', $theme_registry['page']);
    $theme_registry['region']['path'] = $template_uri;
    $runtime->set('region', $theme_registry['region']);

    // Also skip the related template suggestions.
    foreach (\array_keys($theme_registry) as $renderable_id) {
      if (\str_starts_with($renderable_id, 'page__') || \str_starts_with($renderable_id, 'region__')) {
        $runtime->delete($renderable_id);
      }
    }

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);
    $instance_id = $buildable->getInstanceId();

    // A preview sub-request for this layout renders its in-progress (unsaved)
    // builder state instead of the saved configuration.
    $preview_sources = $this->getPreviewSources($buildable);
    $sources = $preview_sources ?? $page_layout->getSources();
    $this->replaceTitleAndContent($sources, $this->title, $this->mainContent);

    $data = $contexts = [];
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($instance_id);

    if ($instance) {
      $contexts = $instance->getAvailableContexts();
    }

    foreach ($sources as $source) {
      $build = $this->componentElementBuilder->buildSource($data, 'content', [], $source, $contexts);
      $data[] = $build['#slots']['content'][0] ?? [];
    }

    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($this);
    // See also: PageLayout::postSave().
    // See also: PageVariantSubscriber::onSelectPageDisplayVariant()
    $cache->addCacheTags($page_layout->getCacheTags());

    if ($preview_sources !== NULL) {
      // Per-editor draft content: always current, never cached.
      $cache->setCacheMaxAge(0);
    }

    $build = [
      'display_builder_content' => [
        '#instance_id' => $instance_id,
        'data' => $data,
        '#weight' => -800,
      ],
    ];
    $cache->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content): self {
    $this->mainContent = $main_content;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title): self {
    $this->title = $title ?? '';

    return $this;
  }

  /**
   * Gets the unsaved draft sources when this render is a preview.
   *
   * The preview iframe loads an admin route, which sub-requests this layout's
   * pinned page with a request attribute naming an instance (@see
   * \Drupal\display_builder\DisplayBuildableInterface::PREVIEW_INSTANCE_ATTRIBUTE).
   * When that attribute matches this layout's instance, the in-progress builder
   * state is served instead of the saved configuration.
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   The page layout buildable for the current page.
   *
   * @return array|null
   *   The draft sources, or NULL when this is not a preview of this layout.
   */
  protected function getPreviewSources(DisplayBuildableInterface $buildable): ?array {
    $requested = $this->requestStack->getCurrentRequest()?->attributes->get(DisplayBuildableInterface::PREVIEW_INSTANCE_ATTRIBUTE);

    // Every front-end page rendered by a layout comes through here, so leave
    // before asking the buildable anything when this is not a preview at all.
    if ($requested === NULL || $requested !== $buildable->getInstanceId()) {
      return NULL;
    }
    $instance = $buildable->getInstance();

    // The attribute crosses a kernel boundary, so the draft is re-authorized
    // here rather than trusting whoever set it.
    return $instance?->access('view') ? $instance->getCurrentState() : NULL;
  }

  /**
   * Replace title and content blocks.
   *
   * Both payloads are wrapped in PageRegionSourceBase::PAGE_PROVIDED. The
   * source cannot tell "the page gave me nothing" from "nobody injected
   * anything" by looking at the payload, and the two want opposite renders,
   * so the wrapper is the signal and the payload is free to be empty.
   *
   * @param array $data
   *   The Display Builder data to alter.
   * @param mixed $title
   *   The title to set.
   * @param array|null $content
   *   The content to set.
   *
   * @todo replace multiple in one pass?
   */
  private function replaceTitleAndContent(array &$data, mixed $title, ?array $content): void {
    if ($content !== NULL) {
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => self::SOURCE_CONTENT_ID], [PageRegionSourceBase::PAGE_PROVIDED => $content]);
    }

    // Try to handle specific title cases.
    if ($title instanceof MarkupInterface) {
      $title = (string) $title;
    }
    elseif (\is_array($title) && isset($title['#markup'])) {
      $title = (string) $title['#markup'];
    }

    if (\is_string($title)) {
      // @todo avoid arbitrary classes.
      $title = ['#markup' => '<h1 class="title page-title">' . $title . '</h1>'];
    }

    // Unconditionally, including for a title none of the above turned into
    // markup: a title callback is free to return any render array, and the
    // wrapper is the only thing telling the source a page filled it. Leave it
    // off and the title node falls back to the builder's placeholder, which
    // on a real page is a fatal render error, not just the wrong words.
    DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => self::SOURCE_TITLE_ID], [PageRegionSourceBase::PAGE_PROVIDED => $title]);
  }

}
