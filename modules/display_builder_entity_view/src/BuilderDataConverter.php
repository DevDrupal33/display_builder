<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Converter data between Layout Builder and Display Builder.
 */
class BuilderDataConverter {

  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Convert "Manage display" formatters to UI Patterns sources.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle.
   * @param array $fields
   *   Configuration of activated fields.
   *
   * @return array
   *   List of UI Patterns sources.
   */
  public function convertFromManageDisplay(string $entity_type, string $bundle, array $fields): array {
    $sources = [];
    \uasort($fields, [SortArray::class, 'sortByWeightElement']);

    $definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

    foreach ($fields as $field_id => $field) {
      if (!isset($field['type'])) {
        // Probably an extra field.
        $sources[] = $this->convertExtraField($field_id);

        // @todo Do we need to check if it is really an extra field?
        continue;
      }

      if (isset($definitions[$field_id]) && !$definitions[$field_id]->isDisplayConfigurable('view')) {
        // Hidden from Manage Display.
        continue;
      }
      $sources[] = $this->convertSingleField($entity_type, $bundle, $field_id, $field);
    }

    return $sources;
  }

  /**
   * Convert from Layout Builder.
   *
   * @param array $sections
   *   A list of layout builder section, so the root of Layout Builder data.
   *
   * @return array
   *   A list of UI Patterns sources.
   */
  public function convertFromLayoutBuilder(array $sections): array {
    $sources = [];

    foreach ($sections as $section) {
      $deriver = $section->getLayout()->getPluginDefinition()->getDeriver();

      if ($deriver === 'Drupal\ui_patterns_layouts\Plugin\Derivative\ComponentLayout') {
        $sources[] = $this->convertUiPatternsLayout($section);

        continue;
      }
      $sources = \array_merge($sources, $this->convertLayout($section));
    }

    return $sources;
  }

  /**
   * Convert field formatter plugin data to a source.
   *
   * Used for conversion from both Manage Display and Layout Builder.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle.
   * @param string $field
   *   Field name.
   * @param array $data
   *   Field formatter data.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertSingleField(string $entity_type, string $bundle, string $field, array $data): array {
    $derivable_context = \implode(':', [$entity_type, $bundle, $field]);
    $source = [
      'source_id' => 'field_formatter:' . $derivable_context,
      'source' => $data,
    ];

    return [
      'source_id' => 'entity_field',
      'source' => [
        'derivable_context' => 'field:' . $derivable_context,
        'field:' . $derivable_context => [
          'value' => [
            'sources' => [$source],
          ],
        ],
      ],
    ];
  }

  /**
   * Convert an extra field.
   *
   * @param string $field_name
   *   The machine name of the extra field.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertExtraField(string $field_name): array {
    return [
      'source_id' => 'extra_field',
      'source' => [
        'field' => $field_name,
      ],
    ];
  }

  /**
   * Convert UI Patterns layout plugin.
   *
   * @param \Drupal\layout_builder\Section $section
   *   A single layout builder section.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertUiPatternsLayout(Section $section): array {
    $slots = [];
    $components = $section->getComponents();

    foreach ($components as $component) {
      $source = $this->convertLayoutBuilderComponent($component);

      if ($source) {
        $source = $this->extractUiStylesData($component, $source);
        $slots[$component->getRegion()]['sources'][] = $source;
      }
    }

    $data = [
      'source_id' => 'component',
      'source' => [
        'component' => $section->getLayoutSettings()['ui_patterns'],
      ],
    ];
    // Sometimes, this value is null, so let's override it.
    $data['source']['component']['component_id'] = \str_replace('ui_patterns:', '', $section->getLayoutId());

    foreach ($section->getThirdPartyProviders() ?: [] as $provider_id) {
      // In Layout builder, ThirdPartyProviders are Drupal modules. In Display
      // Builder, they are Island plugins. So, 'ui_styles' become 'styles'. We
      // are not calling the island 'ui_styles' in order to be ready when the
      // API will land in Core. See https://www.drupal.org/i/3517033.
      if ($provider_id === 'ui_styles') {
        $data['third_party_settings']['styles'] = $section->getThirdPartySettings($provider_id);

        continue;
      }
      $data['third_party_settings'][$provider_id] = $section->getThirdPartySettings($provider_id);
    }
    $data = $this->moveUiStylesAttributesSource($data);

    if ($slots) {
      $data['source']['component']['slots'] = $slots;
    }

    return $data;
  }

  /**
   * Move data from UI Style's attribute source to 3rd party settings.
   *
   * @param array $data
   *   A UI Patterns source data.
   *
   * @return array
   *   The same UI Patterns source data, maybe altered.
   */
  protected function moveUiStylesAttributesSource(array $data): array {
    if ($data['source']['component']['props']['attributes']['source_id'] !== 'ui_styles_attributes') {
      return $data;
    }
    // We keep this order because it is the priority order in UI styles.
    $styles_1 = [
      'selected' => $data['source']['component']['props']['attributes']['source']['styles']['selected'],
      'extra' => $data['source']['component']['props']['attributes']['source']['extra'],
    ];
    $styles_2 = $data['third_party_settings']['styles'] ?? [];
    $data['third_party_settings']['styles'] = NestedArray::mergeDeep($styles_1, $styles_2);
    $data['source']['component']['props']['attributes'] = [];

    return $data;
  }

  /**
   * Convert regular layout plugin.
   *
   * We don't really convert the layout here, we extract the blocks and put
   * them as a flat list where the layout is.
   *
   * @todo better layout support https://www.drupal.org/project/display_builder/issues/3531521
   *
   * @param \Drupal\layout_builder\Section $section
   *   A single layout builder section.
   *
   * @return array
   *   A list of UI Patterns source.
   */
  protected function convertLayout(Section $section): array {
    $sources = [];
    $components = $section->getComponents();

    foreach ($components as $component) {
      $source = $this->convertLayoutBuilderComponent($component);
      $source = $this->extractUiStylesData($component, $source);
      $sources[] = $source;
    }

    return $sources;
  }

  /**
   * Extract UI Styles data.
   *
   * In Display Builder, we don't render through ThemeManager::render() so we
   * don't load block.html.twig and we don't have block wrapper and block title
   * attributes. So, let's ignore title styles and let's merge wrapper styles
   * with block styles (which keep priority).
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   A single layout builder component.
   * @param array $source
   *   The already converted UI Patterns source.
   *
   * @return array
   *   The altered UI Patterns source.
   */
  protected function extractUiStylesData(SectionComponent $component, array $source): array {
    $additional = $component->toArray()['additional'] ?? [];

    $styles = \array_unique(\array_merge($additional['ui_styles_wrapper'] ?? [], $additional['ui_styles'] ?? []));

    if ($styles) {
      $source['third_party_settings']['styles']['selected'] = $styles;
    }

    $extra = \trim(($additional['ui_styles_wrapper_extra'] ?? '') . ' ' . ($additional['ui_styles_extra'] ?? ''));

    if ($extra) {
      $source['third_party_settings']['styles']['extra'] = $extra;
    }

    return $source;
  }

  /**
   * Convert layout builder component.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *   A single layout builder component.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertLayoutBuilderComponent(SectionComponent $component): array {
    /** @var \Drupal\Core\Block\BlockPluginInterface $block */
    $block = $component->getPlugin();
    $definition = $block->getPluginDefinition();

    $class = $definition instanceof PluginDefinitionInterface ? $definition->getClass() : $definition['class'];

    if ($class === 'Drupal\layout_builder\Plugin\Block\FieldBlock') {
      return $this->convertFieldBlock($block);
    }

    $provider = $definition instanceof PluginDefinitionInterface ? $definition->getProvider() : $definition['provider'];

    if ($provider === 'ui_patterns_blocks') {
      return $this->convertUiPatternsBlock($block);
    }

    $id = $definition instanceof PluginDefinitionInterface ? $definition->id() : $definition['id'];

    if ($id === 'extra_field_block') {
      return $this->convertExtraFieldBlock($block);
    }

    return $this->convertBlock($block);
  }

  /**
   * Convert a Layout Builder field block.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   A block plugin.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertFieldBlock(BlockPluginInterface $block): array {
    $config = $block->getConfiguration();
    [, $entity_type, $bundle, $field_name] = \explode(':', $config['id']);

    return $this->convertSingleField($entity_type, $bundle, $field_name, $config['formatter']);
  }

  /**
   * Convert an extra field block.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   A block plugin.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertExtraFieldBlock(BlockPluginInterface $block): array {
    [,,, $field_name] = \explode(':', $block->getPluginId());

    return $this->convertExtraField($field_name);
  }

  /**
   * Convert a UI Patterns block plugin.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   A block plugin.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertUiPatternsBlock(BlockPluginInterface $block): array {
    $config = $block->getConfiguration();

    // Sometimes, this value is null, so let's override it.
    if (!isset($config['ui_patterns']['component_id'])) {
      // See: \Drupal\ui_patterns_blocks\Plugin\Block\ComponentBlock.
      $config['ui_patterns']['component_id'] = \str_replace('ui_patterns:', '', $config['id']);
      // See: \Drupal\ui_patterns_blocks\Plugin\Block\EntityComponentBlock.
      $config['ui_patterns']['component_id'] = \str_replace('ui_patterns_entity:', '', $config['id']);
    }

    return [
      'source_id' => 'component',
      'source' => [
        'component' => $config['ui_patterns'],
      ],
    ];
  }

  /**
   * Convert a generic block plugin.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   A block plugin.
   *
   * @return array
   *   A single UI Patterns source.
   */
  protected function convertBlock(BlockPluginInterface $block): array {
    $block_id = $block->getPluginId();
    $blockConfiguration = $this->updateContextMapping($block->getConfiguration());

    return [
      'source_id' => 'block',
      'source' => [
        'plugin_id' => $block_id,
        $block_id => $blockConfiguration,
      ],
    ];
  }

  /**
   * Update plugin configuration context mapping.
   *
   * @param array $configuration
   *   A configuration array from a plugin.
   *
   * @return array
   *   The updated configuration.
   */
  protected function updateContextMapping(array $configuration): array {
    if (isset($configuration['context_mapping']) && \is_array($configuration['context_mapping'])) {
      foreach ($configuration['context_mapping'] as $key => $value) {
        if ($value === 'layout_builder.entity') {
          $configuration['context_mapping'][$key] = 'entity';
        }
      }
    }

    return $configuration;
  }

}
