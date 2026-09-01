<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\RegionPlaceholderSourceTrait;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;
use Drupal\views\ViewExecutable;

/**
 * Base class for the areas a view display fills when it runs.
 *
 * Each subclass pulls one area out of an executed view and nothing else. The
 * two things they all share are here: getting a view that is executed exactly
 * once per request, and what to show when there is no view to get - in a
 * builder there is no page, so the header, the rows and the pager have nothing
 * behind them, and a node that renders as nothing is the one node the user
 * cannot select, move or delete.
 *
 * @see \Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase
 */
abstract class ViewsUiPatternsSourceBase extends ViewsSourceBase {

  use RegionPlaceholderSourceTrait;

  /**
   * Views already executed in this request, keyed by view and display id.
   *
   * Request-scoped by construction: nothing here survives the process serving
   * one request. It exists because ::getView() can hand back a *different*
   * executable on every call - while a view is open in Views UI its unsaved
   * copy comes out of a shared tempstore, deserialized afresh each time - and
   * a display carrying ten of these sources would then build and run the same
   * view ten times over.
   *
   * @var \Drupal\views\ViewExecutable[]
   */
  protected static array $executed = [];

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();

    if ($view !== NULL) {
      return $this->renderFromView($view);
    }

    return $this->buildRegionPlaceholder(
      $this->label(),
      new TranslatableMarkup('This placeholder will be replaced by the page value.'),
      $this->regionSize()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['label_map'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This element must be configured in the corresponding view.'),
    ];

    return $form;
  }

  /**
   * Pulls this source's area out of an executed view.
   *
   * Only ever called with a view that ran, so an implementation is one line
   * and needs no guard of its own.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The executed view.
   *
   * @return mixed
   *   The area, as the prop type expects it.
   */
  abstract protected function renderFromView(ViewExecutable $view): mixed;

  /**
   * The room the area gets when it has to fall back to a placeholder.
   *
   * @return string
   *   A CSS class suffix: 'md' for a strip, 'lg' for a page's whole content
   *   area.
   *
   * @see \Drupal\display_builder\RenderableBuilderTrait::buildPlaceholderRegion()
   */
  protected function regionSize(): string {
    return 'md';
  }

  /**
   * {@inheritdoc}
   *
   * We override UI Patterns method because UI Patterns is not managing the
   * display (string) context yet.
   *
   * @todo Remove once #3608162 (or one of its follow-up) is done.
   */
  protected function getView(): ?ViewExecutable {
    // Asking for a context that is not there throws, and not being there is
    // the normal case: a builder has no view display behind it.
    $display_id = isset($this->context['display']) ? $this->getContextValue('display') : NULL;

    if ($display_id === NULL) {
      // ui_patterns_views' own component style and row plugins reach these
      // classes too, because the alter swaps the class site-wide. They hand
      // over the view that is already running and never name a display, so
      // that is a real view to render, not a builder with nothing behind it.
      return isset($this->context['ui_patterns_views:view']) ? parent::getView() : NULL;
    }

    return $this->executedView((string) $display_id);
  }

  /**
   * Runs one display of the view in context, at most once per request.
   *
   * @param string $display_id
   *   The display to run.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The executed view, or NULL when there is none in context.
   */
  private function executedView(string $display_id): ?ViewExecutable {
    // Answer from the memo before the parent is asked, not after: while a
    // view is open in Views UI the parent reads it back out of a shared
    // tempstore, which is a database read and a full unserialize *per call*.
    // The view entity in context names the same view without paying for it.
    $view_id = isset($this->context['ui_patterns_views:view_entity'])
      ? (string) $this->getContextValue('ui_patterns_views:view_entity')->id()
      : NULL;

    if ($view_id !== NULL && isset(self::$executed[$view_id . ':' . $display_id])) {
      return self::$executed[$view_id . ':' . $display_id];
    }
    $view = parent::getView();

    if ($view === NULL) {
      return NULL;
    }
    $key = $view->storage->id() . ':' . $display_id;

    if (isset(self::$executed[$key])) {
      return self::$executed[$key];
    }
    $view->setDisplay($display_id);

    // Only when the view has none: on its own page the view arrived here
    // already built by ViewPageController, arguments included, and replacing
    // them would throw away the ones the route really carried.
    if (empty($view->args)) {
      $view->setArguments($this->routeArguments());
    }
    $view->execute($display_id);
    self::$executed[$key] = $view;

    return $view;
  }

  /**
   * The view arguments the current route carries, in order.
   *
   * A views page route names its arguments in a map it carries itself, so the
   * route parameters that are arguments are exactly the ones it lists. Every
   * other parameter belongs to the route, not to the view.
   *
   * @return array
   *   The arguments, in the order the display declares them.
   *
   * @see \Drupal\views\Routing\ViewPageController::handle()
   */
  private function routeArguments(): array {
    $map = $this->routeMatch->getRouteObject()?->getOption('_view_argument_map') ?? [];
    $arguments = [];

    foreach ($map as $parameter_name) {
      $argument = $this->routeMatch->getRawParameter($parameter_name)
        ?? $this->routeMatch->getParameter($parameter_name);

      if ($argument !== NULL) {
        $arguments[] = $argument;
      }
    }

    return $arguments;
  }

}
