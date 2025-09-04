<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\views\display_extender;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Registry;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\views\Attribute\ViewsDisplayExtender;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Styles display extender plugin.
 *
 * @ingroup views_display_extender_plugins
 */
#[ViewsDisplayExtender(
  id: 'display_builder',
  title: new TranslatableMarkup('Display Builder'),
  help: new TranslatableMarkup('Use display builder as output for this view.'),
  no_ui: FALSE,
)]
class DisplayExtender extends DisplayExtenderPluginBase implements WithDisplayBuilderInterface {

  /**
   * The config form builder for Display Builder.
   *
   * @var \Drupal\display_builder\ConfigFormBuilderInterface
   */
  protected $configFormBuilder;

  /**
   * The entity type interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The theme registry.
   */
  protected Registry $themeRegistry;

  /**
   * The list of modules.
   */
  protected ModuleExtensionList $modules;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFormBuilder = $container->get('display_builder.config_form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->themeRegistry = $container->get('theme.registry');
    $instance->modules = $container->get('extension.list.module');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('section') !== 'display_builder') {
      return;
    }

    $form['#title'] .= $this->t('Display Builder');
    $form[ConfigFormBuilderInterface::PROFILE_PROPERTY] = $this->configFormBuilder->build($this, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('section') !== 'display_builder') {
      return;
    }

    // @todo we should have always a fallback.
    $display_builder_config = $form_state->getValue(ConfigFormBuilderInterface::PROFILE_PROPERTY, 'default');
    $this->options[ConfigFormBuilderInterface::PROFILE_PROPERTY] = $display_builder_config;

    if (!empty($display_builder_config)) {
      $this->initInstanceIfMissing();

      return;
    }

    // If no Display Builder selected, we delete the related instance.
    // @todo Do we move that to the View's EntityInterface::delete() method?
    // @todo Also, when the changed are canceled from UI leaving the View
    // without Display Builder.
    $this->entityTypeManager->getStorage('display_builder_instance')->delete([$this->getInstance()]);
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options): void {
    if (!$this->isApplicable()) {
      return;
    }

    $options['display_builder'] = [
      'category' => 'other',
      'title' => $this->t('Display Builder'),
      'desc' => $this->t('Use display builder as output for this view.'),
      'value' => $this->getDisplayBuilder()?->label() ?? $this->t('Disabled'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preExecute(): void {
    if (!$this->getDisplayBuilder()) {
      return;
    }
    // We alter the registry here instead of implementing
    // hook_theme_registry_alter in order keep the alteration specific to each
    // view.
    $view = $this->view;
    // Theme hook suggestion of the current view display.
    $suggestion = \implode('__', ['views_view', $view->id(), $view->getDisplay()->getPluginId()]);
    $entry = $this->buildThemeRegistryEntry();
    $this->themeRegistry->getRuntime()->set($suggestion, $entry);
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
      'view' => $this->view->id(),
      'display' => $this->view->current_display,
    ];

    return Url::fromRoute('display_builder_views.views.manage', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    // Examples: view__articles__default, view__people__grid.
    if (!\str_starts_with($instance_id, 'view__')) {
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
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    return Url::fromRoute('display_builder_views.views.manage', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    return Url::fromRoute('entity.view.edit_form', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface {
    if (!isset($this->options[ConfigFormBuilderInterface::PROFILE_PROPERTY])) {
      return NULL;
    }
    $display_builder_id = $this->options[ConfigFormBuilderInterface::PROFILE_PROPERTY];

    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('display_builder');

    /** @var \Drupal\display_builder\DisplayBuilderInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Examples: view__articles__default, view__people__grid.
    return 'view__' . $this->view->id() . '__' . $this->view->current_display;
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceStorage $storage */
    $storage = $this->entityTypeManager->getStorage('display_builder_instance');

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->load($this->getInstanceId());

    if (!$instance) {
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
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
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($this->view->storage);

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->options[ConfigFormBuilderInterface::SOURCES_PROPERTY] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $sources = $this->getInstance()->getCurrentState();
    $displays = $this->view->storage->get('display');
    $display_id = $this->view->current_display;
    // It is risky to alter a View like that. We need to be careful to not
    // break the storage integrity, but we didn't find a better way.
    $displays[$display_id]['display_options']['display_extenders']['display_builder']['sources'] = $sources;
    $this->view->storage->set('display', $displays);
    $this->view->storage->save();
    // @todo Test if we still need to invalidate the cache manually here.
    $this->view->storage->invalidateCaches();
  }

  /**
   * Build theme registry entry.
   *
   * @return array
   *   A theme registry entry.
   */
  protected function buildThemeRegistryEntry(): array {
    $theme_registry = $this->themeRegistry->get();
    // Identical to views_view with a specific path.
    $entry = $theme_registry['views_view'];
    $entry['path'] = $this->modules->getPath('display_builder_views') . '/templates';

    return $entry;
  }

  /**
   * If display builder can be applied to this display.
   *
   * @return bool
   *   Applicable or not.
   */
  private function isApplicable(): bool {
    $display = $this->view->getDisplay();
    $display_definition = $display->getPluginDefinition();

    if (!isset($display_definition['class'])) {
      return FALSE;
    }

    // Do not include with feed and entity reference, as they have no output to
    // apply a display builder to.
    if ($display_definition['class'] === 'Drupal\views\Plugin\views\display\Feed') {
      return FALSE;
    }

    if ($display_definition['class'] === 'Drupal\views\Plugin\views\display\EntityReference') {
      return FALSE;
    }

    // @todo safer to not allow third party display?
    // phpcs:disable
    // if (str_contains($display_definition['class'], 'Drupal\views\Plugin\views\display')) {
    //   return FALSE;
    // }
    // phpcs:enable

    return TRUE;
  }

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   The state manager.
   */
  private function getInstance(): ?InstanceInterface {
    if (!isset($this->instance)) {
      /** @var \Drupal\display_builder\InstanceInterface|null $instance */
      $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($this->getInstanceId());
      $this->instance = $instance;
    }

    return $this->instance;
  }

}
