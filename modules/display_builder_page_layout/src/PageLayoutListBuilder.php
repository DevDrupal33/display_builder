<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Provides a listing of page layouts.
 */
final class PageLayoutListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_page_layout';
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['notice'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('The first enabled, non-empty, applicable to a page according to its conditions, page layout will be loaded.'),
      '#weight' => -100,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    $entities = parent::load();

    if ($this->countActivePageLayouts($entities) === 0) {
      $params = [
        '@url' => Url::fromRoute('block.admin_display')->toString(),
      ];
      $message = $this->t('Without any active Page Layout, pages are managed by <a href="@url">Block Layout</a>.', $params);
      $this->messenger()->addStatus($message);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Page layout');
    $header['profile_id'] = $this->t('Profile');
    $header['conditions'] = $this->t('Conditions');
    $header['status'] = $this->t('Status');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $row['label'] = $entity->label();
    $row['profile_id']['#plain_text'] = $entity->getProfile()?->label() ?? '?';
    $row['conditions'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $this->getConditionsSummary($entity),
    ];
    $status = $entity->status() ? $this->t('Enabled') : '❌ ' . $this->t('Disabled');
    $status = $entity->status() && empty($entity->getSources()) ? '❌ ' . $this->t('Empty') : $status;
    $row['status']['#plain_text'] = $status;

    $row = $row + parent::buildRow($entity);
    $row['#attributes']['data-id'] = $entity->id();

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity): array {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface @page_layout */
    $page_layout = $entity;
    $operations = parent::getDefaultOperations($entity);
    $operations[] = [
      'title' => new TranslatableMarkup('Build display'),
      'weight' => -10,
      'url' => $page_layout->getBuilderUrl(),
    ];

    return $operations;
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

  /**
   * Count enabled and non empty Page Layout entities.
   *
   * @param array<int,mixed> $entities
   *   The entities to process.
   *
   * @return int
   *   The number of enabled and non empty Page Layout entities.
   */
  private function countActivePageLayouts(array $entities): int {
    $count = 0;

    foreach ($entities as $entity) {
      if ($entity->status === TRUE && !empty($entity->getSources())) {
        ++$count;
      }
    }

    return $count;
  }

  /**
   * Get summary of configured conditions, one item per plugin.
   *
   * @param PageLayoutInterface $entity
   *   The page layout entity.
   *
   * @return array
   *   The summary of configured conditions.
   */
  private function getConditionsSummary(PageLayoutInterface $entity): array {
    $summary = [];
    $conditions = $entity->getConditions();

    foreach ($conditions->getIterator() as $condition) {
      if ($condition->getPluginId() === 'request_path') {
        $pages = \array_map('trim', \explode("\n", $condition->getConfiguration()['pages']));
        $pages = \implode(', ', $pages);

        if (!$condition->isNegated()) {
          $summary[] = $this->t('On the following pages: @pages', ['@pages' => $pages]);
        }
        else {
          $summary[] = $this->t('Not on the following pages: @pages', ['@pages' => $pages]);
        }

        continue;
      }
      $summary[] = $condition->summary();
    }

    return $summary;
  }

}
