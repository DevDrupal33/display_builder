<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\display_builder\IslandInterface;
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
      \ksort($roles);
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
    $island_configuration = $entity->get('island_configuration') ?? [];

    /** @var \Drupal\display_builder\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    $island_by_types = $islandPluginManager->getIslandsByTypes();

    \ksort($island_by_types);

    foreach ($island_by_types as $type => $islands) {
      $form['island_settings']['title_' . $type] = [
        '#type' => 'fieldgroup',
        '#title' => $this->t('@type islands', ['@type' => $type]),
        '#description' => IslandType::description($type),
      ];
      $form['island_settings'][$type] = $this->buildIslandTypeTable(IslandType::from($type), $islands, $island_configuration, $island_settings[$type] ?? []);
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

  /**
   * Build island type table.
   *
   * @param \Drupal\display_builder\IslandType $type
   *   Island type from IslandType enum.
   * @param array $islands
   *   List of island plugins.
   * @param array $configuration
   *   Configuration of all islands from this type.
   * @param array $settings
   *   Settings of all islands from this type.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildIslandTypeTable(IslandType $type, array $islands, array $configuration, array $settings): array {
    $type = $type->value;
    $table_has_options = FALSE;
    $table = [
      '#type' => 'table',
      '#header' => [
        'drag' => '',
        'enable' => $this->t('Enable'),
        'name' => $this->t('Island'),
        'summary' => $this->t('Configuration'),
        'options' => $this->t('Options'),
        'actions' => '',
        'weight' => $this->t('Weight'),
      ],
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
      $table[$id] = $this->buildIslandRow($island, $configuration[$id] ?? [], $settings[$id] ?? [], $table_has_options);
    }

    // Order rows by weight.
    \uasort($table, static function ($a, $b) {
      if (isset($a['#weight'], $b['#weight'])) {
        return (int) $a['#weight'] - (int) $b['#weight'];
      }
    });

    if (!$table_has_options && isset($table['#header']['options'])) {
      $table['#header']['options'] = '';
    }

    return $table;
  }

  /**
   * Build island row.
   *
   * @param \Drupal\display_builder\IslandInterface $island
   *   Island plugin.
   * @param array $configuration
   *   Configuration of this specific island.
   * @param array $default
   *   Settings of this specific island.
   * @param bool $table_has_options
   *   Table has options?
   *
   * @return array
   *   A renderable array.
   */
  protected function buildIslandRow(IslandInterface $island, array $configuration, array $default, bool &$table_has_options): array {
    $id = $island->getPluginId();
    $definition = (array) $island->getPluginDefinition();
    $type = $island->getTypeId();
    /** @var \Drupal\display_builder\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    /** @var \Drupal\display_builder\IslandInterface $instance */
    $instance = $islandPluginManager->createInstance($id, $configuration);
    $weight = isset($default['weight']) ? (string) $default['weight'] : '0';

    $row = [];
    $row['#attributes']['class'] = ['draggable'];
    $row['#weight'] = (int) $weight;

    $row[''] = [];
    $row['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#title_display' => 'invisible',
      '#default_value' => $default['enable'] ?? $definition['enabled_by_default'] ?? FALSE,
    ];
    $row['name'] = [
      '#type' => 'inline_template',
      '#template' => '<strong >{{ name }}</strong><br>{{ description }}',
      '#context' => [
        'name' => $definition['label'] ?? '',
        'description' => $definition['description'] ?? '',
      ],
    ];
    $row['summary'] = [
      '#markup' => \implode('<br>', $instance->configurationSummary()),
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
      $row['options'] = [
        '#type' => 'radios',
        '#title' => $this->t('Display'),
        '#title_display' => 'invisible',
        '#options' => IslandTypeViewDisplay::options(),
        '#default_value' => $default_option,
      ];
      $table_has_options = TRUE;
    }
    else {
      $row['options'] = [];
    }

    if ($island instanceof PluginFormInterface && !$this->entity->isNew()) {
      $row['actions'] = [
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
      $row['actions'] = ['#markup' => ''];
    }

    $row['weight'] = [
      '#type' => 'weight',
      '#default_value' => $weight,
      '#title' => $this->t('Weight'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'class' => ['draggable-weight-' . $type],
      ],
    ];

    return $row;
  }

}
