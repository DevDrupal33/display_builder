<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Form\ProfileForm;
use Drupal\display_builder\Form\ProfileIslandPluginForm;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder\ProfileViewBuilder;
use Drupal\display_builder_ui\ProfileListBuilder;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Defines the display builder entity type.
 */
#[ConfigEntityType(
  id: 'display_builder_profile',
  label: new TranslatableMarkup('Display builder profile'),
  label_collection: new TranslatableMarkup('Display builder profiles'),
  label_singular: new TranslatableMarkup('display builder profile'),
  label_plural: new TranslatableMarkup('display builders profiles'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'description' => 'description',
    'weight' => 'weight',
  ],
  handlers: [
    'route_provider' => [
      'html' => 'Drupal\display_builder\Routing\ProfileRouteProvider',
    ],
    'view_builder' => ProfileViewBuilder::class,
    'list_builder' => ProfileListBuilder::class,
    'form' => [
      'add' => ProfileForm::class,
      'edit' => ProfileForm::class,
      'delete' => EntityDeleteForm::class,
      'edit-plugin' => ProfileIslandPluginForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/display-builder/add',
    'edit-form' => '/admin/structure/display-builder/{display_builder_profile}',
    'edit-plugin-form' => '/admin/structure/display-builder/{display_builder_profile}/edit/{island_id}',
    'delete-form' => '/admin/structure/display-builder/{display_builder_profile}/delete',
    'collection' => '/admin/structure/display-builder',
  ],
  admin_permission: 'administer display builder profile',
  constraints: [
    'ImmutableProperties' => [
      'id',
    ],
  ],
  // Example: display_builder.profile.default.yml.
  config_prefix: 'profile',
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
final class Profile extends ConfigEntityBase implements ProfileInterface {

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
    // When $configuration is updated from ProfileIslandPluginForm,
    // 'weight', 'status' and 'region' properties are missing but they must not
    // be reset.
    $configuration['weight'] = $configuration['weight'] ?? $this->islands[$island_id]['weight'] ?? 0;
    $configuration['status'] = $configuration['status'] ?? $this->islands[$island_id]['status'] ?? FALSE;

    // Only View islands have regions.
    if (isset($this->islands[$island_id]['region'])) {
      $configuration['region'] = $configuration['region'] ?? $this->islands[$island_id]['region'];
    }

    $this->islands[$island_id] = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledIslands(): array {
    $island_enabled = [];

    foreach ($this->islands as $island_id => $configuration) {
      if (isset($configuration['status']) && $configuration['status']) {
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
        'entity.display_builder_profile.edit_plugin_form',
        ['display_builder_profile' => $this->id(), 'island_id' => $island_id],
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
