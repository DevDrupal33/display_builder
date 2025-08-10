<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Form\PatternPresetForm;
use Drupal\display_builder\PatternPresetInterface;
use Drupal\display_builder_ui\PatternPresetListBuilder;
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
    'edit-form' => '/admin/structure/display-builder/preset/{pattern_preset}',
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
  protected string $label;

  /**
   * The preset description.
   */
  protected string $description;

  /**
   * The preset group.
   */
  protected string $group;

  /**
   * The preset sources.
   */
  protected array $sources;

  /**
   * {@inheritdoc}
   */
  public function getSources(array $contexts = [], bool $fillInstanceId = TRUE): array {
    $data = $this->get('sources') ?? [];

    if (isset($data[0]) && \count($data) === 1) {
      $data = \reset($data);
    }

    if (empty($data) || !isset($data['source_id'])) {
      return [];
    }

    if ($fillInstanceId) {
      self::fillInstanceId($data);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    // The root level is a single nestable source plugin.
    $source = $this->sources;

    if (!isset($source['source_id'])) {
      return $this;
    }
    // This will automatically be done by parent::calculateDependencies() if we
    // implement EntityWithPluginCollectionInterface.
    $configuration = [
      'settings' => $source['source'] ?? [],
    ];
    /** @var \Drupal\ui_patterns\SourceInterface $source */
    $source = $this->getSourceManager()->createInstance($source['source_id'], $configuration);
    $this->addDependencies($source->calculateDependencies());

    return $this;
  }

  /**
   * Gets the source plugin manager.
   *
   * @return \Drupal\ui_patterns\SourcePluginManager
   *   The source plugin manager.
   */
  protected static function getSourceManager(): SourcePluginManager {
    return \Drupal::service('plugin.manager.ui_patterns_source');
  }

  /**
   * Recursively fill the _instance_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function fillInstanceId(array &$array): void {
    if (isset($array['source_id']) && !isset($array['_instance_id'])) {
      $array['_instance_id'] = \uniqid();
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::fillInstanceId($value);
      }
    }
  }

}
