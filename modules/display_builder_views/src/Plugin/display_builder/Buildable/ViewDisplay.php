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
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\views\Entity\View;
use Drupal\views\Plugin\views\PluginBase;

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
   * View display extender plugin.
   */
  protected PluginBase $extender;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // It is better to take the plugin from configuration when available in
    // order to manipulate the view "executable" from the tempstore.  So, we
    // have access to the state not yet saved in config.
    if (isset($configuration['extender'])) {
      $this->extender = $configuration['extender'];
      // Configuration to store in the Instance entity.
      unset($this->configuration['extender']);
      $this->configuration['view_id'] = $this->extender->view->id();
      $this->configuration['view_display'] = $this->extender->view->current_display;

      return;
    }

    // If extender plugin is not directly passed, we can get it from the
    // configuration data (as stored in Instance entity):
    // - view_id (string)
    // - view_display (string)
    // However, this is loading a "real" View entity, as stored in config. So
    // it may miss unsaved parameters.
    $view = View::load($configuration['view_id'] ?? '')?->getExecutable() ?? NULL;

    if ($view) {
      $view->setDisplay($configuration['view_display'] ?? '');
      $this->extender = $view->getDisplay()->getExtenders()['display_builder'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $params = [
      'view' => $this->extender->view->id(),
      'display' => $this->extender->view->current_display,
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
    $display_builder_id = $this->extender->options[DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL;

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
    return $this->extender->options[DisplayBuildableInterface::SOURCES_PROPERTY] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $sources = $this->getInstance()->getCurrentState();
    // First, we save in the "live" object.
    $this->extender->options[DisplayBuildableInterface::SOURCES_PROPERTY] = $sources;
    // Then, we save in the permanent storage.
    $displays = $this->extender->view->storage->get('display');
    $display_id = $this->extender->view->current_display;
    // It is risky to alter a View like that. We need to be careful to not
    // break the storage integrity, but we didn't find a better way.
    $displays[$display_id]['display_options']['display_extenders']['display_builder'][DisplayBuildableInterface::SOURCES_PROPERTY] = $sources;
    $this->extender->view->storage->set('display', $displays);
    $this->extender->view->storage->save();
    // @todo Test if we still need to invalidate the cache manually here.
    $this->extender->view->storage->invalidateCaches();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): string {
    return \sprintf('%s%s__%s', self::getPrefix(), $this->extender->view->id(), $this->extender->view->current_display);
  }

  /**
   * {@inheritdoc}
   */
  public static function collectInstances(): array {
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $displayBuildableManager = \Drupal::service('plugin.manager.display_buildable');
    $storage = $entityTypeManager->getStorage('view');
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
        $buildable = $displayBuildableManager->createInstance('view_display',
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
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    // Contexts needed by UI Patterns Source can be added by each Display
    // Buildable plugin by overriding this method.
    // @todo filter by $unqualified_context_ids.
    $contexts = [];
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($this->extender->view->storage);
    // Needed by ui_patterns_views's ViewRowsSource.
    // Will be filled by \Drupal\display_builder_views\Hook\PreprocessViewsView.
    $contexts['ui_patterns_views:rows'] = new Context(new ContextDefinition('any'), []);
    $contexts = RequirementsContext::addToContext(['views:style'], $contexts);

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    if ($this->initialDataSource === 'fixture') {
      return $this->t('Initialization from default configuration.');
    }

    return $this->t('Initialization from existing View configuration.');
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

}
