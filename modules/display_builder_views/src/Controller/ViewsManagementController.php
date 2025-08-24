<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;

/**
 * Returns responses for Display Builder ui routes.
 */
class ViewsManagementController extends ControllerBase {

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
        'id' => ['data' => $this->t('View')],
        'display_builder_config' => ['data' => $this->t('Display builder')],
        'updated' => ['data' => $this->t('Updated')],
        'log' => ['data' => $this->t('Last log')],
        'operations' => ['data' => $this->t('Operations')],
      ],
      '#empty' => $this->t('No Display builder enabled on any view.'),
    ];

    foreach (\array_keys($this->stateManager->loadAll()) as $builder_id) {
      if (!\str_starts_with($builder_id, 'view__')) {
        continue;
      }
      $build['display_builder_table']['#rows'][$builder_id] = $this->buildRow($builder_id);
    }
    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Builds a table row for a display builder related to a view display managed.
   *
   * @param string $builder_id
   *   The builder id.
   *
   * @return array
   *   A table row.
   */
  protected function buildRow(string $builder_id): array {
    $view_id = \explode('__', $builder_id)[1];
    $display_id = \explode('__', $builder_id)[2];
    $view = $this->entityTypeManager()->getStorage('view')->load($view_id);

    if (!$view) {
      return [];
    }

    $builder = $this->stateManager->load($builder_id);
    if (!$builder) {
      return [];
    }

    $row = [];

    $row['id']['data'] = [
      '#type' => 'link',
      '#title' => $view->label() . ' (' . $display_id . ')',
      '#url' => Url::fromRoute('entity.view.edit_display_form', ['view' => $view_id, 'display_id' => $display_id]),
    ];
    $row['display_builder_config']['data'] = $builder['entity_config_id'];
    $row['updated']['data'] = $builder['present']['time'] ?? 0;

    if (isset($builder['present']['log']) && $builder['present']['log'] instanceof TranslatableMarkup) {
      $row['log']['data'] = $this->formatLog($builder['present']['log']);
    }
    else {
      $row['log']['data'] = '-';
    }
    $row['operations']['data']['operations'] = [
      '#type' => 'operations',
      '#links' => $this->getOperationLinks($builder_id),
    ];

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
   *
   * @return array
   *   The operation links.
   */
  private function getOperationLinks(string $builder_id): array {
    return [
      'manage' => [
        'title' => $this->t('Build display'),
        'url' => DisplayExtender::getUrlFromInstanceId($builder_id),
      ],
      'delete' => [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('display_builder_views.views.delete', [
          'builder_id' => $builder_id,
        ]),
      ],
    ];
  }

}
