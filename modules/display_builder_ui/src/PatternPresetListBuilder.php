<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Pattern presets.
 */
final class PatternPresetListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    $header['themes'] = $this->t('Themes');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    /** @var \Drupal\display_builder\ProfileInterface $entity */
    $row['label'] = $entity->label();
    $row['description'] = $entity->get('description');
    $row['themes'] = \implode(', ', $entity->getDependencies()['theme'] ?? []);

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['notice'] = [
      '#markup' => $this->t('A Pattern preset is a reusable arrangement of components.<br>A preset can be created manually or from a Display builder directly.'),
      '#prefix' => '<div class="description">',
      '#suffix' => '</div>',
      '#weight' => -100,
    ];

    return $build;
  }

}
