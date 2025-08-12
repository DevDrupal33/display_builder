<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Component library island plugin implementation.
 */
#[Island(
  id: 'component_library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Components library'),
  description: new TranslatableMarkup('List of available Components to use.'),
  type: IslandType::Library,
)]
class ComponentLibraryPanel extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;

  /**
   * Component provider to exclude by default.
   *
   * @var array
   *   The providers to exclude.
   */
  private const PROVIDER_EXCLUDE = ['display_builder', 'sdc_devel'];

  /**
   * The definitions filtered for current theme.
   *
   * @var array
   *   The definitions filtered.
   */
  private array $definitionsFiltered = [];

  /**
   * The definitions filtered and grouped for current theme.
   *
   * @var array
   *   The definitions filtered and grouped.
   */
  private array $definitionsGrouped = [];

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
    protected ThemeManagerInterface $themeManager,
    protected ModuleExtensionList $modules,
    protected ThemeExtensionList $themes,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $sdcManager, $htmxEvents, $stateManager, $eventSubscriber, $sourceManager);
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
      $container->get('theme.manager'),
      $container->get('extension.list.module'),
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Components';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'providers' => $this->getDefaultProviders(),
      'status' => [
        'experimental',
      ],
      'include_no_ui' => FALSE,
      'show_grouped' => TRUE,
      'show_variants' => TRUE,
      'show_mosaic' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['providers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed providers'),
      '#options' => $this->getProvidersOptions(),
      '#default_value' => $configuration['providers'],
    ];

    // @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/assets/schemas/v1/metadata.schema.json#L217
    $form['status'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed status'),
      '#options' => [
        'experimental' => $this->t('Experimental'),
        'deprecated' => $this->t('Deprecated'),
        'obsolete' => $this->t('Obsolete'),
      ],
      '#description' => $this->t('Components with stable or undefined status will always be available.'),
      '#default_value' => $configuration['status'],
    ];

    $form['show_grouped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show components grouped'),
      '#description' => $this->t('Provide a list of grouped components for selection.'),
      '#default_value' => $configuration['show_grouped'],
    ];

    $form['show_variants'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show components variants'),
      '#description' => $this->t('Provide a list of components per variants for selection.'),
      '#default_value' => $configuration['show_variants'],
    ];

    $form['show_mosaic'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show components mosaic'),
      '#description' => $this->t('Provide a list of mosaic components for selection.'),
      '#default_value' => $configuration['show_mosaic'],
    ];

    // Drupal 11.3+ new exclude feature.
    // @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/assets/schemas/v1/metadata.schema.json#L228
    $form['include_no_ui'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include marked as excluded from the UI'),
      '#description' => $this->t('Components with no ui flag are meant for internal use only. Force to include them. Drupal 11.3+ only.'),
      '#default_value' => $configuration['include_no_ui'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    // At least one provider must be enabled.
    if (empty(\array_filter($values['providers']))) {
      $form_state->setError($form['providers'], $this->t('At least one provider must be selected!'));
    }

    // At least one display must be enabled.
    $show_grouped = (bool) $values['show_grouped'];
    $show_variants = (bool) $values['show_variants'];
    $show_mosaic = (bool) $values['show_mosaic'];

    if (!$show_grouped && !$show_variants && !$show_mosaic) {
      $form_state->setError($form['show_grouped'], $this->t('At least one display must be selected!'));
      $form_state->setError($form['show_variants'], $this->t('At least one display must be selected!'));
      $form_state->setError($form['show_mosaic'], $this->t('At least one display must be selected!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    $summary = [];

    $summary[] = $this->t('Allowed providers: @providers', [
      '@providers' => ($providers = \array_filter($configuration['providers'] ?? [])) ? \implode(', ', $providers) : $this->t('None'),
    ]);
    $summary[] = $this->t('Allowed status: @status', [
      '@status' => \implode(', ', \array_filter(\array_unique(\array_merge(['stable', 'undefined'], $configuration['status'] ?? []))) ?: [$this->t('stable, undefined')]),
    ]);
    $summary[] = $configuration['include_no_ui'] ? $this->t('Include no UI components') : $this->t('Exclude no UI components');

    $list = [];

    if ((bool) $configuration['show_grouped']) {
      $list[] = $this->t('grouped');
    }

    if ((bool) $configuration['show_variants']) {
      $list[] = $this->t('variants');
    }

    if ((bool) $configuration['show_mosaic']) {
      $list[] = $this->t('mosaic');
    }
    $summary[] = $this->t('Components list as: @list', [
      '@list' => !empty($list) ? \implode(', ', $list) : $this->t('None selected'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    // Try to call only once for each sub islands.
    $definitions = $this->getDefinitions();
    $configuration = $this->getConfiguration();

    $this->definitionsFiltered = $definitions['filtered'] ?? [];
    $this->definitionsGrouped = $definitions['grouped'] ?? [];

    $panes = [];

    if ((bool) $configuration['show_grouped']) {
      $panes['grouped'] = [
        'title' => $this->t('Grouped'),
        'content' => $this->getComponentsGrouped($builder_id),
      ];
    }

    if ((bool) $configuration['show_variants']) {
      $panes['variants'] = [
        'title' => $this->t('Variants'),
        'content' => $this->getComponentsVariants($builder_id),
      ];
    }

    if ((bool) $configuration['show_mosaic']) {
      $panes['mosaic'] = [
        'title' => $this->t('Mosaic'),
        'content' => $this->getComponentsMosaic($builder_id),
      ];
    }

    $tabs = [];
    $content = [];

    foreach ($panes as $pane_id => $pane) {
      $id = 'db-' . $builder_id . '-components-tab---' . $pane_id;
      $tabs[] = [
        'title' => $pane['title'],
        'url' => '#' . $id,
      ];
      $content[] = $this->wrapContent($pane['content'], $id);
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:library_panel',
      '#slots' => [
        'tabs' => $this->buildTabs('db-' . $builder_id . '-components-tabs', $tabs),
        'content' => $content,
      ],
    ];
  }

  /**
   * Get providers for ConfigurableInterface::defaultConfiguration().
   *
   * @return array
   *   An associative array of providers ID and definitions.
   */
  protected function getDefaultProviders(): array {
    $providers = [];
    $default_theme = \Drupal::config('system.theme')->get('default');
    $active_themes = $this->buildRecursiveListOfParentThemes($default_theme, [$default_theme]);

    foreach ($this->getProviders() as $provider_id => $provider) {
      // If the provider is not the active theme or its parents, skip it.
      if ($provider['type'] === 'theme' && !\in_array($provider_id, $active_themes, TRUE)) {
        continue;
      }

      // If the provider is part of the excluded list, skip it.
      if (\in_array($provider_id, self::PROVIDER_EXCLUDE, TRUE)) {
        continue;
      }
      $providers[] = $provider_id;
    }

    return $providers;
  }

  /**
   * Build recursive list of parent themes.
   *
   * @param string $current
   *   The extension ID of the current (in the recursive loop context) theme.
   * @param array $themes
   *   The list from the previous recursive step.
   *
   * @return array
   *   The updated list of extensions ID.
   */
  protected function buildRecursiveListOfParentThemes(string $current, array $themes): array {
    $info = $this->themes->get($current)->info;

    if (!isset($info['base theme'])) {
      return $themes;
    }
    $parent = $info['base theme'];

    return $this->buildRecursiveListOfParentThemes($parent, \array_merge($themes, [$parent]));
  }

  /**
   * Get providers options for select input.
   *
   * @return array
   *   An associative array with extension ID as key and extension description
   *   as value.
   */
  protected function getProvidersOptions(): array {
    $options = [];

    foreach ($this->getProviders() as $provider_id => $provider) {
      $params = [
        '@name' => $provider['name'],
        '@type' => $provider['type'],
        '@count' => $provider['count'],
      ];
      $options[$provider_id] = $this->formatPlural($provider['count'], '@name (@type, @count component)', '@name (@type, @count components)', $params);
    }

    return $options;
  }

  /**
   * Get all providers.
   *
   * @return array
   *   Drupal extension definitions, keyed by extension ID
   */
  protected function getProviders(): array {
    $themes = $this->themes->getAllInstalledInfo();
    $modules = $this->modules->getAllInstalledInfo();
    $providers = [];
    $components = $this->sdcManager->getDefinitions();

    foreach ($components as $component) {
      $provider = $component['provider'];
      $definition = $themes[$provider] ?? $modules[$provider] ?? NULL;

      if (!$definition) {
        continue;
      }
      $definition['count'] = isset($providers[$provider]) ? ($providers[$provider]['count']) + 1 : 1;
      $providers[$provider] = $definition;
    }

    return $providers;
  }

  /**
   * Gets the grouped components view.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return array
   *   A renderable array containing the grouped components.
   */
  private function getComponentsGrouped(string $builder_id): array {
    $build = [];

    /** @var \Drupal\ui_patterns\SourceWithChoicesInterface $source */
    $source = $this->sourceManager->createInstance('component');

    foreach ($this->definitionsGrouped as $group_name => $group) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $group_name,
        '#attributes' => [
          'class' => ['db-filter-hide-on-search'],
        ],
      ];

      foreach ($group as $component_id => $definition) {
        $component_id = (string) $component_id;
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);

        $data = [
          'source_id' => 'component',
          'source' => [
            'component' => [
              'component_id' => $source->getChoiceSettings($component_id),
            ],
          ],
        ];
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);
        $build[] = $this->buildPlaceholderButtonWithPreview($builder_id, $definition['annotated_name'], $data, $component_preview_url, $keywords);
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Gets the components variants view.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return array
   *   A renderable array containing the variants placeholders.
   */
  private function getComponentsVariants(string $builder_id): array {
    $build = [];

    /** @var \Drupal\ui_patterns\SourceWithChoicesInterface $source */
    $source = $this->sourceManager->createInstance('component');

    foreach ($this->definitionsFiltered as $component_id => $definition) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $definition['annotated_name'],
        '#attributes' => [
          'data-filter-parent' => $definition['machineName'],
        ],
      ];

      $data = [
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => $source->getChoiceSettings($component_id),
          ],
        ],
      ];

      if (!isset($definition['variants'])) {
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);
        $build_variant = $this->buildPlaceholderButtonWithPreview($builder_id, $this->t('Default'), $data, $component_preview_url, $keywords);
        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
        $build[] = $build_variant;

        continue;
      }

      foreach ($definition['variants'] ?? [] as $variant_id => $variant) {
        $params = ['component_id' => $component_id, 'variant_id' => $variant_id];
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', $params);
        $data['source']['component']['variant_id'] = [
          'source_id' => 'select',
          'source' => [
            'value' => $variant_id,
          ],
        ];
        // Used for search filter.
        $keywords = \sprintf('%s %s %s', $definition['label'], $variant['title'], $definition['provider']);
        $build_variant = $this->buildPlaceholderButtonWithPreview($builder_id, $variant['title'], $data, $component_preview_url, $keywords);
        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
        $build[] = $build_variant;
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Gets the mosaic view of components.
   *
   * @param string $builder_id
   *   Builder ID.
   *
   * @return array
   *   A renderable array containing the mosaic view of components.
   */
  private function getComponentsMosaic(string $builder_id): array {
    $components = [];

    /** @var \Drupal\ui_patterns\SourceInterface $source */
    $source = $this->sourceManager->createInstance('component');

    foreach (\array_keys($this->definitionsFiltered) as $component_id) {
      $component_id = (string) $component_id;
      $component = $this->sdcManager->find($component_id);
      $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);

      /** @var \Drupal\ui_patterns\SourceWithChoicesInterface $source */
      $vals = [
        'source_id' => 'component',
        'source' => [
          'component' => [
            'component_id' => $source->getChoiceSettings($component_id),
          ],
        ],
      ];
      $thumbnail = $component->metadata->getThumbnailPath();

      // Used for search filter.
      $keywords = \sprintf('%s %s', $component->metadata->name, \str_replace(':', ' ', $component_id));
      $build = $this->buildPlaceholderCardWithPreview($component->metadata->name, $vals, $component_preview_url, $keywords, $thumbnail);
      $components[] = $build;
    }

    return $this->buildDraggables($builder_id, $components, 'mosaic');
  }

  /**
   * Get filtered and grouped definitions.
   *
   * @return array
   *   The definitions filtered and grouped.
   */
  private function getDefinitions(): array {
    $definitions = $this->sdcManager->getSortedDefinitions();
    $configuration = $this->getConfiguration();

    $filtered_definitions = $grouped_definitions = [];

    foreach ($definitions as $id => $definition) {
      // Excluded no ui components unless forced.
      if (isset($definition['noUi']) && $definition['noUi'] === TRUE) {
        if ((bool) $configuration['include_no_ui'] !== TRUE) {
          continue;
        }
      }

      // Filter components according to configuration.
      // Components with stable or undefined status will always be available.
      $allowed_status = \array_merge($configuration['status'], ['stable']);

      if (isset($definition['status']) && !\in_array($definition['status'], $allowed_status, TRUE)) {
        continue;
      }
      $allowed_status = \array_merge($configuration['status'], ['stable']);

      if (isset($definition['provider']) && !\in_array($definition['provider'], $configuration['providers'], TRUE)) {
        continue;
      }
      // Because of multiple themes, annotated name contains the theme name.
      // We do not need it.
      $definition['annotated_name'] = $definition['name'] ?? 'n/a';
      $filtered_definitions[$id] = $definition;
      $grouped_definitions[(string) $definition['category']][$id] = $definition;
    }

    // Order list ignoring starting '(' that is used for components names that
    // are sub components.
    \uasort($filtered_definitions, static function ($a, $b) {
      $nameA = \ltrim($a['name'] ?? $a['label'], '(');

      return \strnatcasecmp($nameA, \ltrim($b['name'] ?? $b['label'], '('));
    });

    return [
      'grouped' => $grouped_definitions,
      'filtered' => $filtered_definitions,
    ];
  }

}
