<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\IslandInterface;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandTypeViewDisplay;
use Drupal\display_builder\ProfileInterface;
use Drupal\user\RoleInterface;

/**
 * Display builder form.
 */
final class ProfileForm extends EntityForm {

  use AutowireTrait;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\display_builder\ProfileInterface $entity */
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

    $form['islands'] = [
      '#type' => 'details',
      '#title' => $this->t('Islands configuration'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $island_configuration = $entity->get('islands') ?? [];

    /** @var \Drupal\display_builder\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    $island_by_types = $islandPluginManager->getIslandsByTypes();

    \ksort($island_by_types);

    foreach ($island_by_types as $type => $islands) {
      $form['islands']['title_' . $type] = [
        '#type' => 'fieldgroup',
        '#title' => $this->t('@type islands', ['@type' => $type]),
        '#description' => IslandType::description($type),
      ];
      $form['islands'][$type] = $this->buildIslandTypeTable(IslandType::from($type), $islands, $island_configuration);
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
  public function submitForm(array &$form, FormStateInterface $form_state): ProfileInterface {
    parent::submitForm($form, $form_state);

    // Save user permissions.
    /** @var \Drupal\display_builder\ProfileInterface $entity */
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
   * @param \Drupal\display_builder\IslandType $type
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
        'enable' => $this->t('Enable'),
        'name' => $this->t('Island'),
        'summary' => $this->t('Configuration'),
        'region' => ($type === IslandType::View->value) ? $this->t('Region') : '',
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
   * @param \Drupal\display_builder\IslandInterface $island
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
    /** @var \Drupal\display_builder\IslandPluginManagerInterface $islandPluginManager */
    $islandPluginManager = \Drupal::service('plugin.manager.db_island'); // phpcs:ignore
    /** @var \Drupal\display_builder\IslandConfigurationFormInterface $instance */
    $instance = $islandPluginManager->createInstance($id, $configuration);
    $weight = isset($configuration['weight']) ? (string) $configuration['weight'] : '0';

    $row = [];
    $row['#attributes']['class'] = ['draggable'];
    $row['#weight'] = (int) $weight;

    $row[''] = [];
    $row['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#title_display' => 'invisible',
      '#default_value' => $configuration['enable'] ?? $definition['enabled_by_default'] ?? FALSE,
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
      if ($id !== 'library' && !isset($configuration['region']) && isset($definition['enabled_by_default'])) {
        $region = IslandTypeViewDisplay::Main->value;
      }
      else {
        $region = $configuration['region'] ?? NULL;
      }
      $row['region'] = [
        '#type' => 'radios',
        '#title' => $this->t('Display'),
        '#title_display' => 'invisible',
        '#options' => IslandTypeViewDisplay::regions(),
        '#default_value' => $region,
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
            'input[name="islands[button][' . $id . '][enable]"]' => ['checked' => TRUE],
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

}
