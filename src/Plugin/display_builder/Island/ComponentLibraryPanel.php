<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\ComponentLibraryDefinitionHelper;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandConfigurationFormInterface;
use Drupal\display_builder\IslandConfigurationFormTrait;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Component library island plugin implementation.
 */
#[Island(
  id: 'component_library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Components'),
  description: new TranslatableMarkup('List of available components.'),
  type: IslandType::Library,
)]
class ComponentLibraryPanel extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  /**
   * The theme manager service.
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * The theme extension list service.
   */
  protected ThemeExtensionList $themeList;

  /**
   * The module extension list service.
   */
  protected ModuleExtensionList $moduleList;

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
   * The source data for components.
   *
   * @var array
   *   The source data already prepared.
   */
  private array $sourcesData = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeManager = $container->get('theme.manager');
    $instance->themeList = $container->get('extension.list.theme');
    $instance->moduleList = $container->get('extension.list.module');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'exclude' => [],
      'exclude_id' => '',
      'component_status' => [
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
    $components = $this->sdcManager->getDefinitions();

    $form['exclude'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude providers'),
      '#options' => $this->getProvidersOptions($components, $this->t('component'), $this->t('components')),
      '#default_value' => $configuration['exclude'],
    ];

    $form['exclude_id'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude by id'),
      '#description' => $this->t('Provide a space separated list of components id to exclude, must be prefixed by provider. Example: "ui_suite_bootstrap:card_body<br>ui_suite_bootstrap:table_cell".'),
      '#default_value' => $configuration['exclude_id'],
    ];

    // @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/assets/schemas/v1/metadata.schema.json#L217
    $form['component_status'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed status'),
      '#options' => [
        'experimental' => $this->t('Experimental'),
        'deprecated' => $this->t('Deprecated'),
        'obsolete' => $this->t('Obsolete'),
      ],
      '#description' => $this->t('Components with stable or undefined status will always be available.'),
      '#default_value' => $configuration['component_status'],
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

    $summary[] = $this->t('Excluded providers: @exclude', [
      '@exclude' => ($exclude = \array_filter($configuration['exclude'] ?? [])) ? \implode(', ', $exclude) : $this->t('None'),
    ]);

    if (\strlen($configuration['exclude_id'] ?? '') > 5) {
      $value = \preg_split('/\s+/', \trim($configuration['exclude_id'] ?? ''));

      if ($value === FALSE) {
        $summary[] = $this->t('Component(s) excluded');
      }
      else {
        $num = \count($value);
        $summary[] = $this->formatPlural($num, '@count component excluded', '@count components excluded');
      }
    }

    $summary[] = $this->t('Allowed status: @status', [
      '@status' => \implode(', ', \array_filter(\array_unique(\array_merge(['stable', 'undefined'], $configuration['component_status'] ?? []))) ?: [$this->t('stable, undefined')]),
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
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    // Run a single time and saved as properties to avoid repeating processing
    // in ::getComponentsMosaic(), ::getComponentsVariants() and
    // ::getComponentsGrouped().
    $configuration = $this->getConfiguration();

    $componentDefinitions = new ComponentLibraryDefinitionHelper($this->sdcManager, $this->sourceManager);
    $definitions = $componentDefinitions->getDefinitions($configuration);

    $this->definitionsFiltered = $definitions['filtered'] ?? [];
    $this->definitionsGrouped = $definitions['grouped'] ?? [];
    $this->sourcesData = $definitions['sources'] ?? [];

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
        'tabs' => (\count($panes) > 1) ? $this->buildTabs('db-' . $builder_id . '-components-tabs', $tabs) : [],
        'content' => $content,
      ],
    ];
  }

  /**
   * Get all providers.
   *
   * @param array $definitions
   *   Plugin definitions.
   *
   * @return array
   *   Drupal extension definitions, keyed by extension ID
   */
  protected function getProviders(array $definitions): array {
    $themes = $this->themeList->getAllInstalledInfo();
    $modules = $this->moduleList->getAllInstalledInfo();
    $providers = [];

    foreach ($definitions as $definition) {
      $provider_id = $definition['provider'];

      $provider = $themes[$provider_id] ?? $modules[$provider_id] ?? NULL;

      if (!$provider) {
        continue;
      }
      $provider['count'] = isset($providers[$provider_id]) ? ($providers[$provider_id]['count']) + 1 : 1;
      $providers[$provider_id] = $provider;
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
          'source' => $this->sourcesData[$component_id],
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

    foreach ($this->definitionsFiltered as $component_id => $definition) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $definition['annotated_name'],
        '#attributes' => [
          'data-search-section' => $definition['machineName'],
        ],
      ];

      $data = [
        'source_id' => 'component',
        'source' => $this->sourcesData[$component_id],
      ];

      if (!isset($definition['variants'])) {
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);
        $build_variant = $this->buildPlaceholderButtonWithPreview($builder_id, $this->t('Default'), $data, $component_preview_url, $keywords);
        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
        // Label is used by default to set drawer title when dragging. It is set
        // on RenderableBuilderTrait::buildPlaceholderButton(), so here we need
        // to override it to have the proper label and not the variant name.
        // @see assets/js/db_drawer.js
        // @see src/RenderableBuilderTrait::buildPlaceholderButton()
        $build_variant['#attributes']['data-node-title'] = $definition['label'];

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
        // Label is used by default to set drawer title when dragging. It is set
        // on RenderableBuilderTrait::buildPlaceholderButton(), so here we need
        // to override it to have the proper label and not the variant name.
        // @see assets/js/db_drawer.js
        // @see src/RenderableBuilderTrait::buildPlaceholderButton()
        $build_variant['#attributes']['data-node-title'] = $definition['label'];

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

    foreach (\array_keys($this->definitionsFiltered) as $component_id) {
      $component_id = (string) $component_id;
      $component = $this->sdcManager->find($component_id);
      $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);

      $vals = [
        'source_id' => 'component',
        'source' => $this->sourcesData[$component_id],
      ];
      $thumbnail = $component->metadata->getThumbnailPath();

      // Used for search filter.
      $keywords = \sprintf('%s %s', $component->metadata->name, \str_replace(':', ' ', $component_id));
      $build = $this->buildPlaceholderCardWithPreview($component->metadata->name, $vals, $component_preview_url, $keywords, $thumbnail);
      // Label is used by default to set drawer title when dragging. It is set
      // on RenderableBuilderTrait::buildPlaceholderButton(), so here we need
      // to override it to have the proper label and not the variant name.
      // @see assets/js/db_drawer.js
      // @see src/RenderableBuilderTrait::buildPlaceholderButton()
      $build['#attributes']['data-node-title'] = $component->metadata->name;
      $components[] = $build;
    }

    return $this->buildDraggables($builder_id, $components, 'mosaic');
  }

  /**
   * Get providers options for select input.
   *
   * @param array $definitions
   *   Plugin definitions.
   * @param string|TranslatableMarkup $singular
   *   Singular label of the plugins.
   * @param string|TranslatableMarkup $plural
   *   Plural label of the plugins.
   *
   * @return array
   *   An associative array with extension ID as key and extension description
   *   as value.
   */
  private function getProvidersOptions(array $definitions, string|TranslatableMarkup $singular = 'definition', string|TranslatableMarkup $plural = 'definitions'): array {
    $options = [];

    foreach ($this->getProviders($definitions) as $provider_id => $provider) {
      $params = [
        '@name' => $provider['name'],
        '@type' => $provider['type'],
        '@count' => $provider['count'],
        '@singular' => $singular,
        '@plural' => $plural,
      ];
      $options[$provider_id] = $this->formatPlural($provider['count'], '@name (@type, @count @singular)', '@name (@type, @count @plural)', $params);
    }

    return $options;
  }

}
