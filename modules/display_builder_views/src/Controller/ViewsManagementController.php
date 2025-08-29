<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;

/**
 * Returns responses for Display Builder ui routes.
 */
class ViewsManagementController extends ControllerBase {

  public function __construct(
    private readonly StateManagerInterface $stateManager,
    private readonly DateFormatterInterface $dateFormatter,
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
        'profile_id' => ['data' => $this->t('Profile')],
        'updated' => ['data' => $this->t('Updated')],
        'log' => ['data' => $this->t('Last log')],
        'operations' => ['data' => $this->t('Operations')],
      ],
      '#empty' => $this->t('No Display builder enabled on any view.'),
    ];

    foreach (\array_keys($this->stateManager->loadAll()) as $builder_id) {
      if (!DisplayExtender::checkInstanceId($builder_id)) {
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
    $view_id = DisplayExtender::checkInstanceId($builder_id)['view'];
    $display_id = DisplayExtender::checkInstanceId($builder_id)['display'];
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

    $row['profile_id']['data'] = $view->getDisplay($display_id)['display_options']['display_extenders']['display_builder'][ConfigFormBuilderInterface::PROFILE_PROPERTY] ?? 'n/a';
    $row['updated']['data'] = $builder['present']['time'] ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $builder['present']['time']) : '-';

    if (isset($builder['present']['log'])) {
      $row['log']['data'] = $builder['present']['log'];
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
