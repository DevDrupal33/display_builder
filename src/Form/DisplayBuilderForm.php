<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandTypeViewDisplay;
use Drupal\user\RoleInterface;

/**
 * Display builder form.
 */
final class DisplayBuilderForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\display_builder\DisplayBuilderInterface $entity */
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

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
    ];

    // Add user role access selection. Not available at creation because the
    // permissions are not set yet by DisplayBuilderPermissions.
    if (!$entity->isNew()) {
      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
      $form['roles'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Roles'),
        '#options' => \array_map(static fn (RoleInterface $role) => Html::escape((string) $role->label()), $roles),
        '#default_value' => \array_keys($entity->getRoles()),
      ];
    }

    // Inform on two time save for the island specific configurations.
    if ($this->entity->isNew()) {
      $form['island_settings_notice'] = [
        '#prefix' => '<div class="messages messages--warning">',
        '#markup' => $this->t('Island configuration will be available only after saving this form.'),
        '#suffix' => '</div>',
      ];
    }

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
      $this->t('Actions'),
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
          '#default_value' => $default['enable'] ?? $definition['enabled_by_default'] ?? FALSE,
        ];
        $table[$id]['name'] = [
          '#markup' => $definition['label'] ?? '',
        ];
        $table[$id]['description'] = [
          '#markup' => $definition['description'] ?? '',
        ];

        if ($type === IslandType::View->value) {
          // If new, only library is on sidebar by default.
          // @todo move this position option to Island configuration.
          if ($id !== 'library' && !isset($default['options']) && isset($definition['enabled_by_default'])) {
            $default_option = IslandTypeViewDisplay::Main->value;
          }
          else {
            $default_option = $default['options'] ?? NULL;
          }
          $table[$id]['options'] = [
            '#type' => 'select',
            '#title' => $this->t('Display'),
            '#title_display' => 'invisible',
            '#options' => IslandTypeViewDisplay::options(),
            '#default_value' => $default_option,
          ];
        }
        else {
          $table[$id]['options'] = [];
        }

        if ($island instanceof PluginFormInterface && !$this->entity->isNew()) {
          $table[$id]['actions'] = [
            '#type' => 'link',
            '#title' => $this->t('Configure'),
            '#url' => $this->entity->toUrl('edit-plugin-form', [
              'island_id' => $id,
              'query' => [
                'destination' => $this->entity->toUrl()->toString(),
              ],
            ]),
            '#attributes' => [
              'class' => ['use-ajax', 'button', 'button--small'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => \json_encode([
                'width' => 700,
              ]),
            ],
            '#states' => [
              'visible' => [
                'input[name="island_settings[button][' . $id . '][enable]"]' => ['checked' => TRUE],
              ],
            ],
          ];
        }
        else {
          $table[$id]['actions'] = ['#markup' => ''];
        }

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
      \uasort($table, static function ($a, $b) {
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

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): DisplayBuilderInterface {
    parent::submitForm($form, $form_state);

    // Save user permissions.
    /** @var \Drupal\display_builder\DisplayBuilderInterface $entity */
    $entity = $this->entity;

    if ($permission = $entity->getPermissionName()) {
      foreach ($form_state->getValue('roles') ?? [] as $rid => $enabled) {
        user_role_change_permissions($rid, [$permission => $enabled]);
      }
    }

    return $entity;
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

    // Stay on the form for new to allow islands configuration.
    if ($result === SAVED_NEW) {
      $form_state->setRedirect('entity.display_builder.edit_form', ['display_builder' => $this->entity->id()]);
    }
    elseif ($result === SAVED_UPDATED) {
      $form_state->setRedirect('entity.display_builder.collection');
    }

    return $result;
  }

}
