<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\DisplayVariant;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Display\Attribute\PageDisplayVariant;
use Drupal\Core\Display\PageVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A variant for pages managed by Display Builder Page Layout.
 */
#[PageDisplayVariant(
  id: 'display_builder',
  admin_label: new TranslatableMarkup('Display Builder')
)]
class DisplayBuilder extends VariantBase implements ContainerFactoryPluginInterface, PageVariantInterface {

  private const SOURCE_CONTENT_ID = 'main_page_content';

  private const SOURCE_TITLE_ID = 'page_title';

  /**
   * The render array representing the main content.
   *
   * @var array
   */
  protected $mainContent;

  /**
   * The page title: a string (plain title) or a render array (formatted title).
   *
   * @var string|array
   */
  protected $title = '';

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

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StateManagerInterface $state_manager,
    ComponentElementBuilder $component_element_builder,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stateManager = $state_manager;
    $this->componentElementBuilder = $component_element_builder;
    $this->entityTypeManager = $entity_type_manager;
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
        'display_builder' => [
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
