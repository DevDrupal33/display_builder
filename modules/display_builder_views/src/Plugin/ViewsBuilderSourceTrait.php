<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\RegionPlaceholderSourceTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\views\Plugin\views\display\DisplayPluginInterface;
use Drupal\views\Plugin\views\ViewsPluginInterface;
use Drupal\views\ViewExecutable;

/**
 * What a builder adds to the view display sources of ui_patterns_views.
 *
 * The sources render the parts of an executed view. In a builder there may
 * be no view to get, and a node that renders as nothing is the one node the
 * user cannot select, move or delete: it gets a placeholder instead. Sources
 * backed by a views plugin (the pager, the exposed form, the rows) also
 * expose that plugin's own options form in place, through
 * SourceProcessingDataInterface, instead of sending the user to the Views UI.
 *
 * Used by subclasses of the ui_patterns_views sources, swapped in by
 * DisplayBuilderViewsHook::sourceInfoAlter().
 *
 * @see \Drupal\display_builder_page_layout\Plugin\PageRegionSourceBase
 */
trait ViewsBuilderSourceTrait {

  use RegionPlaceholderSourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    if ($this->getView() !== NULL) {
      return parent::getPropValue();
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
   *
   * The views plugin options and the notice only make sense in a builder: the
   * same sources are configured in the Views UI too, where the view itself is
   * at hand.
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    if (!$this->inBuilder()) {
      return $form;
    }
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
   * Whether the source is configured in a builder.
   *
   * @return bool
   *   TRUE when the contexts carry the 'display_builder' requirement, set by
   *   ViewDisplay::getRuntimeContexts().
   */
  protected function inBuilder(): bool {
    $requirements = $this->context['context_requirements'] ?? NULL;

    return $requirements instanceof RequirementsContext && $requirements->hasValue('display_builder');
  }

  /**
   * {@inheritdoc}
   *
   * On its own page the view arrives here already built by
   * ViewPageController, arguments included. A builder runs the view itself,
   * on a route that is not the view's own page route, so any contextual
   * filter this display declares gets nothing unless seeded from the current
   * route by hand first.
   */
  protected function executedView(): ?ViewExecutable {
    $view = $this->getView();

    if ($view !== NULL && empty($view->args)) {
      $view->setArguments($this->routeArguments());
    }

    return parent::executedView();
  }

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
    $view_id = $this->getView()?->id();
    $display_id = isset($this->context['ui_patterns_views:display']) ? $this->getContextValue('ui_patterns_views:display') : NULL;

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
   * The sources prefer the views_ui shared tempstore copy over the saved
   * view, so saving from here would publish edits the user is still working
   * on in the other UI. The Views UI Save button stays the only way to commit
   * those, and the builder refuses until it has been pressed.
   *
   * @return bool
   *   TRUE when the views_ui tempstore holds this view.
   */
  protected function isViewOpenInViewsUi(): bool {
    $view_id = $this->getView()?->id();

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
   * Configuration reads and writes need the display handler, not the results:
   * the view is not executed for them.
   *
   * @return \Drupal\views\Plugin\views\display\DisplayPluginInterface|null
   *   The display handler, or NULL when the view or the display is unknown.
   */
  protected function getViewDisplay(): ?DisplayPluginInterface {
    return $this->getView()?->getDisplay();
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
    $this->getView()?->storage->save();
  }

  /**
   * The view arguments the current route carries, in order.
   *
   * A views page route names its arguments in a map it carries itself, so
   * the route parameters that are arguments are exactly the ones it lists.
   * Every other parameter belongs to the route, not to the view.
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
