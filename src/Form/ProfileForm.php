<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
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
          'src' => \base_path() . $path . '/assets/images/islands-regions.png',
          'width' => '1122',
          'height' => '171',
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

    // Labels define the order.
    $labels = [
      'library' => $this->t('Library panels'),
      'view' => $this->t('View panels'),
      'preview' => $this->t('Preview panels'),
      'button' => $this->t('Toolbar buttons'),
      'contextual' => $this->t('Contextual panels'),
      'floating' => $this->t('Floating controls'),
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

    $form['islands'][IslandType::Library->value]['library_flat'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Flatten library panels'),
      '#description' => $this->t('<mark>Advanced</mark> Merge all enabled library panels (Components, Blocks, Presets...) into a single flat list without tabs, sharing one search box, instead of separate tabs in the builder sidebar.'),
      '#default_value' => $entity->isLibraryFlat(),
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
  public function submitForm(array &$form, FormStateInterface $form_state): ProfileInterface {
    parent::submitForm($form, $form_state);

    // Save user permissions.
    /** @var \Drupal\display_builder\Entity\ProfileInterface $entity */
    $entity = $this->entity;

    if ($permission = $entity->getPermissionName()) {
      foreach ($form_state->getValue('roles') ?? [] as $rid => $enabled) {
        \user_role_change_permissions($rid, [$permission => $enabled]);
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
    $regions = IslandType::regions($type);

    if (empty($regions)) {
      return $this->buildIslandTable($type, $islands, $configuration);
    }

    // A type split into several regions gets one table per region: an island
    // belongs to its region the way it belongs to its type, so it can be
    // reordered inside it but never moved out of it.
    $build = [];

    foreach ($regions as $region => $label) {
      $region_islands = \array_filter(
        $islands,
        static fn (IslandInterface $island): bool => $island->getRegionId() === $region
      );

      // The region name goes in the table's own caption rather than a sibling
      // heading, so assistive tech ties the two together. It still needs to
      // read as a section heading.
      $caption = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $label,
      ];

      $build[$region] = $this->buildIslandTable($type . '-' . $region, $region_islands, $configuration, $caption);
    }

    return $build;
  }

  /**
   * Build island row.
   *
   * @param \Drupal\display_builder\Island\IslandInterface $island
   *   Island plugin.
   * @param array $configuration
   *   Configuration of this specific island.
   * @param string $group
   *   The identity of the table holding the row, @see buildIslandTable().
   *
   * @return array
   *   A renderable array.
   */
  protected function buildIslandRow(IslandInterface $island, array $configuration, string $group): array {
    $id = $island->getPluginId();
    $definition = (array) $island->getPluginDefinition();
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
        'class' => ['draggable-weight-' . $group],
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

  /**
   * Build a draggable table of islands.
   *
   * @param string $group
   *   The table identity, an island type or an island type and region. Two
   *   tables on the same page must not share it, or dragging a row in one
   *   rewrites the weights the other is driving.
   * @param array $islands
   *   List of island plugins.
   * @param array $configuration
   *   Configuration of all islands from this type.
   * @param array|null $caption
   *   (Optional) The region name, for a type split into several.
   *
   * @return array
   *   A renderable array.
   */
  private function buildIslandTable(string $group, array $islands, array $configuration, ?array $caption = NULL): array {
    $table = [
      '#type' => 'table',
      '#caption' => $caption,
      '#header' => [
        'drag' => '',
        'status' => $this->t('Enabled'),
        'name' => $this->t('Island'),
        'summary' => $this->t('Configuration'),
        'actions' => $this->t('Actions'),
        'weight' => $this->t('Weight'),
      ],
      '#empty' => $this->t('No island here.'),
      '#attributes' => ['id' => 'db-islands-' . $group],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'draggable-weight-' . $group,
        ],
      ],
      // We don't want to submit the island type level. We already know the
      // type of each islands thanks to IslandInterface::getTypeId() so let's
      // keep the storage flat.
      '#parents' => ['islands'],
    ];

    $rows = [];

    foreach ($islands as $id => $island) {
      $rows[$id] = $this->buildIslandRow($island, $configuration[$id] ?? [], $group);
    }

    \uasort($rows, [SortArray::class, 'sortByWeightProperty']);

    return $table + $rows;
  }

}
