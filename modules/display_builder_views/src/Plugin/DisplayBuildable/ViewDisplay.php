<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\DisplayBuildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\ProfileInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
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
    $this->extender = $configuration['extender'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    // @todo replace by attribute
    return 'views__';
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    // @see \Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewRowsSource.
    return 'views:style';
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
    if (!isset($this->extender->options[ConfigFormBuilderInterface::PROFILE_PROPERTY])) {
      return NULL;
    }
    $display_builder_id = $this->extender->options[ConfigFormBuilderInterface::PROFILE_PROPERTY];

    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');

    /** @var \Drupal\display_builder\ProfileInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      // Fallback to a fixture mimicking the standard view layout.
      $sources = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_views', 'default_view');
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $contexts = [];
    // Mark for usage with views.
    $contexts = RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
    // Add view entity that we need in our sources or even UI Patterns Views
    // sources.
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($this->extender->view->storage);

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->extender->options[ConfigFormBuilderInterface::SOURCES_PROPERTY] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $sources = $this->getInstance()->getCurrentState();
    // First, we save in the "live" object.
    $this->extender->options[ConfigFormBuilderInterface::SOURCES_PROPERTY] = $sources;
    // Then, we save in the permanent storage.
    $displays = $this->extender->view->storage->get('display');
    $display_id = $this->extender->view->current_display;
    // It is risky to alter a View like that. We need to be careful to not
    // break the storage integrity, but we didn't find a better way.
    $displays[$display_id]['display_options']['display_extenders']['display_builder'][ConfigFormBuilderInterface::SOURCES_PROPERTY] = $sources;
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

}
