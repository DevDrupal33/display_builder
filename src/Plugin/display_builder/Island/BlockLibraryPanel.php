<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\SourceWithChoicesInterface;
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
    // 'block',
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
   * The sources.
   */
  protected ?array $sources = NULL;

  /**
   * The choices from all sources.
   */
  protected ?array $choices = NULL;

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
    $categories = $this->getGroupedChoices();
    $build = [];

    foreach ($categories as $category_data) {
      if (!empty($category_data['label'])) {
        $build[] = [
          [
            '#type' => 'html_tag',
            '#tag' => 'h4',
            // We hide the group titles on search.
            '#attributes' => ['class' => 'db-filter-hide-on-search'],
            '#value' => $category_data['label'],
          ],
        ];
      }
      $category_choices = $category_data['choices'];

      foreach ($category_choices as $choice) {
        $build[] = $this->buildPlaceholderButton(
          $choice['label'],
          $choice['data'] ?? [],
          $choice['keywords'] ?? ''
        );
      }
    }

    return $this->buildDraggables($builder_id, $build);
  }

  /**
   * Get the group label for a choice.
   *
   * @param array $choice
   *   The choice to get the group for.
   * @param array $source_definition
   *   The source definition to use for the group.
   *
   * @return string|null
   *   The group label for the choice.
   */
  public function getChoiceGroup(array &$choice, array &$source_definition): ?string {
    $group = $source_definition['label'] ?? '';

    switch ($source_definition['id']) {
      case 'block':
        $block_id = $choice['original_id'] ?? '';

        if (\str_starts_with($block_id, 'views_block:') && $choice['group']) {
          $group = $choice['group'];
        }
        elseif (\str_starts_with($block_id, 'system_menu_block:') && $choice['group']) {
          $group = $choice['group'];
        }
        else {
          $group = $this->t('Others');
        }
        break;

      case 'entity_reference':
        $group = $this->t('Referenced entities');

        break;

      case 'entity_field':
        $group = $this->t('Fields');

        break;

      default:
        break;
    }

    return ($group instanceof MarkupInterface) ? (string) $group : $group;
  }

  /**
   * Get the choices grouped by category.
   *
   * @return array
   *   An array of grouped choices.
   */
  protected function getGroupedChoices(): array {
    $choices = $this->getChoices();
    $categories = [];

    foreach ($choices as $choice) {
      $category = $choice['group'] ?? '';

      if ($category instanceof MarkupInterface) {
        $category = (string) $category;
      }

      if (!isset($categories[$category])) {
        $categories[$category] = [
          'label' => $category,
          'metadata' => $choice,
          'choices' => [],
        ];
      }
      $categories[$category]['choices'][] = $choice;
    }
    $this->sortGroupedChoices($categories);

    return $categories;
  }

  /**
   * Sorts the grouped choices.
   *
   * This method sorts the categories by their labels, placing empty category
   * first, views blocks are sorted to the end of the list.
   *
   * @param array $categories
   *   The categories to sort, passed by reference.
   */
  protected function sortGroupedChoices(array &$categories): void {
    // Sort categories : empty first, views at the end.
    \usort($categories, static function ($a, $b) {
      if (empty($a['label'])) {
        return -1;
      }

      if (empty($b['label'])) {
        return 1;
      }
      $source_id_a = $a['metadata']['data']['source_id'] ?? '';
      $source_id_b = $b['metadata']['data']['source_id'] ?? '';

      if (($source_id_a === 'block') && ($source_id_b !== 'block')) {
        return 1;
      }

      if (($source_id_b === 'block') && ($source_id_a !== 'block')) {
        return -1;
      }

      return \strnatcmp($a['label'], $b['label']);
    });
  }

  /**
   * Returns all possible sources.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *
   * @return array<string, array>
   *   An array of sources.
   */
  protected function getSources(): array {
    if ($this->sources === NULL) {
      $definitions = $this->sourceManager->getDefinitionsForPropType('slot', $this->configuration['contexts'] ?? []);
      $slot_definition = ['ui_patterns' => ['type_definition' => $this->sourceManager->getSlotPropType()]];

      foreach ($definitions as $source_id => $definition) {
        if (\in_array($source_id, self::HIDE_SOURCE, TRUE)) {
          continue;
        }
        $source = $this->sourceManager->createInstance($source_id,
          SourcePluginBase::buildConfiguration('slot', $slot_definition, ['source' => []], $this->configuration['contexts'] ?? [])
        );
        $this->sources[$source_id] = [
          'definition' => $definition,
          'source' => $source,
        ];

        if ($source instanceof SourceWithChoicesInterface) {
          $this->sources[$source_id]['choices'] = $source->getChoices();
        }
      }
    }

    return $this->sources;
  }

  /**
   * Validate a choice against the source definition and allowed providers.
   *
   * @param array $choice
   *   The choice to validate.
   * @param array $source_definition
   *   The source definition.
   * @param array|bool $allowed_providers
   *   The allowed providers, or TRUE to allow all.
   *
   * @return bool
   *   Whether the choice is valid or not.
   */
  protected function isChoiceValid(array &$choice, array &$source_definition, $allowed_providers): bool {
    $provider = $choice['provider'] ?? '';

    if ($provider) {
      if (!$allowed_providers) {
        return FALSE;
      }

      if (\is_array($allowed_providers) && (\in_array($provider, self::PROVIDER_EXCLUDE, TRUE) || !\in_array($provider, $allowed_providers, TRUE))) {
        return FALSE;
      }
    }

    if ($source_definition['id'] === 'block') {
      $block_id = $choice['original_id'] ?? '';

      if ($block_id && \in_array($block_id, self::HIDE_BLOCK, TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Get the choices from all sources.
   *
   * @return array
   *   An array of choices.
   */
  protected function getChoices(): array {
    if ($this->choices === NULL) {
      $this->choices = [];
      $configuration = $this->getConfiguration();
      $allowed_providers = $configuration['providers'] ?? TRUE;
      $sources = $this->getSources();

      foreach ($sources as $source_id => $source_data) {
        $definition = $source_data['definition'];
        $source = $source_data['source'];

        if (!isset($source_data['choices'])) {
          $this->choices[] = [
            'label' => $definition['label'] ?? $source_id,
            'data' => ['source_id' => $source_id],
            'keywords' => \sprintf('%s %s %s', $definition['id'], $definition['label'] ?? $source_id, $definition['description'] ?? ''),
          ];

          continue;
        }
        $choices = $source_data['choices'];

        foreach ($choices as $choice_id => $choice) {
          if (!$this->isChoiceValid($choice, $definition, $allowed_providers)) {
            continue;
          }
          $choice_label = $choice['label'] ?? $choice_id;
          $group = $this->getChoiceGroup($choice, $definition);
          $this->choices[] = [
            'group' => $group,
            'label' => $choice_label,
            'data' => [
              'source_id' => $source_id,
              'source' => $source->getChoiceSettings($choice_id),
            ],
            'keywords' => \sprintf('%s %s %s %s', $definition['id'], $choice_label, $definition['description'] ?? '', $choice_id),
          ];
        }
      }
    }

    return $this->choices;
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
    $sources = $this->getSources();
    $providers = [];
    $modules = $this->modules->getAllInstalledInfo();

    foreach ($sources as $source_data) {
      if (!isset($source_data['choices'])) {
        continue;
      }
      $choices = $source_data['choices'];

      foreach ($choices as $choice) {
        $provider = $choice['provider'] ?? '';

        if (!$provider || \in_array($provider, self::PROVIDER_EXCLUDE, TRUE)) {
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
