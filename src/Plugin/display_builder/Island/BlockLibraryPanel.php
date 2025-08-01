<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns_overrides\SourcesBundlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Block library island plugin implementation.
 */
#[Island(
  id: 'block_library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Blocks library'),
  description: new TranslatableMarkup('List of available Drupal blocks to use.'),
  type: IslandType::Library,
)]
class BlockLibraryPanel extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;

  private const HIDE_BLOCK = [
    'help_block',
    'system_messages_block',
    'htmx_loader',
    'broken',
    'system_main_block',
    'page_title_block',
  ];

  private const HIDE_SOURCE = [
    'block',
    'component',
  ];

  /**
   * Component provider to exclude by default.
   *
   * @var array
   *   The providers to exclude.
   */
  private const PROVIDER_EXCLUDE = [
    'ui_patterns_blocks',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $sdcManager,
    protected HtmxEvents $htmxEvents,
    protected StateManagerInterface $stateManager,
    protected EventSubscriberInterface $eventSubscriber,
    protected SourcePluginManager $sourceManager,
    protected ModuleExtensionList $modules,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $sdcManager, $htmxEvents, $stateManager, $eventSubscriber, $sourceManager);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'providers' => $this->getDefaultProviders(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.sdc'),
      $container->get('display_builder.htmx_events'),
      $container->get('display_builder.state_manager'),
      $container->get('display_builder.event_subscriber'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('extension.list.module'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['providers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed modules'),
      '#options' => $this->getProvidersOptions(),
      '#default_value' => $configuration['providers'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    return [
      $this->t('Allowed modules: @providers', [
        '@providers' => \implode(', ', \array_filter($configuration['providers'] ?? []) ?: [$this->t('None')]),
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Blocks';
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    $source_contexts = $this->configuration['contexts'] ?? [];

    $sources = $this->sourceManager->getDefinitionsForPropType('slot', $source_contexts);
    /** @var \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source */
    $block_source = $this->sourceManager->createInstance('block', $this->configuration);

    return [
      $this->buildOtherSources($builder_id, $sources),
      $this->buildDrupalBlocks($builder_id, $block_source),
    ];
  }

  /**
   * Build other sources.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param array $sources
   *   Array of source definitions.
   *
   * @return array
   *   Render array for the other sources section.
   */
  protected function buildOtherSources(string $builder_id, array $sources): array {
    $placeholders = [];

    foreach ($sources as $source_id => $definition) {
      if (\in_array($source_id, self::HIDE_SOURCE, TRUE)) {
        continue;
      }
      $data = [
        'source_id' => $source_id,
      ];
      $keywords = \sprintf('%s %s %s', $definition['id'], $definition['label'] ?? '', $definition['description'] ?? '');
      $build = $this->buildPlaceholderButton($definition['label'], $data, $keywords);
      $placeholders[] = $build;
    }

    return $this->buildDraggables($builder_id, $placeholders);
  }

  /**
   * Filter definitions according to configuration.
   *
   * @param array $definitions
   *   An associative array of module definitions keyed by module ID.
   *
   * @@return array
   *  An associative array of module definitions keyed by module ID.
   */
  protected function filterDefinitions(array $definitions): array {
    $configuration = $this->getConfiguration();
    $allowed_providers = $configuration['providers'];

    if (!$allowed_providers) {
      return [];
    }
    $filtered = [];

    foreach ($definitions as $block_id => $definition) {
      if (\in_array($block_id, self::HIDE_BLOCK, TRUE)) {
        continue;
      }

      if (\in_array($definition['provider'], self::PROVIDER_EXCLUDE, TRUE)) {
        continue;
      }

      if (!\in_array($definition['provider'], $allowed_providers, TRUE)) {
        continue;
      }
      $filtered[$block_id] = $definition;
    }

    return $filtered;
  }

  /**
   * Get Drupal block plugins.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source
   *   The block source to build.
   *
   * @return array
   *   Array of block plugins, keyed by block ID with admin label as value.
   */
  protected function buildDrupalBlocks(string $builder_id, SourcesBundlerInterface $block_source): array {
    $definitions = $block_source->getOptions();
    $definitions = $this->filterDefinitions($definitions);
    $names = \array_column($definitions, 'admin_label');
    \array_multisort($names, \SORT_ASC, $definitions);
    $views_blocks = [];
    $menu_blocks = [];
    $other_blocks = [];

    foreach ($definitions as $block_id => $definition) {
      if (\str_starts_with($block_id, 'views_block:')) {
        $views_blocks[$block_id] = $definition;
      }
      elseif (\str_starts_with($block_id, 'system_menu_block:')) {
        $menu_blocks[$block_id] = $definition;
      }
      else {
        $other_blocks[$block_id] = $definition;
      }
    }
    $build = [
      $views_blocks ? $this->buildDrupalBlocksGroup($builder_id, $this->t('List (Views)'), $views_blocks, $block_source) : [],
      $menu_blocks ? $this->buildDrupalBlocksGroup($builder_id, $this->t('Menus'), $menu_blocks, $block_source) : [],
      $other_blocks ? $this->buildDrupalBlocksGroup($builder_id, $this->t('Others'), $other_blocks, $block_source) : [],
    ];
    $build = $this->buildDraggables($builder_id, $build);
    $build['#source_contexts'] = $this->configuration['contexts'] ?? [];

    return $build;
  }

  /**
   * Build a group of block placeholders.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The group title.
   * @param array $definitions
   *   Block plugin definitions.
   * @param \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source
   *   The block source to build.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildDrupalBlocksGroup(string $builder_id, string|TranslatableMarkup $title, array $definitions, SourcesBundlerInterface $block_source): array {
    $build = [
      [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        // We hide the group titles on search.
        '#attributes' => ['class' => 'db-filter-hide-on-search'],
        '#value' => $title,
      ],
    ];

    foreach ($definitions as $block_id => $definition) {
      $data = $block_source->getDataSkeleton($block_id);
      $keywords = \sprintf('%s %s %s', $definition['id'], $definition['admin_label'] ?? '', $definition['category'] ?? '');
      $block_preview_url = Url::fromRoute('display_builder.api_block_preview', ['block_id' => $block_id]);
      $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $definition['admin_label'], $data, $block_preview_url, $keywords);
    }

    return $build;
  }

  /**
   * Get providers options for select input.
   *
   * @return array
   *   An associative array with module ID as key and module description as
   *   value.
   */
  protected function getProvidersOptions(): array {
    $options = [];

    foreach ($this->getProviders() as $provider_id => $provider) {
      $params = [
        '@name' => $provider['name'],
        '@count' => $provider['count'],
      ];
      $options[$provider_id] = $this->formatPlural($provider['count'], '@name (@count block)', '@name (@count blocks)', $params);
    }

    return $options;
  }

  /**
   * Get all providers.
   *
   * @return array
   *   Drupal modules definitions, keyed by extension ID
   */
  protected function getProviders(): array {
    /** @var \Drupal\ui_patterns_overrides\SourcesBundlerInterface $block_source */
    $block_source = $this->sourceManager->createInstance('block', $this->configuration);
    $modules = $this->modules->getAllInstalledInfo();
    $providers = [];

    foreach ($block_source->getOptions() as $block_id => $block) {
      if (\in_array($block_id, self::HIDE_BLOCK, TRUE)) {
        continue;
      }
      $provider = $block['provider'];
      $definition = $modules[$provider];
      $definition['count'] = isset($providers[$provider]) ? ($providers[$provider]['count']) + 1 : 1;
      $providers[$provider] = $definition;
    }

    return $providers;
  }

  /**
   * Get default providers.
   *
   * @return array
   *   A list of Drupal modules IDs.
   */
  protected function getDefaultProviders(): array {
    $providers = [];

    foreach (\array_keys($this->getProviders()) as $provider_id) {
      // If the provider is part of the excluded list, skip it.
      if (\in_array($provider_id, self::PROVIDER_EXCLUDE, TRUE)) {
        continue;
      }
      $providers[] = $provider_id;
    }

    return $providers;
  }

}
