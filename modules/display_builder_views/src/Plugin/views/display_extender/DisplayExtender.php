<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\views\display_extender;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\Registry;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\InstanceInterface;
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
final class DisplayExtender extends DisplayExtenderPluginBase {

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
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
    $form[DisplayBuildableInterface::PROFILE_PROPERTY] = $this->displayBuildable()->buildInstanceForm(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state): void {
    if ($form_state->get('section') !== 'display_builder') {
      return;
    }

    // @todo we should have always a fallback.
    $profile_id = $form_state->getValue(DisplayBuildableInterface::PROFILE_PROPERTY, 'default');
    $this->options[DisplayBuildableInterface::PROFILE_PROPERTY] = $profile_id;

    if (empty($profile_id)) {
      // If no Display Builder selected, we delete the related instance.
      // @todo Do we move that to the View's EntityInterface::delete() method?
      // @todo Also, when the changed are canceled from UI leaving the View
      // without Display Builder.
      $storage = $this->entityTypeManager->getStorage('display_builder_instance');
      $storage->delete([$this->getInstance()]);

      return;
    }

    $buildable = $this->displayBuildable();
    $buildable->initInstanceIfMissing();
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options): void {
    $buildable = $this->displayBuildable();

    if (!$this->isApplicable()) {
      return;
    }

    $options['display_builder'] = [
      'category' => 'other',
      'title' => $this->t('Display Builder'),
      'desc' => $this->t('Use display builder as output for this view.'),
      'value' => $buildable->getProfile()?->label() ?? $this->t('Disabled'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preExecute(): void {
    $buildable = $this->displayBuildable();

    if (!$buildable->getProfile()) {
      return;
    }
    // We alter the registry here instead of implementing
    // hook_theme_registry_alter in order keep the alteration specific to each
    // view.
    $view = $this->view;
    // Theme hook suggestion of the current view display.
    $suggestion = \implode('__', ['views_view', $view->id(), $view->current_display]);
    $entry = $this->buildThemeRegistryEntry();
    $this->themeRegistry->getRuntime()->set($suggestion, $entry);
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
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  protected function getInstance(): ?InstanceInterface {
    if (!isset($this->instance)) {
      $this->instance = $this->displayBuildable()->getInstance();
    }

    return $this->instance;
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
   * Gets the display buildable manager.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface
   *   The manager for display buildable.
   */
  private function displayBuildable(): DisplayBuildableInterface {
    /** @var \Drupal\display_builder\DisplayBuildablePluginManager $manager */
    $manager = \Drupal::service('plugin.manager.display_buildable');
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $manager->createInstance('view_display', ['extender' => $this]);

    return $buildable;
  }

}
