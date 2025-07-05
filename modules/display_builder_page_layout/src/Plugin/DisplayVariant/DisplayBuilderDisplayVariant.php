<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\DisplayVariant;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Display\Attribute\DisplayVariant;
use Drupal\Core\Display\ContextAwareVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_page_layout\DisplayBuilderPageLayout;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Layout Builder variant.
 *
 * @todo do we need this display or only the page?
 */
#[DisplayVariant(
  id: 'display_builder',
  admin_label: new TranslatableMarkup('Display Builder (Content only)'),
)]
class DisplayBuilderDisplayVariant extends VariantBase implements ContainerFactoryPluginInterface, ContextAwareVariantInterface {

  private const PAGE_MANAGER_PREFIX = 'page_manager_';

  /**
   * An array of collected contexts.
   *
   * This is only used on runtime, and is not stored.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $contexts = [];

  /**
   * The display builder state manager.
   */
  protected StateManagerInterface $stateManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The display builder page layout config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Component element builder.
   *
   * @var \Drupal\ui_patterns\Element\ComponentElementBuilder
   */
  protected $componentElementBuilder;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    StateManagerInterface $state_manager,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ComponentElementBuilder $component_element_builder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stateManager = $state_manager;
    $this->config = $config_factory->get(DisplayBuilderPageLayout::PAGE_LAYOUT_CONFIG);
    $this->entityTypeManager = $entity_type_manager;
    $this->componentElementBuilder = $component_element_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('display_builder.state_manager'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('ui_patterns.component_element_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];

    $builder_id = $this->configuration['display_builder_id'] ?? '';

    if (empty($this->configuration['display_builder_id'])) {
      throw new \LogicException('Missing display builder id in this page manager variant.');
    }

    $builder_data = $this->stateManager->getCurrentState($builder_id);
    $contexts = $this->stateManager->getContexts($builder_id) ?? [];
    // @todo merge contexts from page manager.
    // phpcs:ignore-next-line
    // $contexts = \array_merge($contexts, $this->getContexts());
    $display_builder_data = [];

    foreach ($builder_data as $source_data) {
      $build = $this->componentElementBuilder->buildSource([], 'content', [], $source_data, $contexts);
      $display_builder_data[] = $build['#slots']['content'][0] ?? [];
    }

    return $display_builder_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts(): array {
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function setContexts(array $contexts): self {
    $this->contexts = $contexts;

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    if (isset($this->configuration['display_builder_id']) && !empty($this->configuration['display_builder_id'])) {
      $url = Url::fromRoute('display_builder_page_layout.page_manager.manage', ['builder_id' => $this->configuration['display_builder_id']]);
      $form['info'] = [
        '#markup' => '<p>' . $this->t('Got to the <a href="@url">display builder edit page</a> to edit this variant.', ['@url' => $url->toString()]) . '</p>',
      ];

      return $form;
    }

    $displayBuilderConfig = $this->entityTypeManager->getStorage('display_builder')->loadMultiple();
    $options = [];

    foreach ($displayBuilderConfig as $entityId => $configEntity) {
      $options[$entityId] = $configEntity->label();
    }
    $form['builder_config_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Display Builder config'),
      '#description' => $this->t('Pick a display builder configuration to use.'),
      '#options' => $options,
      '#default_value' => $this->configuration['builder_config_id'] ?? DisplayBuilder::DISPLAY_BUILDER_CONFIG,
      '#required' => TRUE,
    ];

    $form['fixture_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Initial content'),
      '#description' => $this->t('Enter the initial content to use for this display builder.'),
      '#options' => DisplayBuilderHelpers::getAllFixturesOptions(['display_builder_page_layout']),
      '#default_value' => 'blank',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Save label.
    parent::submitConfigurationForm($form, $form_state);

    // Nothing to do if we are editing a variant on page manager form.
    if (empty($form_state->getValues())) {
      return;
    }

    // Generate a display builder instance id, prefix is only to ease
    // identification and is never used for any process or logic.
    $display_builder_id = \sprintf('%s%s', self::PAGE_MANAGER_PREFIX, uniqid());
    $builder_config_id = $form_state->getValue('builder_config_id');

    // Get the initial data from fixture.
    // @todo display_builder_devel module will be deleted or split.
    $fixture_id = $form_state->getValue('fixture_id');
    $builder_data = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_page_layout', '', $fixture_id);

    // Set context mark to know it's a page manager builder, and add the page
    // manager config uuid to save later builder data in the page manager
    // configuration object.
    // We add both requirements to allow sources from
    // Drupal\ui_patterns_overrides\Plugin\UiPatterns\Source.
    $contexts = [];
    $requirements = [
      DisplayBuilderPageLayout::PAGE_MANAGER_CONTEXT_REQUIREMENT,
      DisplayBuilderPageLayout::PAGE_LAYOUT_CONTEXT_REQUIREMENT,
    ];
    $contexts = RequirementsContext::addToContext($requirements, $contexts);
    $contexts['page_manager_variant_uuid'] = new Context(ContextDefinition::create('string'), $this->configuration['uuid'] ?? '');

    $this->stateManager->create($display_builder_id, $builder_config_id, $builder_data, $contexts);

    $this->configuration['display_builder_id'] = $display_builder_id;
    $this->configuration['display_builder_sources'] = $builder_data;
  }

}
