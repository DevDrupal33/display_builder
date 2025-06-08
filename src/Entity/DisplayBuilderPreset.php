<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\display_builder\DisplayBuilderPresetInterface;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Form\DisplayBuilderPresetForm;
use Drupal\display_builder_ui\DisplayBuilderPresetListBuilder;
use Drupal\Core\Entity\EntityDeleteForm;

/**
 * Defines the display builder preset entity type.
 */
#[ConfigEntityType(
  id: 'display_builder_preset',
  label: new TranslatableMarkup('Display builder Preset'),
  label_collection: new TranslatableMarkup('Display builder Presets'),
  label_singular: new TranslatableMarkup('display builder Preset'),
  label_plural: new TranslatableMarkup('display builder presets'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'theme' => 'theme',
    'description' => 'description',
    'group' => 'group',
    'sources' => 'sources',
  ],
  handlers: [
    'list_builder' => DisplayBuilderPresetListBuilder::class,
    'form' => [
      'add' => DisplayBuilderPresetForm::class,
      'edit' => DisplayBuilderPresetForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/display-builder/preset/add',
    'edit-form' => '/admin/structure/display-builder/preset/{display_builder_preset}',
    'delete-form' => '/admin/structure/display-builder/preset/{display_builder_preset}/delete',
    'collection' => '/admin/structure/display-builder/preset',
  ],
  admin_permission: 'administer display builder preset',
  constraints: [
    'ImmutableProperties' => [
      'id',
    ],
  ],
  config_export: [
    'id',
    'label',
    'theme',
    'description',
    'group',
    'sources',
  ],
)]
final class DisplayBuilderPreset extends ConfigEntityBase implements DisplayBuilderPresetInterface {

  /**
   * The preset ID.
   */
  protected string $id;

  /**
   * The preset label.
   */
  protected string $label;

  /**
   * The preset theme.
   */
  protected string $theme;

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
  protected string $sources;

  /**
   * {@inheritdoc}
   */
  public function getSources(array $contexts = [], bool $fillInstanceId = TRUE): array {
    $data = Yaml::decode($this->get('sources') ?? []);

    if (isset($data[0]) && count($data) === 1) {
      $data = reset($data);
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
   * Recursively fill the _instance_id key.
   *
   * @param array $array
   *   The array reference.
   */
  private static function fillInstanceId(array &$array): void {
    if (isset($array['source_id']) && !isset($array['_instance_id'])) {
      $array['_instance_id'] = uniqid();
    }

    foreach ($array as &$value) {
      if (\is_array($value)) {
        self::fillInstanceId($value);
      }
    }
  }

}
