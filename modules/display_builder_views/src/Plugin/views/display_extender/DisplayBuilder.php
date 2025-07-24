<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin\views\display_extender;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StorageProperties;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\Entity\DisplayBuilder as DisplayBuilderConfigEntity;
use Drupal\display_builder_views\DisplayBuilderViewsManager;
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
class DisplayBuilder extends DisplayExtenderPluginBase {

  private const VIEWS_PREFIX = 'views_';

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->stateManager = $container->get('display_builder.state_manager');
    $instance->configFormBuilder = $container->get('display_builder.config_form_builder');
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
    $instance_id = $this->options[StorageProperties::InstanceId->value] ?? NULL;
    $form[StorageProperties::InstanceId->value] = $this->buildInstanceForm($instance_id);
    $display_builder_id = $this->options[StorageProperties::ConfigEntityId->value] ?? DisplayBuilderConfigEntity::DISPLAY_BUILDER_CONFIG;
    $form[StorageProperties::ConfigEntityId->value] = $this->configFormBuilder->buildDisplayBuilder($display_builder_id);
  }

  /**
   * Build instance form.
   *
   * @param ?string $instance_id
   *   The key used to retrieve a state from the State API.
   *
   * @return array
   *   A form renderable array.
   */
  private function buildInstanceForm(?string $instance_id): array {
    // @todo no change or selection for now, just inform about the builder id.
    if (!$instance_id) {
      return [
        '#type' => 'select',
        '#title' => $this->t('Display builder instance'),
        '#options' => [
          '_new' => $this->t('Create the instance'),
          '_none' => $this->t('None (ignore)'),
        ],
        '#default_value' => '_new',
        '#required' => TRUE,
      ];
    }
    $url = Url::fromRoute('display_builder_views.views.manage', ['builder_id' => $instance_id]);
    return [
      '#type' => 'select',
      '#title' => $this->t('Display builder instance'),
      '#description' => $this->t('Display builder used to manage this view. <a href="@url" target="_blank">Edit here</a>', ['@url' => $url->toString()]),
      '#options' => [
        $instance_id => $instance_id,
        '_none' => $this->t('Disable (detach instance)'),
      ],
      '#default_value' => $instance_id,
      '#required' => TRUE,
    ];
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

    $instance = $form_state->getValue(StorageProperties::InstanceId->value);

    if ($instance === '_none') {
      unset($this->options[StorageProperties::ConfigEntityId->value], $this->options[StorageProperties::InstanceId->value]);
      return;
    }

    $builder_config_id = $form_state->getValue(StorageProperties::ConfigEntityId->value);
    $this->options[StorageProperties::ConfigEntityId->value] = $builder_config_id;

    // Create a new display builder and set empty data in the view display
    // option. Add view uuid to allow save with ON_SAVE event.
    if ($instance === '_new') {
      $instance = \sprintf('%s%s', self::VIEWS_PREFIX, uniqid());

      $contexts = [];
      // Mark for usage with views.
      $contexts = RequirementsContext::addToContext([DisplayBuilderViewsManager::VIEWS_CONTEXT_REQUIREMENT], $contexts);
      // Add view entity that we need in our sources or even UI Patterns Views
      // sources.
      $contexts['ui_patterns_views:view_entity'] = EntityContext::fromEntity($this->view->storage->load($this->view->id()));

      // Get fixtures if exist, fallback to default mimicking the standard view
      // blocks without markup.
      $builder_data = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_views');
      $this->stateManager->create($instance, $builder_config_id, $builder_data, $contexts);
    }

    // Re-set even if change is not yet allowed.
    $this->options[StorageProperties::InstanceId->value] = $instance;
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
    $is_display_builder = FALSE;

    if (isset($this->options[StorageProperties::InstanceId->value])) {
      $is_display_builder = $this->options[StorageProperties::InstanceId->value];
    }

    $options['display_builder'] = [
      'category' => 'other',
      'title' => $this->t('Display Builder'),
      'desc' => $this->t('Use display builder as output for this view.'),
      'value' => $is_display_builder ? $this->t('Yes') : $this->t('No'),
    ];
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
