<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Form\PatternPresetForm;
use Drupal\display_builder\SlotSourceProxy;
use Drupal\display_builder_ui\PatternPresetListBuilder;
use Drupal\ui_patterns\SourceInterface;
use Drupal\ui_patterns\SourcePluginManager;

/**
 * Defines the Pattern preset entity type.
 */
#[ConfigEntityType(
  id: 'pattern_preset',
  label: new TranslatableMarkup('Pattern preset'),
  label_collection: new TranslatableMarkup('Pattern presets'),
  label_singular: new TranslatableMarkup('Pattern preset'),
  label_plural: new TranslatableMarkup('Pattern presets'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'theme' => 'theme',
    'description' => 'description',
    'group' => 'group',
    'sources' => 'sources',
    'weight' => 'weight',
  ],
  handlers: [
    'list_builder' => PatternPresetListBuilder::class,
    'form' => [
      'add' => PatternPresetForm::class,
      'edit' => PatternPresetForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/display-builder/preset/add',
    'delete-form' => '/admin/structure/display-builder/preset/{pattern_preset}/delete',
    'collection' => '/admin/structure/display-builder/preset',
  ],
  admin_permission: 'administer Pattern preset',
  constraints: [
    'ImmutableProperties' => [
      'id',
    ],
  ],
  config_export: [
    'id',
    'label',
    'description',
    'group',
    'sources',
    'weight',
  ],
)]
final class PatternPreset extends ConfigEntityBase implements PatternPresetInterface {

  /**
   * The preset ID.
   */
  protected string $id;

  /**
   * The preset label.
   */
  protected string $label = '';

  /**
   * The preset description.
   */
  protected string $description = '';

  /**
   * The preset group.
   */
  protected ?string $group = NULL;

  /**
   * The preset sources.
   */
  protected array $sources = [];

  /**
   * Weight to order the entity in lists.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The UI Patterns source plugin manager.
   */
  protected SourcePluginManager $sourcePluginManager;

  /**
   * Slot source proxy.
   */
  protected SlotSourceProxy $slotSourceProxy;

  /**
   * {@inheritdoc}
   */
  public function getGroup(): ?string {
    if (isset($this->group) && !empty($this->group)) {
      return $this->group;
    }

    if (isset($this->sources['source'], $this->sources['source_id'])) {
      $configuration = [
        'settings' => $this->sources['source'] ?? [],
      ];
      /** @var \Drupal\ui_patterns\SourceInterface $source */
      $source = $this->sourcePluginManager()->createInstance($this->sources['source_id'], $configuration);

      // We check if the UI Patterns source plugin has a getGroup() method.
      // At the moment, this method is not part of SourceInterface but is
      // anticipated for a future update to the UI Patterns API.
      // Using method_exists() ensures compatibility in the meantime.
      if (\method_exists($source, 'getGroup')) {
        $this->group = (string) $source->getGroup();
      }
      $this->save();
    }

    return !empty($this->group) ? $this->group : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    $contexts = [];
    $data = $this->getSources($contexts, FALSE);
    $data = $this->slotSourceProxy()->getLabelWithSummary($data);

    return $data['summary'] ?: $data['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(array $contexts = [], bool $fillInternalId = TRUE): array {
    $data = $this->get('sources') ?? [];

    if (isset($data[0]) && \count($data) === 1) {
      $data = \reset($data);
    }

    if (empty($data) || !isset($data['source_id'])) {
      return [];
    }

    if ($fillInternalId) {
      self::fillInternalId($data);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  public function getContexts(): array {
    // The root level is a single nestable source plugin.
    if (!isset($this->sources['source_id']) || !isset($this->sources['source'])) {
      return [];
    }

    // In case of a missing or malformed plugin (e.g. after config import),
    // return empty rather than crashing. Unexpected exceptions are logged.
    try {
      return $this->getContextFromSource($this->sources['source_id'], $this->sources['source']);
    }
    catch (PluginException) {
      // Plugin no longer exists or config is malformed — silently skip.
      return [];
    }
    catch (\Exception $e) {
      // Unexpected runtime error from plugin code: log and degrade gracefully.
      \Drupal::logger('display_builder')->warning(
        'PatternPreset @id: unexpected exception resolving contexts: @message',
        ['@id' => $this->id(), '@message' => $e->getMessage()],
      );

      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): self {
    parent::calculateDependencies();

    // The root level is a single nestable source plugin.
    if (!isset($this->sources['source_id'])) {
      return $this;
    }
    // This will automatically be done by parent::calculateDependencies() if we
    // implement EntityWithPluginCollectionInterface.
    $configuration = [
      'settings' => $this->sources['source'] ?? [],
    ];
    /** @var \Drupal\ui_patterns\SourceInterface $source */
    $source = $this->sourcePluginManager()->createInstance($this->sources['source_id'], $configuration);
    $this->addDependencies($source->calculateDependencies());

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function areContextsSatisfied(array $contexts): bool {
    $context_definitions = $this->getContexts();

    if (empty($context_definitions)) {
      return TRUE;
    }

    foreach ($context_definitions as $key => $context_definition) {
      if (!$context_definition->isRequired()) {
        continue;
      }

      if (!\array_key_exists($key, $contexts)) {
        return FALSE;
      }

      if (!$context_definition->isSatisfiedBy($contexts[$key])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Recursively get the source contexts.
   *
   * @param string $source_id
   *   Source plugin ID.
   * @param array $source
   *   Source plugin configuration.
   *
   * @return array
   *   Context definitions of the source.
   */
  private function getContextFromSource(string $source_id, array $source): array {
    /** @var \Drupal\ui_patterns\SourceInterface $source */
    $source = $this->sourcePluginManager()->createInstance($source_id, ['settings' => $source]);

    if ($source->getPluginId() === 'component') {
      return $this->getContextsFromComponent($source);
    }

    // @todo Traverse also context switchers.
    return $source->getContextDefinitions();
  }

  /**
   * Get contexts from component.
   *
   * Go through all slots and props to get the nested sources contexts.
   *
   * @param \Drupal\ui_patterns\SourceInterface $source
   *   Source plugin.
   *
   * @return array
   *   Context definitions of the component source.
   */
  private function getContextsFromComponent(SourceInterface $source): array {
    $contexts = [];
    $slots = $source->getSetting('component')['slots'] ?? [];

    foreach ($slots as $slot) {
      foreach ($slot['sources'] ?? [] as $slot_source) {
        $contexts = \array_merge($contexts, $this->getContextFromSource($slot_source['source_id'], $slot_source['source']));
      }
    }

    $props = $source->getSetting('component')['props'] ?? [];

    foreach ($props as $prop_source) {
      $contexts = \array_merge($contexts, $this->getContextFromSource($prop_source['source_id'], $prop_source['source']));
    }

    return $contexts;
  }

  /**
   * Recursively fill the node_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function fillInternalId(array &$array): void {
    if (isset($array['source_id']) && !isset($array['node_id'])) {
      $array['node_id'] = \uniqid();
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::fillInternalId($value);
      }
    }
  }

  /**
   * Gets the source plugin manager.
   *
   * @return \Drupal\ui_patterns\SourcePluginManager
   *   The source plugin manager.
   */
  private function sourcePluginManager(): SourcePluginManager {
    return $this->sourcePluginManager ??= \Drupal::service('plugin.manager.ui_patterns_source');
  }

  /**
   * Slot source proxy.
   *
   * @return \Drupal\display_builder\SlotSourceProxy
   *   The slot source proxy.
   */
  private function slotSourceProxy(): SlotSourceProxy {
    return $this->slotSourceProxy ??= \Drupal::service('display_builder.slot_sources_proxy');
  }

}
