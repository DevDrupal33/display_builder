<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Store a plugin ID and its configuration to easily create instances.
 */
#[FieldType(
  id: 'plugin',
  label: new TranslatableMarkup('Plugin'),
  list_class: FieldItemList::class,
  no_ui: TRUE,
)]
class PluginItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'plugin_manager_id' => '',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $definitions = [];
    // @see FactoryInterface::createInstance()
    // The ID of the plugin being instantiated.
    $definitions['plugin_id'] = DataDefinition::create('string')->setRequired(TRUE);
    // An array of configuration relevant to the plugin instance.
    $definitions['configuration'] = MapDataDefinition::create();

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'plugin_id' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'configuration' => [
          'type' => 'blob',
          'size' => 'big',
          'serialize' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Get plugin instance.
   *
   * @return object
   *   An instantiated plugin instance.
   */
  public function getInstance(): object {
    $service_id = $this->getSetting('plugin_manager_id');
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $service */
    $service = \Drupal::service($service_id);

    return $service->createInstance(
      $this->get('plugin_id')->getString(),
      $this->get('configuration')->getValue() ?? [],
    );
  }

}
