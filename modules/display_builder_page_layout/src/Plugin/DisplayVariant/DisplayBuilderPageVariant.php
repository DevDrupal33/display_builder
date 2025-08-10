<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\DisplayVariant;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Registry;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A variant for pages managed by Display Builder Page Layout.
 */
#[PageDisplayVariant(
  id: 'display_builder',
  admin_label: new TranslatableMarkup('Display Builder page')
)]
class DisplayBuilderPageVariant extends VariantBase implements ContainerFactoryPluginInterface, PageVariantInterface {

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
   * The display builder state manager.
   */
  protected StateManagerInterface $stateManager;

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

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StateManagerInterface $state_manager,
    ComponentElementBuilder $component_element_builder,
    EntityTypeManagerInterface $entity_type_manager,
    Registry $theme_registry,
    ExtensionList $modules,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stateManager = $state_manager;
    $this->componentElementBuilder = $component_element_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->themeRegistry = $theme_registry;
    $this->modules = $modules;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('display_builder.state_manager'),
      $container->get('ui_patterns.component_element_builder'),
      $container->get('entity_type.manager'),
      $container->get('theme.registry'),
      $container->get('extension.list.module'),
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

    // We alter the registry here instead of implementing
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

    $sources = $page_layout->getSources();
    $instance_id = $page_layout->getInstanceId();
    $this->replaceTitleAndContent($sources, $this->title, $this->mainContent);

    $contexts = $this->stateManager->getContexts($instance_id) ?? [];
    $data = [];

    foreach ($sources as $source) {
      $build = $this->componentElementBuilder->buildSource($data, 'content', [], $source, $contexts);
      $data[] = $build['#slots']['content'][0] ?? [];
    }

    return [
      'content' => [
        'status_messages' => [
          '#type' => 'status_messages',
          '#weight' => -1000,
          '#include_fallback' => TRUE,
        ],
        'display_builder_content' => [
          'data' => $data,
          '#weight' => -800,
        ],
      ],
    ];
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
    $this->title = $title;

    return $this;
  }

  /**
   * Replace title and content blocks.
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
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => self::SOURCE_CONTENT_ID], $content);
    }

    // Try to handle specific title cases.
    if (\is_string($title)) {
      $title = $title;
    }
    elseif ($title instanceof MarkupInterface) {
      $title = (string) $title;
    }
    elseif (isset($title['#markup'])) {
      $title = $title['#markup'];
    }

    if ($title !== NULL && \is_string($title)) {
      // @todo avoid arbitrary classes.
      $title = ['#markup' => '<h1 class="title page-title">' . $title . '</h1>'];
      DisplayBuilderHelpers::findArrayReplaceSource($data, ['source_id' => self::SOURCE_TITLE_ID], $title);
    }
  }

}
