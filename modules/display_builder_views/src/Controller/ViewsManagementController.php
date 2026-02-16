<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder_views\Plugin\display_builder\Buildable\ViewDisplay;

/**
 * Returns responses for Display Builder ui routes.
 */
class ViewsManagementController extends ControllerBase {

  public function __construct(
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

    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');

    foreach (\array_keys($storage->loadMultiple()) as $builder_id) {
      if (!ViewDisplay::checkInstanceId($builder_id)) {
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
    $view_id = ViewDisplay::checkInstanceId($builder_id)['view'];
    $display_id = ViewDisplay::checkInstanceId($builder_id)['display'];
    $view = $this->entityTypeManager()->getStorage('view')->load($view_id);

    if (!$view) {
      return [];
    }

    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $storage->load($builder_id);

    if (!$builder) {
      return [];
    }
    $builder = $builder->toArray();

    $row = [];

    $row['id']['data'] = [
      '#type' => 'link',
      '#title' => $view->label() . ' (' . $display_id . ')',
      '#url' => Url::fromRoute('entity.view.edit_display_form', ['view' => $view_id, 'display_id' => $display_id]),
    ];

    $row['profile_id'] = [
      'data-profile-id' => \sprintf('profile_%s', $view_id),
      'data' => $view->getDisplay($display_id)['display_options']['display_extenders']['display_builder'][DisplayBuildableInterface::PROFILE_PROPERTY] ?? '?',
    ];
    $row['updated']['data'] = $builder['present']->time ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $builder['present']->time) : '-';

    if (isset($builder['present']->log)) {
      $row['log']['data'] = $builder['present']->log;
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
        'url' => ViewDisplay::getUrlFromInstanceId($builder_id),
        'attributes' => [
          'data-link-builder' => $builder_id,
        ],
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
