<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\BlockLibrarySourceHelper;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandConfigurationFormInterface;
use Drupal\display_builder\IslandConfigurationFormTrait;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
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
    // Used only for imports from Manage Display and Layout Builder.
    'extra_field',
    // No Wysiwyg from our UI until #3561474 is fixed.
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['exclude'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude modules'),
      '#options' => $this->getProvidersOptions(),
      '#default_value' => $configuration['exclude'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();

    return [
      $this->t('Excluded modules: @exclude', [
        '@exclude' => \implode(', ', \array_filter($configuration['exclude'] ?? []) ?: [$this->t('None')]),
      ]),
    ];
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
    $categories = BlockLibrarySourceHelper::getGroupedChoices(
      $this->getSources(),
      $exclude_providers,
    );

    $build = [];

    foreach ($categories as $category_data) {
      $build[] = $this->buildCategorySection($category_data, $builder_id);
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
   *
   * @return array
   *   The render array for the category section.
   */
  private function buildCategorySection(array $category_data, string $builder_id): array {
    $section = [];

    if (!empty($category_data['label'])) {
      $section[] = [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#attributes' => ['class' => 'db-filter-hide-on-search'],
        '#value' => $category_data['label'],
      ];
    }

    foreach ($category_data['choices'] as $choice) {
      $section[] = $choice['preview']
        ? $this->buildPlaceholderButtonWithPreview($builder_id, $choice['label'], $choice['data'] ?? [], $choice['preview'], $choice['keywords'] ?? '')
        : $this->buildPlaceholderButton($choice['label'], $choice['data'] ?? [], $choice['keywords'] ?? '');
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
