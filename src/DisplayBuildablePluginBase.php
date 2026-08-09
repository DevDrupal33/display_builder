<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ConfigurablePluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for display_buildable plugins.
 */
abstract class DisplayBuildablePluginBase extends ConfigurablePluginBase implements DisplayBuildableInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * A tiny hint to remember where the initial data comes from.
   *
   * See ::getInitialSources() and ::getInitializationMessage().
   */
  protected string $initialDataSource = '';

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
    /** @var array $definition */
    $definition = $this->pluginDefinition;

    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->getInstance();

    if (!$instance) {
      $instance = $this->createDisplayBuilderInstance();
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(): ?InstanceInterface {
    if ($this->getInstanceId() === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface|null $instance */
    $instance = $storage->load($this->getInstanceId());

    return $instance;
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
  public function getPreviewPagePath(): ?string {
    // Most displays are fragments that can appear on any number of pages, so
    // they have no single page to preview against.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildInstanceForm(bool $mandatory = TRUE, ?TranslatableMarkup $title = NULL, bool $link = TRUE): array {
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
      self::PROFILE_PROPERTY => $this->buildSelect($profile, $mandatory, $title),
    ];

    // Add the builder link to edit.
    if ($this->getInstanceId() && $profile && $link) {
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
    /** @var \Drupal\display_builder\Entity\ProfileInterface[] $display_builders */
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
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    return $this->getRuntimeContexts([]);
  }

  /**
   * {@inheritdoc}
   */
  public function revertSources(): array {
    return [];
  }

  /**
   * Create a display builder instance.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function createDisplayBuilderInstance(): EntityInterface {
    $data = $this->getInitialSources();
    $tree = new SourceTree($data);
    $data = $tree->getTree();
    $data = [
      'id' => $this->getInstanceId(),
      'buildable' => [
        'plugin_id' => $this->getPluginId(),
        'configuration' => $this->getConfiguration(),
      ],
      'sources' => $data,
      'hash' => Instance::getUniqId($data),
      'revision_log_message' => $this->getInitializationMessage(),
      'revision_created' => \time(),
      'revision_user' => (int) $this->currentUser->id(),
    ];

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->create($data);

    return $instance;
  }

  /**
   * Get the message to put in the first log step.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The log message.
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    return $this->t('Initialize display');
  }

  /**
   * Initialize sources for this implementation.
   *
   * @return array
   *   The data.
   */
  protected function getInitialSources(): array {
    return $this->getSources();
  }

  /**
   * Build profile select when user is allowed to select one.
   *
   * @param \Drupal\display_builder\Entity\ProfileInterface|null $profile
   *   Display Builder profile (or not)
   * @param bool $mandatory
   *   (Optional) Is it mandatory to use Display Builder? (for example, in
   *   Page Layouts or in Entity View display Overrides). If not mandatory,
   *   the Display Builder is activated only if a Display Builder config entity
   *   is selected.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   (Optional) The Select title, default to 'Profile'.
   *
   * @return array
   *   A renderable form array.
   */
  protected function buildSelect(?ProfileInterface $profile, bool $mandatory = TRUE, ?TranslatableMarkup $title = NULL): array {
    $select = [
      '#type' => 'select',
      '#title' => $title ?? $this->t('Profile'),
      '#description' => $this->t('The profile defines the features available in the builder. It can be changed anytime after creation.'),
      '#options' => $this->getAllowedProfiles(),
    ];

    if ($profile) {
      $select['#default_value'] = (string) $profile->id();
    }
    elseif (isset($select['#options']['default']) && $mandatory) {
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
   * @param \Drupal\display_builder\Entity\ProfileInterface $profile
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
