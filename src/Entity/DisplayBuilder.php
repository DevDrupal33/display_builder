<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\DisplayBuilderViewBuilder;
use Drupal\display_builder\Form\DisplayBuilderForm;
use Drupal\display_builder\Form\DisplayBuilderIslandPluginForm;
use Drupal\display_builder_ui\DisplayBuilderListBuilder;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Defines the display builder entity type.
 */
#[ConfigEntityType(
  id: 'display_builder',
  label: new TranslatableMarkup('Display builder'),
  label_collection: new TranslatableMarkup('Display builders'),
  label_singular: new TranslatableMarkup('display builder'),
  label_plural: new TranslatableMarkup('display builders'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'description' => 'description',
    'weight' => 'weight',
  ],
  handlers: [
    'route_provider' => [
      'html' => 'Drupal\display_builder\Routing\DisplayBuilderRouteProvider',
    ],
    'view_builder' => DisplayBuilderViewBuilder::class,
    'list_builder' => DisplayBuilderListBuilder::class,
    'form' => [
      'add' => DisplayBuilderForm::class,
      'edit' => DisplayBuilderForm::class,
      'delete' => EntityDeleteForm::class,
      'edit-plugin' => DisplayBuilderIslandPluginForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/display-builder/add',
    'edit-form' => '/admin/structure/display-builder/{display_builder}',
    'edit-plugin-form' => '/admin/structure/display-builder/{display_builder}/edit/{island_id}',
    'delete-form' => '/admin/structure/display-builder/{display_builder}/delete',
    'collection' => '/admin/structure/display-builder',
  ],
  admin_permission: 'administer display builders',
  constraints: [
    'ImmutableProperties' => [
      'id',
    ],
  ],
  config_export: [
    'id',
    'label',
    'library',
    'description',
    'islands',
    'debug',
    'weight',
  ],
)]
final class DisplayBuilder extends ConfigEntityBase implements DisplayBuilderInterface {

  /**
   * The display builder config ID.
   */
  protected string $id;

  /**
   * The display builder label.
   */
  protected string $label;

  /**
   * The display builder description.
   */
  protected string $description;

  /**
   * The display builder library mode.
   */
  protected string $library = 'cdn';

  /**
   * The display builder debug mode.
   */
  protected bool $debug = FALSE;

  /**
   * The islands configuration for storage.
   */
  protected ?array $islands = [];

  /**
   * Weight of this page layout when negotiating the page variant.
   *
   * The first/lowest that is accessible according to conditions is loaded.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * {@inheritdoc}
   */
  public function getIslandConfiguration(string $island_id): array {
    return $this->islands[$island_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandConfigurations(): array {
    return $this->islands ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setIslandConfiguration(string $island_id, array $configuration = []): void {
    // When $configuration is updated from DisplayBuilderIslandPluginForm,
    // 'weight', 'enable' and 'region' properties are missing but they must not
    // be reset.
    $configuration['weight'] = $configuration['weight'] ?? $this->islands[$island_id]['weight'] ?? 0;
    $configuration['enable'] = $configuration['enable'] ?? $this->islands[$island_id]['enable'] ?? FALSE;

    // Only View islands have regions.
    if (isset($this->islands[$island_id]['region'])) {
      $configuration['region'] = $configuration['region'] ?? $this->islands[$island_id]['region'];
    }

    $this->islands[$island_id] = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandEnabled(): array {
    $island_enabled = [];

    foreach ($this->islands as $island_id => $configuration) {
      if (isset($configuration['enable']) && (bool) $configuration['enable']) {
        $island_enabled[$island_id] = $configuration['weight'] ?? 0;
      }
    }

    return $island_enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissionName(): string {
    return 'use display builder ' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles(): array {
    // Do not list any roles if the permission does not exist.
    $permission = $this->getPermissionName();

    if (empty($permission)) {
      return [];
    }

    $roles = \array_filter(Role::loadMultiple(), static fn (RoleInterface $role) => $role->hasPermission($permission));

    return \array_map(static fn (RoleInterface $role) => $role->label(), $roles);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = NULL, array $options = []): Url {
    if ($rel === 'edit-plugin-form' && $this->id() && isset($options['island_id'])) {
      $island_id = $options['island_id'];
      unset($options['island_id']);

      return Url::fromRoute(
        'entity.display_builder.edit_plugin_form',
        ['display_builder' => $this->id(), 'island_id' => $island_id],
        $options
      );
    }

    return parent::toUrl($rel, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary(): string {
    return $this->library;
  }

  /**
   * {@inheritdoc}
   */
  public function isDebugModeActivated(): bool {
    return $this->debug;
  }

}
