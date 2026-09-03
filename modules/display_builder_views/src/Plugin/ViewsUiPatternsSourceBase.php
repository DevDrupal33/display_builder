<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\EmptyPlaceholderHelpInterface;
use Drupal\display_builder\RegionPlaceholderSourceTrait;
use Drupal\display_builder\SourceProcessingDataInterface;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;
use Drupal\views\Plugin\views\display\DisplayPluginInterface;
use Drupal\views\Plugin\views\ViewsPluginInterface;
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
 * Sources backed by a views plugin (the pager, the exposed form, the rows)
 * also expose that plugin's own options form in place, through
 * SourceProcessingDataInterface, instead of sending the user to the Views UI.
 *
 * @see \Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase
 */
abstract class ViewsUiPatternsSourceBase extends ViewsSourceBase implements EmptyPlaceholderHelpInterface, SourceProcessingDataInterface {

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
   *
   * The view ran and this area rendered nothing - not because the node needs
   * configuring, but because the view itself has nothing to put there right
   * now (no area text, no results on this page, a single page of results...).
   */
  public function emptyPlaceholderHelp(): string|TranslatableMarkup {
    return new TranslatableMarkup('Depends on the view: this area only shows when the view itself has something to put there.');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $type = $this->getViewsPluginType();

    if ($type !== NULL && $this->buildViewsPluginForm($form, $type, $form_state)) {
      return $form;
    }

    // Keep the parent's "label_map" label, it names the source. This one says
    // why there is nothing under it, and where to go instead.
    $url = $this->getViewsUiEditUrl();
    $form['notice'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $url === NULL
        ? $this->t('This element must be configured in the corresponding view.')
        : $this->t('This element must be configured in <a href=":url" target="_blank">the corresponding view</a>.', [':url' => $url->toString()]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateFormData(array $form, FormStateInterface $form_state): void {
    $key = $this->getOptionsKey();

    if ($key === NULL || !isset($form[$key]) || !$form_state->hasValue($key)) {
      return;
    }

    if ($this->isViewOpenInViewsUi()) {
      $form_state->setErrorByName($key, $this->t('This view has unsaved changes in the Views UI. Save or cancel them there before configuring it from the builder.'));

      return;
    }
    $type = $this->getViewsPluginType();
    $plugin = $type === NULL ? NULL : $this->getViewsPlugin($type);

    if ($plugin === NULL) {
      return;
    }
    $sub_form = $form[$key];
    $plugin->validateOptionsForm($sub_form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function processFormData(array $data, FormStateInterface $form_state): array {
    $type = $this->getViewsPluginType();

    if ($type === NULL) {
      return $data;
    }
    $this->submitViewsPluginForm($type, $form_state);
    // The views options belong to the view, not to the source tree.
    unset($data[$type . '_options']);

    return $data;
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
   * The views plugin type this source renders the output of.
   *
   * Sources computed from other view displays have none: they have nothing to
   * configure locally.
   *
   * @return string|null
   *   A views plugin type, as used by DisplayPluginBase::getPlugin(), or NULL.
   */
  protected function getViewsPluginType(): ?string {
    return NULL;
  }

  /**
   * The form key holding the options this source diverts to the view.
   *
   * @return string|null
   *   The key, or NULL when the source has nothing to divert.
   */
  protected function getOptionsKey(): ?string {
    $type = $this->getViewsPluginType();

    return $type === NULL ? NULL : $type . '_options';
  }

  /**
   * The Views UI edit URL of the display this source belongs to.
   *
   * @return \Drupal\Core\Url|null
   *   The URL, or NULL without views_ui or without access to it.
   */
  protected function getViewsUiEditUrl(): ?Url {
    $view_id = $this->getUnexecutedView()?->storage->id();
    $display_id = $this->getContextValue('display');

    if (!$this->moduleHandler->moduleExists('views_ui') || $view_id === NULL || !\is_string($display_id)) {
      return NULL;
    }
    $url = Url::fromRoute('entity.view.edit_display_form', [
      'view' => $view_id,
      'display_id' => $display_id,
    ]);

    return $url->access() ? $url : NULL;
  }

  /**
   * Is the view held by the Views UI with changes nobody confirmed yet?
   *
   * GetViewExecutable() prefers the views_ui shared tempstore copy over the
   * saved view, so saving from here would publish edits the user is still
   * working on in the other UI. The Views UI Save button stays the only way to
   * commit those, and the builder refuses until it has been pressed.
   *
   * @return bool
   *   TRUE when the views_ui tempstore holds this view.
   */
  protected function isViewOpenInViewsUi(): bool {
    $view_id = $this->getUnexecutedView()?->storage->id();

    if ($view_id === NULL) {
      return FALSE;
    }

    return $this->tempStore?->get((string) $view_id) !== NULL;
  }

  /**
   * Adds the options form of the backing views plugin to the source form.
   *
   * The elements go in their own "{type}_options" subtree, the same key the
   * plugin's own submit handler reads, so a plugin overriding
   * ViewsPluginInterface::submitOptionsForm() keeps working.
   *
   * @param array $form
   *   The source settings form.
   * @param string $type
   *   The views plugin type.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE when the plugin contributed a form, FALSE when there is nothing to
   *   configure.
   *
   * @see \Drupal\views\Plugin\views\display\DisplayPluginBase::buildOptionsForm()
   */
  protected function buildViewsPluginForm(array &$form, string $type, FormStateInterface $form_state): bool {
    $plugin = $this->getViewsPlugin($type);

    if ($plugin === NULL) {
      return FALSE;
    }
    $key = $type . '_options';
    $form[$key] = ['#tree' => TRUE];
    $plugin->buildOptionsForm($form[$key], $form_state);

    // A plugin can use options in general and still expose nothing for the
    // variant currently selected, "Display a specified number of items" being
    // the obvious pager.
    if (\count(Element::children($form[$key])) === 0) {
      unset($form[$key]);

      return FALSE;
    }

    return TRUE;
  }

  /**
   * Saves the submitted options back into the view.
   *
   * Goes through the display handler rather than writing config keys, so a
   * display inheriting the section from the default display updates the
   * default display instead of growing an override the view never reads.
   *
   * @param string $type
   *   The views plugin type.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\views\Plugin\views\display\DisplayPluginBase::submitOptionsForm()
   */
  protected function submitViewsPluginForm(string $type, FormStateInterface $form_state): void {
    $display = $this->getViewDisplay();
    $plugin = $this->getViewsPlugin($type);
    $key = $type . '_options';

    if ($display === NULL || $plugin === NULL || !$form_state->hasValue($key)) {
      return;
    }
    // The plugin's submit handler expects its own subtree, and core rebuilds
    // it the same way before submitting.
    $sub_form = [];
    $plugin->buildOptionsForm($sub_form, $form_state);
    $plugin->submitOptionsForm($sub_form, $form_state);

    $options = $display->getOption($type);
    $options['options'] = $form_state->getValue($key);
    $display->setOption($type, $options);
    $this->saveView();
  }

  /**
   * Gets a views plugin of the current display, when it has options to show.
   *
   * @param string $type
   *   The views plugin type.
   *
   * @return \Drupal\views\Plugin\views\ViewsPluginInterface|null
   *   The plugin, or NULL when unavailable or without options.
   */
  protected function getViewsPlugin(string $type): ?ViewsPluginInterface {
    $plugin = $this->getViewDisplay()?->getPlugin($type);

    if (!$plugin instanceof ViewsPluginInterface || !$plugin->usesOptions()) {
      return NULL;
    }

    return $plugin;
  }

  /**
   * Gets the display handler of the view display this source belongs to.
   *
   * @return \Drupal\views\Plugin\views\display\DisplayPluginInterface|null
   *   The display handler, or NULL when the view or the display is unknown.
   */
  protected function getViewDisplay(): ?DisplayPluginInterface {
    return $this->getUnexecutedView()?->getDisplay();
  }

  /**
   * Saves the view this source belongs to.
   *
   * Only reached once validateFormData() has established that the views_ui
   * tempstore does not hold this view.
   *
   * @see self::isViewOpenInViewsUi()
   */
  protected function saveView(): void {
    $this->getUnexecutedView()?->storage->save();
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
   * The view executable set on the right display, without executing it.
   *
   * Configuration reads and writes need the display handler, not the results.
   *
   * @return \Drupal\views\ViewExecutable|null
   *   The view executable, or NULL when the view or the display is unknown.
   */
  protected function getUnexecutedView(): ?ViewExecutable {
    $view = parent::getView();
    $display_id = $this->getContextValue('display');

    if ($view === NULL || $display_id === NULL) {
      return NULL;
    }
    $view->setDisplay($display_id);

    return $view;
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
    // Executing a display builds its attachments, and core binds the view
    // entity to each attachment clone it creates on the way
    // (ViewExecutable::attachDisplays(), through the ViewExecutable
    // constructor). The next source asking the entity for its executable
    // would get that clone, render its own output from it, and then see it
    // flipped back to the attachment display when the attachment renderable
    // pre-renders - an HTML list rendered with the unformatted style's
    // options, for instance. Reclaiming the binding right after the execute
    // keeps every source of this display on one executable.
    $view->storage->set('executable', $view);
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
