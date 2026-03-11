<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
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

    foreach (ViewDisplay::collectInstances() as $instance_id => $instance) {
      $build['display_builder_table']['#rows'][$instance_id] = $this->buildRow($instance);
    }
    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Builds a table row for a display builder related to a view display managed.
   *
   * @param \Drupal\display_builder\InstanceInterface|null $instance
   *   The display builder instance (or NULL).
   *
   * @return array
   *   A table row.
   */
  protected function buildRow(?InstanceInterface $instance): array {
    if (!$instance) {
      return [];
    }

    $instance_id = (string) $instance->id();
    $view_id = ViewDisplay::checkInstanceId($instance_id)['view'];
    $display_id = ViewDisplay::checkInstanceId($instance_id)['display'];
    $view = $this->entityTypeManager()->getStorage('view')->load($view_id);

    if (!$view) {
      return [];
    }

    /** @var \Drupal\display_builder\Plugin\Field\FieldType\HistoryStep $present; */
    $present = $instance->get('present')->first();

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
    $row['updated']['data'] = $present->getTime() ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $present->getTime()) : '-';

    if ($log = $present->getLog()) {
      $row['log']['data'] = $log;
    }
    else {
      $row['log']['data'] = '-';
    }
    $row['operations']['data']['operations'] = [
      '#type' => 'operations',
      '#links' => $this->getOperationLinks($instance_id),
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
