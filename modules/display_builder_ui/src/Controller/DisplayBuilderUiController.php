<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_dev_tools\MockEntity;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_ui\Form\DisplayBuilderUiFilterForm;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for Display Builder ui routes.
 */
class DisplayBuilderUiController extends ControllerBase {

  /**
   * Number of items to display per page.
   */
  private const ITEMS_PER_PAGE = 20;

  /**
   * Mapping of context classes to human-readable labels.
   *
   * @var array<string, array{class-string, string}>
   *
   * @todo to have from a hook_info in each module.
   */
  protected array $contextClasses = [
    'views' => [DisplayExtender::class, 'Views'],
    'page_layout' => [PageLayout::class, 'Page layout'],
    'entity_view' => [EntityViewDisplay::class, 'Entity view'],
    'entity_view_override' => [DisplayBuilderItemList::class, 'Entity view override'],
  ];

  public function __construct(
    private readonly DateFormatterInterface $dateFormatter,
    private readonly PagerManagerInterface $pagerManager,
    private readonly PagerParametersInterface $pagerParameters,
  ) {}

  /**
   * List all Display Builder instances.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   A render array.
   */
  public function instances(Request $request): array {
    $filter_type = $request->query->get('display') ?? '';

    $build = [];
    $build['filter_form'] = $this->formBuilder()->getForm(DisplayBuilderUiFilterForm::class);
    $build['filter_form']['#attributes'] = ['class' => ['container-inline']];

    $instances = $this->entityTypeManager()->getStorage('display_builder_instance')->loadMultiple();
    $filtered = [];

    foreach ($instances as $instance_id => $instance) {
      /** @var \Drupal\display_builder\InstanceInterface $instance */
      $instance = $instance;
      $type = $this->getBuilderType($instance);

      if ($filter_type === '' || $type === $filter_type) {
        $filtered[$instance_id] = $instance;
      }
    }

    $limit = self::ITEMS_PER_PAGE;
    $total = \count($filtered);

    // Get current page from pager parameters.
    $current_page = $this->pagerParameters->findPage();
    $offset = $current_page * $limit;

    // Initialize the pager.
    $this->pagerManager->createPager($total, $limit);

    $paged = \array_slice($filtered, $offset, $limit);

    $build['notice'] = [
      '#markup' => $this->t('List of all Display builder instances.<br>An instance is a saved arrangement of components and styles for a specific display context (a view mode, a page layout or a view).<br>Instances are created directly from display pages like Entity view, Page layout or Views and should be managed directly from each display context.'),
      '#prefix' => '<div class="description">',
      '#suffix' => '</div>',
      '#weight' => -100,
    ];

    $build['table'] = [
      '#theme' => 'table',
      '#header' => [
        'id' => ['data' => $this->t('Instance')],
        'display' => ['data' => $this->t('Display')],
        'profile' => ['data' => $this->t('Profile')],
        'updated' => ['data' => $this->t('Updated')],
        'log' => ['data' => $this->t('Last log')],
        'operations' => ['data' => $this->t('Operations')],
      ],
      '#rows' => [],
    ];

    foreach ($paged as $instance_id => $instance) {
      $build['table']['#rows'][$instance_id] = $this->buildRow($instance);
    }

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Builds a table row for a display builder.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   Display builder instance.
   *
   * @return array
   *   A table row.
   */
  protected function buildRow(InstanceInterface $instance): array {
    $instance_id = (string) $instance->id();
    $data = $instance->toArray();
    $row = $links = [];
    $url = $type_attached = $current_class = NULL;

    foreach ($this->contextClasses as [$class, $label]) {
      if (\class_exists($class) && $instance->hasSaveContextsRequirement($class::getContextRequirement())) {
        $current_class = $class;
        $url = $class::getUrlFromInstanceId($instance_id);
        $type_attached = new TranslatableMarkup($label); // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString

        break;
      }
    }

    // If no context class matched, check if it's a mock entity (from
    // display_builder_dev_tools).
    if (!$url && \class_exists(MockEntity::class)) {
      $current_class = MockEntity::class;
      $url = MockEntity::getUrlFromInstanceId($instance_id);
      $type_attached = $this->t('Other');
    }

    if (!$url) {
      $row['id']['data'] = $instance_id;
      $type_attached = $this->t('Other');
    }
    else {
      $row['id']['data'] = [
        '#type' => 'link',
        '#title' => $instance_id,
        '#url' => $url,
      ];
      $links = [
        'view' => [
          'title' => $this->t('View'),
          'url' => $url,
        ],
      ];
    }

    $row['profile']['data'] = $instance->getProfile()->id();
    $row['display']['data'] = $type_attached;

    $present = $data['present'] ?? [];

    if (!$present) {
      $present = ['time' => NULL, 'log' => NULL];
    }
    $row['updated']['data'] = $present['time'] ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $present['time']) : '-';

    if (isset($present['log'])) {
      $row['log']['data'] = $present['log'];
    }
    else {
      $row['log']['data'] = '-';
    }

    $context = [
      'instance_id' => $instance_id,
      'class' => $current_class,
    ];

    $this->moduleHandler()->alter('display_builder_ui_operations_links', $links, $context);

    if (\count($links) > 0) {
      $row['operations']['data']['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    return ['data' => $row];
  }

  /**
   * Helper to get builder type string for filtering.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   Display builder instance.
   *
   * @return string
   *   The type string.
   */
  protected function getBuilderType(InstanceInterface $instance): string {
    foreach ($this->contextClasses as $type => [$class, $label]) {
      if (\class_exists($class) && $instance->hasSaveContextsRequirement($class::getContextRequirement())) {
        return $type;
      }
    }

    return 'other';
  }

}
