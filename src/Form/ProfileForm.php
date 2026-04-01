<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandType;
use Drupal\user\RoleInterface;

/**
 * Display builder form.
 */
final class ProfileForm extends EntityForm {

  use AutowireTrait;

  /**
   * Module extension list.
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\display_builder\Entity\ProfileInterface $entity */
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
        'exists' => [Profile::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
    ];

    // Add user role access selection. Not available at creation because the
    // permissions are not set yet by ProfilePermissions.
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
      $form['islands_notice'] = [
        '#prefix' => '<div class="messages messages--warning">',
        '#markup' => $this->t('Island configuration will be available only after saving this form.'),
        '#suffix' => '</div>',
      ];
    }

    $path = $this->moduleExtensionList()->getPath('display_builder');
    $form['islands_intro'] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'label',
        '#value' => $this->t('Islands'),
        '#attributes' => [
          'class' => ['form-item__label'],
        ],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => base_path() . $path . '/assets/images/islands-regions.png',
          'width' => '1200',
        ],
        '#prefix' => '<div style="text-align: center;">',
        '#suffix' => '</div>',
      ],
    ];

    $form['islands'] = [
      '#type' => 'vertical_tabs',
    ];

    $island_configuration = $entity->get('islands') ?? [];

    /** @var \Drupal\display_builder\Island\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    $island_by_types = $islandPluginManager->getIslandsByTypes();
    $labels = [
      'view' => $this->t('View panels'),
      'button' => $this->t('Toolbar buttons'),
      'contextual' => $this->t('Contextual panels'),
      'library' => $this->t('Library panels'),
      'menu' => $this->t('Menu items'),
    ];
    // Sort the types according to the labels.
    $island_by_types = \array_merge($labels, $island_by_types);

    foreach ($island_by_types as $type => $islands) {
      $form['islands'][$type] = [
        '#type' => 'details',
        '#title' => $labels[$type] ?? $type,
        '#description' => IslandType::description($type),
        '#group' => 'islands',
        'content' => $this->buildIslandTypeTable(IslandType::from($type), $islands, $island_configuration),
      ];
    }

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
  public function submitForm(array &$form, FormStateInterface $form_state): ProfileInterface {
    parent::submitForm($form, $form_state);

    // Save user permissions.
    /** @var \Drupal\display_builder\Entity\ProfileInterface $entity */
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

    // Set the initial default configuration and stay on the form to allow
    // islands configuration.
    if ($result === SAVED_NEW) {
      $form_state->setRedirect('entity.display_builder_profile.edit_form', ['display_builder_profile' => $this->entity->id()]);
    }
    elseif ($result === SAVED_UPDATED) {
      $form_state->setRedirect('entity.display_builder_profile.collection');
    }

    return $result;
  }

  /**
   * Build island type table.
   *
   * @param \Drupal\display_builder\Island\IslandType $type
   *   Island type from IslandType enum.
   * @param array $islands
   *   List of island plugins.
   * @param array $configuration
   *   Configuration of all islands from this type.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildIslandTypeTable(IslandType $type, array $islands, array $configuration): array {
    $type = $type->value;
    $table = [
      '#type' => 'table',
      '#header' => [
        'drag' => '',
        'status' => $this->t('Enabled'),
        'name' => $this->t('Island'),
        'summary' => $this->t('Configuration'),
        'region' => empty(IslandType::regions($type)) ? '' : $this->t('Region'),
        'actions' => $this->t('Actions'),
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
      // We don't want to submit the island type level. We already know the
      // type of each islands thanks to IslandInterface::getTypeId() so let's
      // keep the storage flat.
      '#parents' => ['islands'],
    ];

    foreach ($islands as $id => $island) {
      $table[$id] = $this->buildIslandRow($island, $configuration[$id] ?? []);
    }

    // Order rows by weight.
    \uasort($table, static function ($a, $b) {
      if (isset($a['#weight'], $b['#weight'])) {
        return (int) $a['#weight'] - (int) $b['#weight'];
      }
    });

    return $table;
  }

  /**
   * Build island row.
   *
   * @param \Drupal\display_builder\Island\IslandInterface $island
   *   Island plugin.
   * @param array $configuration
   *   Configuration of this specific island.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildIslandRow(IslandInterface $island, array $configuration): array {
    $id = $island->getPluginId();
    $definition = (array) $island->getPluginDefinition();
    $type = $island->getTypeId();
    /** @var \Drupal\display_builder\Island\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    /** @var \Drupal\display_builder\Island\IslandConfigurationFormInterface $instance */
    $instance = $islandPluginManager->createInstance($id, $configuration);
    $weight = isset($configuration['weight']) ? (string) $configuration['weight'] : '0';

    $row = [];
    $row['#attributes']['class'] = ['draggable'];
    $row['#weight'] = (int) $weight;

    $row[''] = [];
    $row['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#title_display' => 'invisible',
      '#default_value' => $configuration['status'] ?? $definition['enabled_by_default'] ?? FALSE,
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

    $regions = IslandType::regions($type);

    if (!empty($regions)) {
      $row['region'] = [
        '#type' => 'radios',
        '#title' => $this->t('Region'),
        '#title_display' => 'invisible',
        '#options' => $regions,
        '#default_value' => $configuration['region'] ?? $definition['default_region'] ?? NULL,
      ];
    }
    else {
      $row['region'] = [];
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
            'input[name="islands[' . $id . '][status]"]' => ['checked' => TRUE],
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

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();

    /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    $entity = $entity;

    if ($this->entity instanceof EntityWithPluginCollectionInterface) {
      // Do not manually update values represented by plugin collections.
      $values = \array_diff_key($values, $this->entity->getPluginCollections());
    }

    foreach ($values as $key => $value) {
      if ($key === 'islands') {
        $value = NestedArray::mergeDeep($entity->get('islands'), $value);
      }
      $entity->set($key, $value);
    }
  }

  /**
   * Wraps the module extension list service repository service.
   *
   * @return \Drupal\Core\Extension\ModuleExtensionList
   *   The module extension list service.
   */
  protected function moduleExtensionList(): ModuleExtensionList {
    return $this->moduleExtensionList ??= \Drupal::service('extension.list.module'); // phpcs:ignore
  }

}
