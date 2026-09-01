<?php

declare(strict_types=1);

namespace Drupal\display_builder_views_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\views\ViewExecutable;

/**
 * Counts how many times a view is really run.
 *
 * The only observable difference between executing a view once per render and
 * once per source is how often views runs. Nothing on the view itself says so
 * afterwards, so it is counted as it happens.
 *
 * @see \Drupal\Tests\display_builder_views\Kernel\ViewsSourceRenderTest
 */
class CountViewExecutions {

  public const STATE_KEY = 'display_builder_views_test.executions';

  public function __construct(
    protected StateInterface $state,
  ) {}

  /**
   * Implements hook_views_pre_execute().
   */
  #[Hook('views_pre_execute')]
  public function viewsPreExecute(ViewExecutable $view): void {
    $counts = $this->state->get(self::STATE_KEY, []);
    $key = $view->storage->id() . ':' . $view->current_display;
    $counts[$key] = ($counts[$key] ?? 0) + 1;
    $this->state->set(self::STATE_KEY, $counts);
  }

}
