<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\Core\Cache\CacheableMetadata;
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
      '#value' => $this->t('A page is built by the first layout in this list whose conditions match it. Layouts that are disabled or empty are skipped. Pages matched by none of them fall through to the default layout, at the bottom of the list.'),
      '#weight' => -100,
    ];
    $build = $this->addDefaultPageLayouts($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Remove default page layouts because they are not draggable and will be
   * displayed apart.
   */
  public function load(): array {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface[] $entities */
    $entities = parent::load();

    return \array_filter($entities, static fn (PageLayoutInterface $entity) => !$entity->isDefault());
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
    $row['profile_id']['data']['#plain_text'] = $entity->getProfile()?->label() ?? '?';
    $row['conditions']['data'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $this->getConditionsSummary($entity),
    ];
    $status = $entity->status() ? $this->t('Enabled') : '❌ ' . $this->t('Disabled');
    $status = $entity->status() && empty($entity->getSources()) ? '❌ ' . $this->t('Empty') : $status;
    $row['status']['data']['#plain_text'] = $status;

    $row = $row + parent::buildRow($entity);
    $row['#attributes']['data-id'] = $entity->id();

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
    $page_layout = $entity;
    $manager = \Drupal::service('plugin.manager.display_buildable');
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $manager->createInstance('page_layout', ['entity' => $page_layout]);
    $operations = parent::getDefaultOperations($entity);
    $operations[] = [
      'title' => new TranslatableMarkup('Build display'),
      'weight' => -10,
      'url' => $buildable->getBuilderUrl(),
    ];

    if ($entity->hasLinkTemplate('duplicate-form')) {
      $operations['duplicate'] = [
        'title' => $this->t('Duplicate'),
        'weight' => 15,
        'url' => $entity->toUrl('duplicate-form'),
      ];
    }

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
   * Add default page layouts to the entities table footer.
   *
   * @param array<string,mixed> $build
   *   A renderable array.
   *
   * @return array<string,mixed>
   *   The altered renderable array.
   */
  private function addDefaultPageLayouts(array $build): array {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface[] $entities */
    $entities = parent::load();
    $default_layouts = \array_filter($entities, static fn (PageLayoutInterface $entity) => $entity->isDefault());

    foreach ($default_layouts as $entity_id => $entity) {
      $row = $this->buildRow($entity);
      unset($row['weight'], $row['#weight'], $row['#attributes']);
      $build['entities']['#footer'][$entity_id]['data'] = $row;
      $build['entities']['#footer'][$entity_id]['style'] = 'font-weight: normal';
    }

    $message = $this->getUncoveredPagesMessage($default_layouts);

    if ($message !== NULL) {
      $this->messenger()->addStatus($message);
    }

    return $build;
  }

  /**
   * Says what still builds the pages no layout in the list matches.
   *
   * The UI only ever offers one default layout, but the API does not stop code
   * from creating several, and sites in production already have. So a single
   * working one is enough, and it is not necessarily the first.
   *
   * The ways to have none are worth telling apart: one is work not started, the
   * others are work left unfinished, and each has a different next step.
   *
   * @param \Drupal\display_builder_page_layout\PageLayoutInterface[] $default_layouts
   *   The page layouts carrying no condition.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The message, or NULL when a default layout covers those pages.
   */
  private function getUncoveredPagesMessage(array $default_layouts): ?TranslatableMarkup {
    $params = ['@url' => Url::fromRoute('block.admin_display')->toString()];

    if ($default_layouts === []) {
      $params['@create'] = Url::fromRoute('entity.page_layout.add_default_form')->toString();

      return $this->t('Pages matched by no layout below are still built by <a href="@url">Block Layout</a>. <a href="@create">Create the default page layout</a> to take them over.', $params);
    }

    foreach ($default_layouts as $default_layout) {
      if ($default_layout->status() && !empty($default_layout->getSources())) {
        return NULL;
      }
    }

    if (\count($default_layouts) > 1) {
      return $this->t('No default page layout is both enabled and built, so pages matched by no layout below are still built by <a href="@url">Block Layout</a>. Enable one and build its display to take them over.', $params);
    }

    $default_layout = \reset($default_layouts);
    $params['%label'] = $default_layout->label();

    if (empty($default_layout->getSources())) {
      return $this->t('The default page layout %label is empty, so pages matched by no layout below are still built by <a href="@url">Block Layout</a>. Build its display to take them over.', $params);
    }

    return $this->t('The default page layout %label is disabled, so pages matched by no layout below are still built by <a href="@url">Block Layout</a>. Enable it to take them over.', $params);
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
