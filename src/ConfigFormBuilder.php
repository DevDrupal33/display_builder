<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Config form builder.
 */
class ConfigFormBuilder implements ConfigFormBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(DisplayBuildableInterface $buildable, bool $mandatory = TRUE): array {
    $profile = $buildable->getProfile();
    $allowed = $this->isAllowed($buildable);

    if (!$allowed && !$profile) {
      return [
        ConfigFormBuilderInterface::PROFILE_PROPERTY => [
          '#markup' => $this->t('You are not allowed to use Display Builder.'),
        ],
      ];
    }

    if (!$allowed && $profile) {
      return [
        ConfigFormBuilderInterface::PROFILE_PROPERTY => $this->buildDisabledSelect($profile),
      ];
    }

    $form = [
      ConfigFormBuilderInterface::PROFILE_PROPERTY => $this->buildSelect($profile, $mandatory),
    ];

    // Add the builder link to edit.
    if ($buildable->getInstanceId() && $profile) {
      $form['link'] = $this->buildLink($buildable);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedProfiles(?AccountInterface $account = NULL): array {
    $account = $account ?? $this->currentUser;
    $options = [];
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');
    $entity_ids = $storage->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    /** @var \Drupal\display_builder\ProfileInterface[] $display_builders */
    $display_builders = $storage->loadMultiple($entity_ids);

    // Entity query doesn't execute access control handlers for config
    // entities. So we need to do an extra check here.
    foreach ($display_builders as $entity_id => $entity) {
      // We don't execute $entity->access() to not catch 'administer display
      // builder profile' permission. See ProfileAccessControlHandler.
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
  public function isAllowed(DisplayBuildableInterface $buildable, ?AccountInterface $account = NULL): bool {
    $options = $this->getAllowedProfiles($account);

    if (empty($options)) {
      return FALSE;
    }
    $profile = $buildable->getProfile();

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
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   An entity allowing the use of Display Builder.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildLink(DisplayBuildableInterface $buildable): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => [
        'class' => ['form-item__description'],
      ],
      'content' => [
        '#type' => 'link',
        '#title' => $this->t('Build the display'),
        '#url' => $buildable->getBuilderUrl(),
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
