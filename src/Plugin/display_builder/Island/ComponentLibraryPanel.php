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
use Drupal\display_builder\ComponentLibraryDefinitions;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;
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
   * The component library definitions service.
   */
  protected ComponentLibraryDefinitions $componentDefinitions;

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
    $instance->componentDefinitions = $container->get('display_builder.component_library_definitions');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'exclude' => [],
      'exclude_id' => '',
      'component_status' => [],
      'include_no_ui' => FALSE,
      'show' => 'grouped',
      'preview' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();
    $components = $this->sdcManager->getDefinitions();

    // Compatibility layer with previous config.
    // @todo Remove and replace by config update on next release.
    if (!isset($configuration['show'])) {
      $configuration['show'] = $configuration['show_mosaic'] ? 'mosaic' : 'grouped';
      $configuration['show'] = $configuration['show_variants'] ? 'variants' : $configuration['show'];
      $configuration['show'] = $configuration['show_grouped'] ? 'grouped' : $configuration['show'];
    }

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display'),
    ];

    $form['display']['show'] = [
      '#type' => 'select',
      '#title' => $this->t('Display components as'),
      '#default_value' => $configuration['show'],
      '#options' => [
        'grouped' => $this->t('grouped'),
        'variants' => $this->t('with variants'),
        'flat' => $this->t('flat list'),
        'mosaic' => $this->t('thumbnails mosaic'),
      ],
    ];

    $form['display']['preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable preview on hover'),
      '#description' => $this->t('Enable or disable the preview of components when hovering over them.'),
      '#default_value' => $configuration['preview'],
    ];

    $form['configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configuration'),
    ];

    $form['configuration']['exclude'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude providers'),
      '#description' => $this->t('Exclude components from specific providers (modules or themes).'),
      '#options' => $this->getProvidersOptions($components, $this->t('component'), $this->t('components')),
      '#default_value' => $configuration['exclude'],
    ];

    $form['configuration']['exclude_id'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude by id'),
      '#description' => $this->t('Provide a list of components id to exclude, one by line, must be prefixed by provider. Example: ui_suite_bootstrap:card_body<br>ui_suite_bootstrap:table_cell.'),
      '#default_value' => $configuration['exclude_id'],
    ];

    // @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/assets/schemas/v1/metadata.schema.json#L239
    $form['configuration']['component_status'] = [
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

    // @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/assets/schemas/v1/metadata.schema.json#L250
    // @todo remove 11.3 reference when end of support is reached.
    $form['configuration']['include_no_ui'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include non UI'),
      '#description' => $this->t('Components with `no ui` flag are meant for internal use only. Force to include them. Drupal 11.3+ only.'),
      '#default_value' => $configuration['include_no_ui'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    $summary = [];

    $summary[] = $this->t('Components displayed as: <em>@list</em>', [
      '@list' => match ($configuration['show']) {
        'variants' => $this->t('variants'),
        'mosaic' => $this->t('mosaic'),
        'flat' => $this->t('flat'),
        default => $this->t('grouped'),
      },
    ]);

    if ($configuration['preview']) {
      $summary[] = $this->t('Preview on hover enabled');
    }

    $summary[] = $this->t('Excluded providers: @exclude', [
      '@exclude' => ($exclude = \array_filter($configuration['exclude'] ?? [])) ? \implode(', ', $exclude) : $this->t('None'),
    ]);

    if (\strlen($configuration['exclude_id'] ?? '') > 5) {
      $value = \preg_split('/\r\n|\r|\n|\s+/', \trim($configuration['exclude_id'] ?? ''));

      if ($value === FALSE) {
        $summary[] = $this->t('Component(s) excluded');
      }
      else {
        $summary[] = $this->formatPlural(\count($value), '@count component excluded', '@count components excluded by id');
      }
    }

    $summary[] = $this->t('Allowed status: @status', [
      '@status' => \implode(', ', \array_filter(\array_unique(\array_merge(['stable', 'undefined'], $configuration['component_status'] ?? []))) ?: [$this->t('stable, undefined')]),
    ]);

    $summary[] = $configuration['include_no_ui'] ? $this->t('Include `No UI` components') : '';

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

    $definitions = $this->componentDefinitions->getDefinitions($configuration);

    $this->definitionsFiltered = $definitions['filtered'] ?? [];
    $this->definitionsGrouped = $definitions['grouped'] ?? [];
    $this->sourcesData = $definitions['sources'] ?? [];

    $content = match ($configuration['show']) {
      'mosaic' => $this->getComponentsMosaic($builder_id, (bool) $configuration['preview']),
      'variants' => $this->getComponentsVariants($builder_id, (bool) $configuration['preview']),
      'flat' => $this->getComponentsFlat($builder_id, (bool) $configuration['preview']),
      default => $this->getComponentsGrouped($builder_id, (bool) $configuration['preview']),
    };

    return [
      '#type' => 'component',
      '#component' => 'display_builder:library_panel',
      '#slots' => [
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
   * @param bool $preview
   *   Whether to show preview on hover.
   *
   * @return array
   *   A renderable array containing the grouped components.
   */
  private function getComponentsGrouped(string $builder_id, bool $preview): array {
    $build = [];

    foreach ($this->definitionsGrouped as $group_name => $group) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $group_name,
        '#attributes' => [
          'class' => ['db-placeholder__group', 'db-filter-hide-on-search'],
        ],
      ];

      foreach ($group as $component_id => $definition) {
        $component_id = (string) $component_id;

        $data = [
          'source_id' => 'component',
          'source' => $this->sourcesData[$component_id],
        ];
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);

        if ($preview) {
          $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
          $build[] = $this->buildPlaceholderListWithPreview($builder_id, $definition['label'], $data, $component_preview_url, $keywords);
        }
        else {
          $build[] = $this->buildPlaceholderList($definition['label'], $data, $keywords);
        }
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Gets the flat components view.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param bool $preview
   *   Whether to show preview on hover.
   *
   * @return array
   *   A renderable array containing the grouped components.
   */
  private function getComponentsFlat(string $builder_id, bool $preview): array {
    $build = [];

    foreach ($this->definitionsFiltered as $component_id => $definition) {
      $component_id = (string) $component_id;

      $data = [
        'source_id' => 'component',
        'source' => $this->sourcesData[$component_id],
      ];
      // Used for search filter.
      $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);

      if ($preview) {
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
        $build[] = $this->buildPlaceholderListWithPreview($builder_id, $definition['label'], $data, $component_preview_url, $keywords);
      }
      else {
        $build[] = $this->buildPlaceholderList($definition['label'], $data, $keywords);
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Gets the components variants view.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param bool $preview
   *   Whether to show preview on hover.
   *
   * @return array
   *   A renderable array containing the variants placeholders.
   */
  private function getComponentsVariants(string $builder_id, bool $preview): array {
    $build = [];

    foreach ($this->definitionsFiltered as $component_id => $definition) {
      $build[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $definition['label'],
        '#attributes' => [
          'class' => ['db-placeholder__group'],
          'data-search-section' => $definition['machineName'],
        ],
      ];

      $data = [
        'source_id' => 'component',
        'source' => $this->sourcesData[$component_id],
      ];

      if (!isset($definition['variants'])) {
        // Used for search filter.
        $keywords = \sprintf('%s %s', $definition['label'], $definition['provider']);

        if ($preview) {
          $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
          $build_variant = $this->buildPlaceholderListWithPreview($builder_id, $this->t('Default'), $data, $component_preview_url, $keywords);
        }
        else {
          $build_variant = $this->buildPlaceholderList($this->t('Default'), $data, $keywords);
        }

        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
        $build_variant['#attributes']['data-node-title'] = $definition['label'];

        $build[] = $build_variant;

        continue;
      }

      foreach ($definition['variants'] ?? [] as $variant_id => $variant) {
        $params = ['component_id' => $component_id, 'variant_id' => $variant_id];
        $data['source']['component']['variant_id'] = [
          'source_id' => 'select',
          'source' => [
            'value' => $variant_id,
          ],
        ];
        // Used for search filter.
        $keywords = \sprintf('%s %s %s', $definition['label'], $variant['title'], $definition['provider']);

        if ($preview) {
          $component_preview_url = Url::fromRoute('display_builder.api_component_preview', $params);
          $build_variant = $this->buildPlaceholderListWithPreview($builder_id, $variant['title'], $data, $component_preview_url, $keywords);
        }
        else {
          $build_variant = $this->buildPlaceholderList($variant['title'], $data, $keywords);
        }

        $build_variant['#attributes']['data-filter-child'] = $definition['machineName'];
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
   * @param bool $preview
   *   Whether to show preview on hover.
   *
   * @return array
   *   A renderable array containing the mosaic view of components.
   */
  private function getComponentsMosaic(string $builder_id, bool $preview): array {
    $components = [];

    foreach (\array_keys($this->definitionsFiltered) as $component_id) {
      $component_id = (string) $component_id;
      $component = $this->sdcManager->find($component_id);

      $vals = [
        'source_id' => 'component',
        'source' => $this->sourcesData[$component_id],
      ];
      $thumbnail = $component->metadata->getThumbnailPath();

      // Used for search filter.
      $keywords = \sprintf('%s %s', $component->metadata->name, \str_replace(':', ' ', $component_id));

      if ($preview) {
        $component_preview_url = Url::fromRoute('display_builder.api_component_preview', ['component_id' => $component_id]);
        $build = $this->buildPlaceholderCardWithPreview($builder_id, $component->metadata->name, $vals, $component_preview_url, $keywords, $thumbnail);
      }
      else {
        $build = $this->buildPlaceholderCard($component->metadata->name, $vals, $keywords, $thumbnail);
      }

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
