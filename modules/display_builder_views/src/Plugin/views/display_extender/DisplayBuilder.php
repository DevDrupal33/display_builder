<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\views\display_extender;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\DisplayBuilderInterface;
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
class DisplayBuilder extends DisplayExtenderPluginBase implements WithDisplayBuilderInterface {

  /**
   * The display builder state manager.
   *
   * @var \Drupal\display_builder\StateManager\StateManagerInterface
   */
  protected $stateManager;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stateManager = $container->get('display_builder.state_manager');
    $instance->configFormBuilder = $container->get('display_builder.config_form_builder');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
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
   *
   * @phpstan-ignore-next-line
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
    $this->stateManager->delete($this->getInstanceId() ?? '');
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-ignore-next-line
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
  public static function getContextRequirement(): string {
    // @see Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewRowsSource.
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
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $view = \explode('__', $instance_id)[1];
    $display = \explode('__', $instance_id)[2];
    $params = ['view' => $view, 'display' => $display];

    return Url::fromRoute('display_builder_views.views.manage', $params);
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
    $instance_id = $this->getInstanceId();
    // One instance in State API by page layout view display.
    $instance = $this->stateManager->load($instance_id);

    if ($instance !== NULL) {
      // The instance already exists in State Manager, so nothing to do.
      return;
    }
    // Init instance if missing in State Manager because the View display is new
    // or because the instance was deleted in the State API.
    $contexts = [];
    // Mark for usage with views.
    $contexts = RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
    // Add view entity that we need in our sources or even UI Patterns Views
    // sources.
    $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($this->view->storage);

    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      // Fallback to a fixture mimicking the standard view layout.
      $sources = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_views', '', 'default_view');
    }
    $this->stateManager->create($instance_id, (string) $this->getDisplayBuilder()->id(), $sources, $contexts);
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
    $sources = $this->stateManager->getCurrentState($this->getInstanceId());
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
   * If display builder can be applied to this display.
   *
   * @return bool
   *   Applicable or not.
   */
  private function isApplicable(): bool {
    $display = $this->view->getDisplay();
    $display_definition = $display->getPluginDefinition();

    // @phpstan-ignore-next-line
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

}
