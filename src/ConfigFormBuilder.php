<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
  public function build(WithDisplayBuilderInterface $entity, bool $mandatory = TRUE): array {
    $options = $this->getAllowedDisplayBuilders();

    if (empty($options)) {
      return [];
    }

    $form = [];

    $options = $mandatory ? $options : ['' => $this->t('- Disabled -')] + $options;
    $form[ConfigFormBuilderInterface::PROFILE_PROPERTY] = [
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#description' => $this->t('The profile defines the features available in the builder. It can be changed anytime.'),
      '#options' => $options,
    ];

    // Add admin information to link the profiles.
    if ($this->moduleHandler->moduleExists('display_builder_ui') && $this->currentUser->hasPermission('administer display builder profile')) {
      $form[ConfigFormBuilderInterface::PROFILE_PROPERTY]['#description'] = [
        [
          '#markup' => $form[ConfigFormBuilderInterface::PROFILE_PROPERTY]['#description'] . '<br>',
        ],
        [
          '#type' => 'link',
          '#title' => $this->t('Add and configure display builder profiles'),
          '#url' => Url::fromRoute('entity.display_builder.collection'),
          '#suffix' => '.',
        ],
      ];
    }

    // Add the builder link to edit.
    $instance_id = $entity->getInstanceId();

    if ($instance_id && $entity->getDisplayBuilder()) {
      $form['link'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['form-item__description'],
        ],
        'content' => [
          '#type' => 'link',
          '#title' => $this->t('Build the display'),
          '#url' => $entity->getBuilderUrl(),
          '#attributes' => [
            'class' => ['button', 'button--small'],
          ],
        ],
      ];
    }

    if ($entity->getDisplayBuilder()?->id()) {
      $form[ConfigFormBuilderInterface::PROFILE_PROPERTY]['#default_value'] = (string) $entity->getDisplayBuilder()->id();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedDisplayBuilders(): array {
    $options = [];
    $storage = $this->entityTypeManager->getStorage('display_builder');
    $entity_ids = $storage->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    /** @var \Drupal\display_builder\DisplayBuilderInterface[] $display_builders */
    $display_builders = $storage->loadMultiple($entity_ids);

    foreach ($display_builders as $entity_id => $entity) {
      if ($this->currentUser->hasPermission($entity->getPermissionName())) {
        $options[$entity_id] = $entity->label();
      }
    }

    return $options;
  }

}
