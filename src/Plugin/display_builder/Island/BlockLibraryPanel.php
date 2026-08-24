<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\BlockLibrarySourceHelper;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block library island plugin implementation.
 */
#[Island(
  id: 'block_library',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Blocks'),
  description: new TranslatableMarkup('List of available blocks.'),
  type: IslandType::Library,
)]
class BlockLibraryPanel extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  private const HIDE_SOURCE = [
    'component',
    // Token is deprecated in UI Patterns; 'Textfield' allow token.
    'token',
    // Superseded by textarea until it works.
    'wysiwyg',
  ];

  private const HIDE_PROVIDER = ['ui_patterns_blocks'];

  /**
   * The sources.
   */
  protected array $sources = [];

  /**
   * The module extension list service.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleList = $container->get('extension.list.module');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'exclude' => [
        'devel',
        'htmx',
        'shortcut',
      ],
      'exclude_id' => '',
      'show' => 'grouped',
      'preview' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display'),
    ];

    $form['display']['show'] = [
      '#type' => 'select',
      '#title' => $this->t('Display blocks as'),
      '#default_value' => $configuration['show'],
      '#options' => [
        'grouped' => $this->t('grouped'),
        'flat' => $this->t('flat list'),
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
      '#title' => $this->t('Exclude from modules'),
      '#description' => $this->t('Select the modules which blocks have to be excluded from the block library.'),
      '#options' => $this->getProvidersOptions(),
      '#default_value' => $configuration['exclude'],
    ];

    $form['configuration']['exclude_id'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exclude by id'),
      '#description' => $this->t('Provide a list of id to exclude. One id by line, source id is used. Example: "help_block views_block:comments_recent-block_1 system_menu_block:account field:node:article:vid".'),
      '#default_value' => $configuration['exclude_id'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    $summary[] = $this->t('Blocks displayed as: <em>@list</em>', [
      '@list' => match ($configuration['show']) {
        'flat' => $this->t('flat'),
        default => $this->t('grouped'),
      },
    ]);

    if ($configuration['preview']) {
      $summary[] = $this->t('Preview on hover enabled');
    }

    $summary[] = $this->t('Excluded modules: @exclude', [
      '@exclude' => \implode(', ', \array_filter($configuration['exclude'] ?? []) ?: [$this->t('None')]),
    ]);

    if (\strlen($configuration['exclude_id'] ?? '') > 5) {
      $value = \preg_split('/\r\n|\r|\n/', \trim($configuration['exclude_id'] ?? ''));

      if ($value === FALSE) {
        $summary[] = $this->t('Block(s) excluded');
      }
      else {
        $summary[] = $this->formatPlural(\count($value), '@count block excluded', '@count blocks excluded by id');
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $builder_id = (string) $builder->id();
    $configuration = $this->getConfiguration();

    $exclude_providers = \array_merge(
      $configuration['exclude'] ?? [],
      self::HIDE_PROVIDER
    );
    $exclude_by_id = \preg_split('/\r\n|\r|\n/', \trim($configuration['exclude_id'] ?? '')) ?: [];

    $build = [];

    if ($configuration['show'] === 'grouped') {
      $categories = BlockLibrarySourceHelper::getGroupedChoices(
        $this->getSources(),
        $exclude_providers,
        $exclude_by_id,
        (bool) $configuration['preview'],
      );

      foreach ($categories as $category_data) {
        $build[] = $this->buildCategorySection($category_data, $builder_id, (bool) $configuration['preview']);
      }
    }
    else {
      $choices = BlockLibrarySourceHelper::getChoices(
        $this->getSources(),
        $exclude_providers,
        $exclude_by_id,
        (bool) $configuration['preview'],
      );

      foreach ($choices as $choice) {
        if ($configuration['preview']) {
          $build[] = $this->buildPlaceholderListWithPreview($builder_id, $choice['label'], $data, $choice['preview'], $choice['keywords']);
        }
        else {
          $build[] = $this->buildPlaceholderList($choice['label'], $data, $choice['keywords']);
        }
      }
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:library_panel',
      '#slots' => [
        'content' => $this->buildDraggables($builder_id, $build),
      ],
    ];
  }

  /**
   * Build a category section.
   *
   * @param array $category_data
   *   The category data.
   * @param string $builder_id
   *   The builder ID.
   * @param bool $preview
   *   Whether to show preview on hover.
   *
   * @return array
   *   The render array for the category section.
   */
  private function buildCategorySection(array $category_data, string $builder_id, bool $preview): array {
    $section = [];

    if (!empty($category_data['label'])) {
      $section[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#attributes' => [
          'class' => ['db-placeholder__group', 'db-filter-hide-on-search'],
        ],
        '#value' => $category_data['label'],
      ];
    }

    foreach ($category_data['choices'] as $choice) {
      $label = $choice['label'] ?? '-';

      if ($preview && $choice['preview']) {
        $section[] = $this->buildPlaceholderListWithPreview($builder_id, $label, $choice['data'] ?? [], $choice['preview'], $choice['keywords'] ?? '');
      }
      else {
        $section[] = $this->buildPlaceholderList($label, $choice['data'] ?? [], $choice['keywords'] ?? '');
      }
    }

    return $section;
  }

  /**
   * Returns all possible sources.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @return array<string, array>
   *   An array of sources.
   */
  private function getSources(): array {
    if (!empty($this->sources)) {
      return $this->sources;
    }

    $definitions = $this->sourceManager->getDefinitionsForPropType('slot', $this->configuration['contexts'] ?? []);
    $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

    foreach ($definitions as $source_id => $definition) {
      // A block is a source for slots but without slots.
      if (\is_a($definition['class'], SourceWithSlotsInterface::class, TRUE)) {
        continue;
      }

      if (\in_array($source_id, self::HIDE_SOURCE, TRUE)) {
        continue;
      }

      try {
        $source = $this->sourceManager->createInstance(
          $source_id,
          SourcePluginBase::buildConfiguration('slot', $slot_definition, ['source' => []], $this->configuration['contexts'] ?? [])
        );
      }
      catch (\Throwable $e) {
        $this->logger->error('Invalid source found: %message', ['%message' => $e->getMessage()]);

        continue;
      }

      $this->sources[$source_id] = [
        'definition' => $definition,
        'source' => $source,
      ];

      if ($source instanceof SourceWithChoicesInterface) {
        $this->sources[$source_id]['choices'] = $source->getChoices();
      }
    }

    return $this->sources;
  }

  /**
   * Get providers options for select input.
   *
   * @return array
   *   An associative array with module ID as key and module description as
   *   value.
   */
  private function getProvidersOptions(): array {
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
  private function getProviders(): array {
    $sources = $this->getSources();
    $providers = [];
    $modules = $this->moduleList->getAllInstalledInfo();

    foreach ($sources as $source_data) {
      if (!isset($source_data['choices'])) {
        continue;
      }
      $choices = $source_data['choices'];

      foreach ($choices as $choice) {
        $provider = $choice['provider'] ?? '';

        if (!$provider || \in_array($provider, self::HIDE_PROVIDER, TRUE)) {
          continue;
        }

        if (!isset($modules[$provider])) {
          // If the provider is not a module, skip it.
          continue;
        }

        if (!isset($providers[$provider])) {
          $providers[$provider] = $modules[$provider];
          $providers[$provider]['count'] = 0;
        }
        ++$providers[$provider]['count'];
      }
    }

    return $providers;
  }

}
