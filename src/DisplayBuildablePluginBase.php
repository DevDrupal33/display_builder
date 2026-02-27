<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for display_buildable plugins.
 */
abstract class DisplayBuildablePluginBase extends PluginBase implements DisplayBuildableInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * Current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->moduleHandler = $container->get('module_handler');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    $reflection = new \ReflectionClass(static::class);
    $attribute = $reflection->getAttributes(DisplayBuildable::class);

    return $attribute[0]->newInstance()->instance_prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    $definition = $this->pluginDefinition;

    return (string) ($definition instanceof PluginDefinitionInterface ? $definition->id() : ($definition['label'] ?? ''));
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->getInstance();

    if (!$instance) {
      /** @var \Drupal\display_builder\InstanceStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('display_builder_instance');
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(): ?InstanceInterface {
    if (isset($this->instance)) {
      return $this->instance;
    }

    if ($this->getInstanceId() === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface|null $instance */
    $instance = $storage->load($this->getInstanceId());

    if (!$instance) {
      return NULL;
    }

    $this->instance = $instance;

    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Plugins will override this method.
    if (!isset($this->instance)) {
      return NULL;
    }

    return (string) $this->instance->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    // Plugins will override this method.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    // Plugins will override this method.
    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    // Plugins will override this method.
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildInstanceForm(bool $mandatory = TRUE): array {
    $profile = $this->getProfile();
    $allowed = $this->isAllowed();

    if (!$allowed && !$profile) {
      return [
        self::PROFILE_PROPERTY => [
          '#markup' => $this->t('You are not allowed to use Display Builder.'),
        ],
      ];
    }

    if (!$allowed && $profile) {
      return [
        self::PROFILE_PROPERTY => $this->buildDisabledSelect($profile),
      ];
    }

    $form = [
      self::PROFILE_PROPERTY => $this->buildSelect($profile, $mandatory),
    ];

    // Add the builder link to edit.
    if ($this->getInstanceId() && $profile) {
      $form['link'] = $this->buildLink();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedProfiles(?AccountInterface $account = NULL): array {
    $account ??= $this->currentUser;
    $options = [];
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');
    $entity_ids = $storage->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    /** @var \Drupal\display_builder\ProfileInterface[] $display_builders */
    $display_builders = $storage->loadMultiple($entity_ids);

    // Entity query doesn't execute access control handlers for config
    // entities. So we need to do an extra check here.
    foreach ($display_builders as $entity_id => $entity) {
      // We don't execute $entity->access() to not catch admin permission
      // 'administer display builder profile'.
      // @see ProfileAccessControlHandler.
      // Administrators can use any profile, but it is better to only propose
      // them the ones related to their permissions.
      if ($account->hasPermission($entity->getPermissionName())) {
        $options[$entity_id] = $entity->label();
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed(?AccountInterface $account = NULL): bool {
    $options = $this->getAllowedProfiles($account);

    if (empty($options)) {
      return FALSE;
    }
    $profile = $this->getProfile();

    if (!$profile) {
      return TRUE;
    }

    return isset($options[(string) $profile->id()]);
  }

  /**
   * Build profile select when user is allowed to select one.
   *
   * @param ?ProfileInterface $profile
   *   Display Builder profile (or not)
   * @param bool $mandatory
   *   (Optional). Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   *
   * @return array
   *   A renderable form array.
   */
  protected function buildSelect(?ProfileInterface $profile, bool $mandatory): array {
    $select = [
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#description' => $this->t('The profile defines the features available in the builder.'),
      '#options' => $this->getAllowedProfiles(),
    ];

    if ($profile) {
      $select['#default_value'] = (string) $profile->id();
    }
    elseif (isset($select['#options']['default'])) {
      $select['#default_value'] = 'default';
    }

    if ($mandatory) {
      $select['#required'] = TRUE;
    }
    else {
      $select['#empty_option'] = $this->t('- Disabled -');
    }

    // Add admin information to link the profiles.
    if ($this->isCurrentUserAllowedToAdministrate()) {
      $select['#description'] = [
        [
          '#markup' => $select['#description'] . '<br>',
        ],
        [
          '#type' => 'link',
          '#title' => $this->t('Add and configure display builder profiles'),
          '#url' => Url::fromRoute('entity.display_builder_profile.collection'),
          '#suffix' => '.',
        ],
      ];
    }

    return $select;
  }

  /**
   * Build link to Display Builder.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildLink(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'class' => ['form-item__description'],
      ],
      'content' => [
        '#type' => 'link',
        '#title' => $this->t('Build the display'),
        '#url' => $this->getBuilderUrl(),
        '#attributes' => [
          'class' => ['button', 'button--small'],
        ],
      ],
    ];
  }

  /**
   * Build disabled profile select when user is not allowed to select one.
   *
   * @param ProfileInterface $profile
   *   Display Builder profile (or not)
   *
   * @return array
   *   A renderable form array.
   */
  protected function buildDisabledSelect(ProfileInterface $profile): array {
    return [
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#description' => $this->t('You are not allowed to use Display Builder here.'),
      '#options' => [
        (string) $profile->id() => $profile->label(),
      ],
      '#disabled' => TRUE,
    ];
  }

  /**
   * Is the current user allowed to use administrate display builder profiles?
   *
   * @return bool
   *   Allowed or not.
   */
  protected function isCurrentUserAllowedToAdministrate(): bool {
    return $this->moduleHandler->moduleExists('display_builder_ui') && $this->currentUser->hasPermission('administer display builder profile');
  }

}
