<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Config form builder.
 */
class ConfigFormBuilder implements ConfigFormBuilderInterface {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(EntityWithDisplayBuilderInterface $entity, bool $mandatory = TRUE): array {
    $options = $this->getAllowedDisplayBuilders();

    if (empty($options)) {
      return [];
    }

    $options = $mandatory ? $options : ['' => $this->t('- Disabled -')] + $options;
    $form = [
      '#type' => 'select',
      '#title' => $this->t('Configuration'),
      '#description' => $this->t('Select a Display builder configuration for this instance, can be changed later.'),
      '#options' => $options,
      '#default_value' => $entity->getDisplayBuilder()?->id() ?? '',
    ];

    if ($entity->getInstanceId()) {
      $form['#description'] = [
        '#type' => 'link',
        '#title' => $this->t('Build the display'),
        '#url' => $entity->getBuilderUrl(),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDisplayBuilder(?string $display_builder, bool $mandatory = TRUE): array {
    /** @var \Drupal\display_builder\DisplayBuilderInterface[] $display_builders */
    $display_builders = $this->entityTypeManager->getStorage('display_builder')->loadMultiple();
    $options = $mandatory ? [] : ['' => $this->t('- Disabled -')];

    foreach ($display_builders as $entity_id => $entity) {
      if ($this->currentUser->hasPermission($entity->getPermissionName())) {
        $options[$entity_id] = $entity->label();
      }
    }

    return match (\count($options)) {
      // No form input if no display builders.
      0 => [],
      // Hidden form input if only one display builder.
      1 => [
        '#type' => 'hidden',
        '#default_value' => \array_keys($options)[0],
      ],
      default => [
        '#type' => 'select',
        '#title' => $this->t('Configuration'),
        '#description' => $this->t('Select a Display builder configuration for this instance, can be changed later.'),
        '#options' => $options,
        '#default_value' => $display_builder,
      ]
    };
  }

  /**
   * Get display builders allowed for the current user.
   */
  protected function getAllowedDisplayBuilders(): array {
    $options = [];
    /** @var \Drupal\display_builder\DisplayBuilderInterface[] $display_builders */
    $display_builders = $this->entityTypeManager->getStorage('display_builder')->loadMultiple();

    foreach ($display_builders as $entity_id => $entity) {
      if ($this->currentUser->hasPermission($entity->getPermissionName())) {
        $options[$entity_id] = $entity->label();
      }
    }

    return $options;
  }

}
