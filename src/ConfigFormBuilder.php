<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Config form builder.
 */
class ConfigFormBuilder implements ConfigFormBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected StateManagerInterface $stateManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(EntityWithDisplayBuilderInterface $entity, bool $mandatory = TRUE): array {
    $options = $this->getAllowedDisplayBuilders();

    if (empty($options)) {
      return [];
    }

    $form = [];

    $description = $this->t('Select a Display builder profile for this instance. Can be changed anytime.');
    $description .= '<br>';
    $description .= $this->t('Profiles allow to include specific functionalities available in the builder.');

    $options = $mandatory ? $options : ['' => $this->t('- Disabled -')] + $options;
    $form[StorageProperties::ConfigEntityId->value] = [
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#description' => $description,
      '#options' => $options,
    ];

    if ($entity->getDisplayBuilder()?->id()) {
      $form[StorageProperties::ConfigEntityId->value]['#default_value'] = (string) $entity->getDisplayBuilder()->id();
    }

    $instance_id = $entity->getInstanceId();

    // Add the builder link to edit.
    if ($instance_id && $entity->getDisplayBuilder()) {
      $params = [
        '@url' => $entity->getBuilderUrl()->toString(),
      ];

      $message = $this->t('Click o this link to edit the display: <a href="@url" target="_blank">build the display</a>.', $params);
      $form['link'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['form-item__description'],
        ],
        '#value' => $message,
      ];
    }

    // Add admin information to link the profiles.
    if ($this->currentUser->hasPermission('administer display builders')) {
      $params = [
        '@url' => Url::fromRoute('entity.display_builder.collection')->toString(),
      ];
      $message = $this->t('Display builder profiles can be configured from the <a href="@url" target="_blank">Display builder profiles</a>.', $params);
      $form['admin_link'] = [
        '#type' => 'html_tag',
        '#prefix' => '<hr>',
        '#tag' => 'p',
        '#attributes' => [
          'class' => ['form-item__description'],
        ],
        '#value' => $message,
      ];
    }

    return $form;
  }

  /**
   * Get display builders allowed for the current user.
   *
   * @return array
   *   The list of allowed profiles.
   */
  protected function getAllowedDisplayBuilders(): array {
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
