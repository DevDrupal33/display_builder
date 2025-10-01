<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides a listing of Pattern presets.
 */
final class PatternPresetListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pattern_preset_list_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['group'] = $this->t('Group');
    $header['description'] = $this->t('Description');
    $header['theme'] = $this->t('Theme');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\display_builder\PatternPresetInterface $entity */
    $row = [];
    $row['label'] = $entity->label();
    $row['group']['data']['#plain_text'] = $entity->getGroup();
    $row['description']['data']['#plain_text'] = $entity->get('description') ?: $entity->getSummary();
    $row['theme']['data']['#plain_text'] = \implode(', ', $entity->getDependencies()['theme'] ?? []);

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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $rows = Element::children($form['entities']);

    if (\count($rows) < 2) {
      unset($form['actions']['submit']);
    }

    return $form;
  }

}
