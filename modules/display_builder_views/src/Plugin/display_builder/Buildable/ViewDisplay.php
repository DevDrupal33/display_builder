<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\DisplayReference;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\views\Plugin\views\display\DisplayRouterInterface;
use Drupal\views\Plugin\views\PluginBase;
use Drupal\views\ViewExecutable;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'view_display',
  label: new TranslatableMarkup('Views'),
  instance_prefix: 'views__',
)]
final class ViewDisplay extends DisplayBuildablePluginBase {

  /**
   * View display extender plugin, once passed in or loaded by ::getExtender().
   */
  protected ?PluginBase $extender = NULL;

  /**
   * {@inheritdoc}
   *
   * Configuration, as stored in the Instance entity:
   * - view_id (string)
   * - view_display (string)
   *
   * It is better to take the extender plugin from configuration, as
   * 'extender', when available: it manipulates the view "executable" from the
   * tempstore, so we have access to the state not yet saved in config.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset($configuration['extender'])) {
      return;
    }
    $this->extender = $configuration['extender'];
    unset($this->configuration['extender']);
    $this->configuration['view_id'] = $this->extender->view->id();
    $this->configuration['view_display'] = $this->extender->view->current_display;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(): ?string {
    // Through ::getExtender(), not the property: the plugin only carries an
    // extender when one was passed in, and a caller holding nothing but a view
    // id and a display id is the normal case, not a broken one.
    $extender = $this->getExtender();

    // NULL when the view cannot be loaded, or when the display extender is not
    // registered in views settings.
    if ($extender === NULL) {
      return NULL;
    }
    $view = $extender->view;
    $display_id = (string) $view->current_display;

    return $this->composeDisplayLabel(
      (string) $view->storage->label(),
      (string) ($view->storage->getDisplay($display_id)['display_title'] ?? $display_id),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $view = $this->getExtender()->view;
    $params = [
      'view' => $view->id(),
      'display' => $view->current_display,
    ];

    return Url::fromRoute('display_builder_views.views.manage', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::getPrefix())) {
      return NULL;
    }
    [, $view, $display] = \explode('__', $instance_id);

    return [
      'view' => $view,
      'display' => $display,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    return $account->hasPermission('administer views') ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      // Fallback to the list of instances.
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('display_builder_views.views.manage', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      // Fallback to the list of instances.
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('entity.view.edit_form', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $display_builder_id = $this->getExtender()->options[DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL;

    if ($display_builder_id === NULL && $this->getInstance()) {
      return $this->getInstance()->getProfile();
    }

    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');

    /** @var \Drupal\display_builder\Entity\ProfileInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->getExtender()->options[DisplayBuildableInterface::SOURCES_PROPERTY] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $sources = $this->getInstance()->getCurrentState();
    $extender = $this->getExtender();
    // First, we save in the "live" object.
    $extender->options[DisplayBuildableInterface::SOURCES_PROPERTY] = $sources;
    // Then, we save in the permanent storage.
    $storage = $extender->view->storage;
    $displays = $storage->get('display');
    $display_id = $extender->view->current_display;
    // It is risky to alter a View like that. We need to be careful to not
    // break the storage integrity, but we didn't find a better way.
    $displays[$display_id]['display_options']['display_extenders']['display_builder'][DisplayBuildableInterface::SOURCES_PROPERTY] = $sources;
    $storage->set('display', $displays);
    $storage->save();
    // @todo Test if we still need to invalidate the cache manually here.
    $storage->invalidateCaches();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): string {
    $view = $this->getExtender()->view;

    return \sprintf('%s%s__%s', self::getPrefix(), $view->id(), $view->current_display);
  }

  /**
   * {@inheritdoc}
   */
  public function collectInstances(): array {
    $storage = $this->entityTypeManager->getStorage('view');
    $instances = [];

    foreach ($storage->loadMultiple() as $view) {
      // @phpstan-ignore-next-line
      foreach ($view->display as $display_id => $display) {
        $profile = $display['display_options']['display_extenders']['display_builder'][DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL;

        if (!$profile) {
          continue;
        }
        $instance_id = \sprintf('%s%s__%s', self::getPrefix(), $view->id(), $display_id);
        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('view_display',
          [
            'view_id' => $view->id(),
            'view_display' => $display_id,
          ]
        );
        $buildable->initInstanceIfMissing();
        $instance_id = $buildable->getInstanceId();
        $instances[$instance_id] = $buildable->getInstance();
      }
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   *
   * Every display of every view, not only the ones already carrying a profile.
   * A view display that is not built with Display Builder is still a level a
   * page can be assembled from, and hiding it is what leaves users concluding
   * it does not exist.
   */
  public function collectDisplays(array $options = []): array {
    $references = [];

    /** @var \Drupal\views\ViewEntityInterface $view */
    foreach ($this->entityTypeManager->getStorage('view')->loadMultiple() as $view) {
      if (!$view->status()) {
        continue;
      }
      $view_id = (string) $view->id();
      $executable = $view->getExecutable();

      foreach (\array_keys($view->get('display') ?? []) as $display_id) {
        $display_id = (string) $display_id;

        if (!$this->isDisplayBuildable($executable, $display_id)) {
          continue;
        }

        $extender = $view->getDisplay($display_id)['display_options']['display_extenders']['display_builder'] ?? [];
        $built = !empty($extender[DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL);

        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('view_display', [
          'view_id' => $view_id,
          'view_display' => $display_id,
        ]);
        // A NULL name means the display extender is not registered, and
        // ::getInstanceId() reads straight through it, so stop here.
        $label = $buildable->getDisplayLabel();

        if ($label === NULL) {
          continue;
        }
        $instance_id = $buildable->getInstanceId();
        $settings_url = self::viewEditUrl($view_id, $display_id);

        $references[] = new DisplayReference(
          instanceId: $instance_id,
          kind: $buildable->label(),
          label: $label,
          url: $built ? $buildable->getBuilderUrl() : $settings_url,
          built: $built,
          empty: $built && empty($extender[DisplayBuildableInterface::SOURCES_PROPERTY] ?? []),
          settingsUrl: $settings_url,
        );
      }
    }

    return $references;
  }

  /**
   * {@inheritdoc}
   *
   * A page display registers its own route - visited for real, it appears
   * inside a page. A block, attachment or embed display never does; it only
   * ever renders embedded in something else, so its preview must not be
   * wrapped in chrome either.
   */
  public function previewWithChrome(): bool {
    $extender = $this->getExtender();

    if ($extender === NULL) {
      return TRUE;
    }

    return $extender->view->getDisplay() instanceof DisplayRouterInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    // Contexts needed by UI Patterns Source can be added by each Display
    // Buildable plugin by overriding this method.
    // @todo filter by $unqualified_context_ids.
    $contexts = [];
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($this->getExtender()->view->storage);
    // Needed by ui_patterns_views's ViewRowsSource.
    // Will be filled by \Drupal\display_builder_views\Hook\PreprocessViewsView.
    $contexts['ui_patterns_views:rows'] = new Context(new ContextDefinition('any'), []);
    return RequirementsContext::addToContext(['views:style'], $contexts);
  }

  /**
   * Gets the display extender plugin this plugin builds.
   *
   * Loaded on demand: the constructor runs before ::create() has injected the
   * entity type manager. The fallback loads a "real" View entity, as stored in
   * config, so it may miss unsaved parameters.
   *
   * @return \Drupal\views\Plugin\views\PluginBase|null
   *   The extender, or NULL if the configured view no longer exists.
   */
  protected function getExtender(): ?PluginBase {
    if ($this->extender !== NULL) {
      return $this->extender;
    }
    /** @var \Drupal\views\ViewEntityInterface|null $view_entity */
    $view_entity = $this->entityTypeManager
      ->getStorage('view')
      ->load($this->configuration['view_id'] ?? '');
    $view = $view_entity?->getExecutable();

    if ($view) {
      $view->setDisplay($this->configuration['view_display'] ?? '');
      $this->extender = $view->getDisplay()->getExtenders()['display_builder'] ?? NULL;
    }

    return $this->extender;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    if ($this->initialDataSource === 'fixture') {
      return $this->t('Initialize display from default configuration');
    }

    return $this->t('Initialize display from existing View configuration');
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitialSources(): array {
    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      // Fallback to a fixture mimicking the standard view layout.
      $sources = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_views', 'default_view');
      $this->initialDataSource = 'fixture';
    }

    return $sources;
  }

  /**
   * Check if the view display can be built with display builder.
   *
   * @param \Drupal\views\ViewExecutable $executable
   *   View executable.
   * @param string $display_id
   *   View display ID.
   *
   * @return bool
   *   Is the view display buildable or not.
   */
  private function isDisplayBuildable(ViewExecutable $executable, string $display_id): bool {
    $executable->setDisplay($display_id);
    $display = $executable->getDisplay();

    if (!$display->isEnabled()) {
      return FALSE;
    }

    if (\str_starts_with($display->getOption('path') ?? '', 'admin/')) {
      return FALSE;
    }

    if ($display->getOption('use_admin_theme')) {
      return FALSE;
    }

    /** @var array $definition */
    $definition = $display->getPluginDefinition();

    if ($definition['no_ui']) {
      return FALSE;
    }

    if (!isset($definition['theme'])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * The Views UI edit URL for a display not built with Display Builder.
   *
   * @param string $view_id
   *   The view id.
   * @param string $display_id
   *   The display id within the view.
   *
   * @return \Drupal\Core\Url
   *   The URL, falling back to the instance collection without views_ui.
   */
  private static function viewEditUrl(string $view_id, string $display_id): Url {
    if (!self::routeExists('entity.view.edit_display_form')) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('entity.view.edit_display_form', [
      'view' => $view_id,
      'display_id' => $display_id,
    ]);
  }

}
