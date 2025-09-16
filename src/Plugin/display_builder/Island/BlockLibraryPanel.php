<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandConfigurationFormInterface;
use Drupal\display_builder\IslandConfigurationFormTrait;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\ui_patterns\SourceWithChoicesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class BlockLibraryPanel extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  private const HIDE_BLOCK = [
    'help_block',
    'system_messages_block',
    'htmx_loader',
    'broken',
    'system_main_block',
    'page_title_block',
  ];

  private const HIDE_SOURCE = [
    'component',
    // Used only for imports from Manage Display and Layout Builder.
    'extra_field',
  ];

  private const HIDE_PROVIDER = ['ui_patterns_blocks'];

  /**
   * The sources.
   */
  protected ?array $sources = NULL;

  /**
   * The choices from all sources.
   */
  protected ?array $choices = NULL;

  /**
   * The module list extension service.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * The UI Patterns source plugin manager.
   */
  protected SourcePluginManager $sourceManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleList = $container->get('extension.list.module');
    $instance->sourceManager = $container->get('plugin.manager.ui_patterns_source');

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
   * {@inheritdoc}
   */
  public function label(): string {
    return 'Blocks';
  }

  /**
   * Get the group label for a choice.
   *
   * @param array $choice
   *   The choice to get the group for.
   * @param array $source_definition
   *   The source definition to use for the group.
   *
   * @return string
   *   The group label for the choice.
   */
  private static function getChoiceGroupLabel(array &$choice, array &$source_definition): string {
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
          $group = new TranslatableMarkup('Others');
        }

        break;

      case 'entity_reference':
        $group = new TranslatableMarkup('Referenced entities');

        break;

      case 'entity_field':
        $group = new TranslatableMarkup('Fields');

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
  private function getGroupedChoices(): array {
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
    self::sortGroupedChoices($categories);

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
  private static function sortGroupedChoices(array &$categories): void {
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
  private function getSources(): array {
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
   * @param array $excluded_providers
   *   The excluded providers.
   *
   * @return bool
   *   Whether the choice is valid or not.
   */
  private function isChoiceValid(array &$choice, array &$source_definition, array $excluded_providers = []): bool {
    $provider = $choice['provider'] ?? '';

    if ($provider) {
      if (\in_array($provider, self::HIDE_PROVIDER, TRUE) && \in_array($provider, $excluded_providers, TRUE)) {
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
  private function getChoices(): array {
    if ($this->choices !== NULL) {
      return $this->choices;
    }

    $this->choices = [];

    $configuration = $this->getConfiguration();
    $excluded_providers = $configuration['exclude'] ?? [];
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
        if (!$this->isChoiceValid($choice, $definition, $excluded_providers)) {
          continue;
        }
        $choice_label = $choice['label'] ?? $choice_id;
        $group_label = self::getChoiceGroupLabel($choice, $definition);
        $this->choices[] = [
          'group' => $group_label,
          'label' => $choice_label,
          'data' => [
            'source_id' => $source_id,
            'source' => $source->getChoiceSettings($choice_id),
          ],
          'keywords' => \sprintf('%s %s %s %s', $definition['id'], $choice_label, $definition['description'] ?? '', $choice_id),
        ];
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
