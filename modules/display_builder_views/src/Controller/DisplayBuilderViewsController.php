<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Returns responses for Display Builder ui routes.
 */
class DisplayBuilderViewsController extends ControllerBase {

  public function __construct(
    private readonly StateManagerInterface $stateManager,
  ) {}

  /**
   * Generate a simple index of saved display builder.
   *
   * @return array
   *   A render array.
   */
  public function pageViewsIndex(): array {
    $build = [];
    $build['display_builder_table'] = [
      '#theme' => 'table',
      '#header' => [
        'id' => ['data' => $this->t('Id')],
        'display_builder_config' => ['data' => $this->t('Default display config')],
        'updated' => ['data' => $this->t('Updated')],
        'log' => ['data' => $this->t('Last log')],
      ],
    ];

    $views_related = NULL;

    try {
      $views = $this->entityTypeManager()->getStorage('view');
      $build['display_builder_table']['#header']['used'] = ['data' => $this->t('Used in view')];

      // First load views with a display builder enabled.
      /** @var \Drupal\views\Entity\View $view */
      foreach ($views->loadMultiple() as $view) {
        foreach ($view->get('display') as $display_id => $display) {
          if (!isset($display['display_options']['display_extenders']['display_builder']['display_builder_id'])) {
            continue;
          }

          $display_builder_id = $display['display_options']['display_extenders']['display_builder']['display_builder_id'];

          if (!$display_builder_id) {
            continue;
          }

          $id = $view->id();
          $url = Url::fromRoute('entity.view.edit_display_form', ['view' => $id, 'display_id' => $display_id]);

          $views_related[$display_builder_id] = [
            'label' => $view->label(),
            'id' => $id,
            'url' => $url,
          ];
        }
      }
    }
    catch (\Throwable $th) {
    }

    $build['display_builder_table']['#header']['operations'] = ['data' => $this->t('Operations')];

    foreach (array_keys($this->stateManager->loadAll()) as $builder_id) {
      if (!isset($views_related[$builder_id])) {
        continue;
      }
      $build['display_builder_table']['#rows'][$builder_id] = $this->buildRow($builder_id, $this->stateManager->load((string) $builder_id), $views_related);
    }

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Load the main display builder for page layout.
   *
   * @param string $builder_id
   *   The builder instance id.
   *
   * @return array
   *   The display builder editable.
   */
  public function manageViews(string $builder_id): array {
    // Disable cache page.
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore

    if (empty($builder_id)) {
      throw new \LogicException('Missing builder id in the path.');
    }

    // @todo handle if this view display has been deleted.
    if ($this->stateManager->load($builder_id) === NULL) {
      throw new \LogicException('Missing builder data associated, see view display configuration.');
    }

    $builder_config_id = $this->stateManager->getEntityConfigId($builder_id);

    $storage = $this->entityTypeManager()->getStorage('display_builder');
    /** @var \Drupal\display_builder\DisplayBuilderInterface $displayBuilderConfig */
    $displayBuilderConfig = $storage->load($builder_config_id);

    $contexts = $this->stateManager->getContexts($builder_id) ?? [];

    return $displayBuilderConfig->build($builder_id, $contexts);
  }

  /**
   * Provides a generic title callback for a display used in pages.
   *
   * @param string $builder_id
   *   The uniq display id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The title for the display page, if found.
   */
  public function title(string $builder_id): ?TranslatableMarkup {
    if (empty($builder_id)) {
      return $this->t('Display builder');
    }

    return $this->t('Display builder: @id', ['@id' => $builder_id]);
  }

  /**
   * Builds a table row for a display builder related to a view display managed.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $builder
   *   An builder to display.
   * @param array $views_related
   *   Array of page ids and builder type indexed by builder id.
   *
   * @return array
   *   A table row.
   */
  protected function buildRow(string $builder_id, array $builder, array $views_related): array {
    $row = [];

    $row['id']['data'] = [
      '#type' => 'link',
      '#title' => $builder_id,
      '#url' => Url::fromRoute('display_builder_views.views.manage', ['builder_id' => $builder_id]),
    ];
    $row['display_builder_config']['data'] = $builder['entity_config_id'];
    $row['updated']['data'] = $builder['present']['time'] ?? 0;
    if (isset($builder['present']['log']) && $builder['present']['log'] instanceof TranslatableMarkup) {
      $row['log']['data'] = $this->formatLog($builder['present']['log']);
    }
    else {
      $row['log']['data'] = '-';
    }

    $label = $this->t('Not used');

    if (isset($views_related[$builder_id]['label'])) {
      $label = [
        '#type' => 'link',
        '#title' => \sprintf('%s (%s)', $views_related[$builder_id]['label'], $views_related[$builder_id]['id']),
        '#url' => $views_related[$builder_id]['url'],
      ];
    }

    $row['used']['data'] = $label;

    $links = $this->getOperationLinks($builder_id, isset($views_related[$builder_id]) ? FALSE : TRUE);

    if (\count($links) > 0) {
      $row['operations']['data']['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    return ['data' => $row];
  }

  /**
   * Format the log.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $log
   *   The log to format.
   *
   * @return array
   *   The formatted log.
   */
  private function formatLog(TranslatableMarkup $log): array {
    return ['#markup' => Markup::create($log->render())];
  }

  /**
   * Delete a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param bool $can_delete
   *   This display builder can be deleted (not attached to a view display).
   *
   * @return array
   *   The operation links
   */
  private function getOperationLinks(string $builder_id, bool $can_delete = FALSE): array {
    $build = [
      'manage' => [
        'title' => $this->t('Manage'),
        'url' => Url::fromRoute('display_builder_views.views.manage', [
          'builder_id' => $builder_id,
        ]),
      ],
    ];

    if ($can_delete) {
      $build['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('display_builder_views.views.delete', [
          'builder_id' => $builder_id,
        ]),
      ];
    }

    return $build;
  }

}
