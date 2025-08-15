<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\UiPatterns\Source;

use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the source.
 */
#[Source(
  id: 'content_entity',
  label: new TranslatableMarkup('Content entity'),
  description: new TranslatableMarkup('A content entity with a specific display.'),
  prop_types: ['slot']
)]
class ContentEntitySource extends SourcePluginBase {

  /**
   * The entity type manager.
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity display manager.
   */
  protected ?EntityDisplayRepositoryInterface $entityDisplay;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $plugin = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->entityDisplay = $container->get('entity_display.repository');

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings(): array {
    return [
      'entity' => NULL,
      'entity_type' => NULL,
      'entity_display' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $entity = $this->getEntity();

    if ($entity === NULL) {
      return [];
    }

    $entity_type = $this->getSetting('entity_type');

    if ($entity_type === NULL) {
      return [];
    }

    $view_builder = $this->entityTypeManager->getViewBuilder($entity_type);

    return $view_builder->view($entity, $this->getSetting('entity_display') ?? 'default');
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $entity_type = $this->getSetting('entity_type');

    if ($entity_type === NULL || empty($entity_type)) {
      $options = [];

      foreach ($this->entityTypeManager->getDefinitions() as $key => $value) {
        if (!$value instanceof ConfigEntityType) {
          $options[$key] = $value->getLabel();
        }
      }

      $form['entity_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Pick a content entity type'),
        '#options' => $options,
      ];

      return $form;
    }

    // Once entity type selected, we do not allow change.
    $form['entity_type'] = [
      '#type' => 'hidden',
      '#value' => $entity_type,
    ];

    $entity = $this->getEntity();
    $form['entity'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Search a content of type @type', ['@type' => $entity_type]),
      '#target_type' => $entity_type,
      '#default_value' => $entity,
      '#selection_handler' => 'default',
      '#placeholder' => $this->t('Search for a content'),
    ];

    if (!$entity) {
      return $form;
    }

    $display_options = $this->entityDisplay->getViewModeOptions($entity_type);
    $form['entity_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Pick a display mode'),
      '#options' => $display_options,
      '#default_value' => $this->getSetting('entity_display'),
    ];

    return $form;
  }

  /**
   * Returns the referenced content entity object.
   */
  private function getEntity(): ?ContentEntityInterface {
    $entity_id = $this->getSetting('entity');
    $entity_type = $this->getSetting('entity_type');

    if (!$entity_id || !$entity_type) {
      return NULL;
    }

    if (!\is_numeric($entity_id)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage($entity_type);

    return $storage->load($entity_id);
  }

}
