<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandTypeViewDisplay;

/**
 * Display builder form.
 */
final class DisplayBuilderForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [DisplayBuilder::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
    ];

    $form['library'] = [
      '#type' => 'select',
      '#title' => $this->t('Shoelace library'),
      '#description' => $this->t('Select the library mode. If local must be installed in libraries folder, see README.'),
      '#options' => [
        'cdn' => $this->t('CDN'),
        'local' => $this->t('Local'),
      ],
      '#default_value' => $entity->get('library'),
    ];

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug mode'),
      '#description' => $this->t('Enable verbose JavaScript and error logs.'),
      '#default_value' => $entity->get('debug'),
    ];

    $form['island_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Islands configuration'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $island_settings = $entity->get('island_settings') ?? [];

    /** @var \Drupal\display_builder\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    $island_by_types = $islandPluginManager->getIslandsByTypes();

    $header = [
      '',
      $this->t('Enable'),
      $this->t('Name'),
      $this->t('Description'),
      '',
      $this->t('Provider'),
      $this->t('Weight'),
    ];

    foreach ($island_by_types as $type => $islands) {
      $table = [
        '#type' => 'table',
        '#header' => $header,
        '#attributes' => ['id' => 'db-islands-' . $type],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'draggable-weight-' . $type,
          ],
        ],
      ];

      foreach ($islands as $id => $island) {
        $definition = $island->getPluginDefinition();
        $default = $island_settings[$type][$id] ?? [];
        $weight = isset($default['weight']) ? (string) $default['weight'] : '0';

        $table[$id] = [];
        $table[$id]['#attributes']['class'] = ['draggable'];
        $table[$id]['#weight'] = (int) $weight;

        $table[$id][''] = [];
        $table[$id]['enable'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable'),
          '#title_display' => 'invisible',
          '#default_value' => $default['enable'] ?? FALSE,
        ];
        $table[$id]['name'] = [
          '#markup' => $definition['label'] ?? '',
        ];
        $table[$id]['description'] = [
          '#markup' => $definition['description'] ?? '',
        ];

        if ($type === IslandType::View->value) {
          $table[$id]['options'] = [
            '#type' => 'select',
            '#title' => $this->t('Display'),
            '#title_display' => 'invisible',
            '#options' => IslandTypeViewDisplay::options(),
            '#default_value' => $default['options'] ?? NULL,
          ];
        }
        else {
          $table[$id]['variant'] = [];
        }
        $table[$id]['provider'] = [
          '#markup' => $definition['provider'] ?? '',
        ];
        $table[$id]['weight'] = [
          '#type' => 'weight',
          '#default_value' => $weight,
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['draggable-weight-' . $type],
          ],
        ];
      }

      // Order rows by weight.
      uasort($table, static function ($a, $b) {
        if (isset($a['#weight'], $b['#weight'])) {
          return (int) $a['#weight'] - (int) $b['#weight'];
        }
      });

      $form['island_settings']['title_' . $type] = [
        '#type' => 'fieldgroup',
        '#title' => $this->t('@type islands', ['@type' => $type]),
        '#description' => IslandType::description($type),
      ];
      $form['island_settings'][$type] = $table;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    // Clear the plugin cache so changes are applied on front theme builder.
    /** @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface $pluginCacheClearer */
    $pluginCacheClearer = \Drupal::service('plugin.cache_clearer'); // phpcs:ignore
    $pluginCacheClearer->clearCachedDefinitions();

    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        SAVED_NEW => $this->t('Created new display builder config %label.', $message_args),
        SAVED_UPDATED => $this->t('Updated display builder config %label.', $message_args),
        default => '',
      }
    );

    return $result;
  }

}
