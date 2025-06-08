<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_page_layout\DisplayBuilderPageLayout;
use Drupal\display_builder_page_layout\DisplayBuilderPageLayoutInterface;

/**
 * Returns responses for Display Builder ui routes.
 */
class DisplayBuilderPageLayoutController extends ControllerBase {

  public function __construct(
    private readonly StateManagerInterface $stateManager,
    private readonly DisplayBuilderPageLayoutInterface $pageLayoutManager,
  ) {}

  /**
   * Load the main display builder for page layout.
   *
   * @return array
   *   The display builder editable.
   */
  public function managePageLayout(): array {
    $page_layout_config = $this->config(DisplayBuilderPageLayout::PAGE_LAYOUT_CONFIG);
    $builder_config_id = $page_layout_config->get('builder_config_id');

    if ($builder_config_id === NULL || empty($builder_config_id)) {
      // @todo if config is deleted, get first available?
      throw new \LogicException('Missing display builder config id in the configuration.');
    }

    $builder_id = $page_layout_config->get('builder_id');

    if ($builder_id === NULL || empty($builder_id)) {
      throw new \LogicException('Missing builder id in the configuration.');
    }

    // If instance is deleted, create a new one for page layout.
    if ($this->stateManager->load($builder_id) === NULL) {
      $builder_id = $this->pageLayoutManager->newPageLayout();
    }

    $storage = $this->entityTypeManager()->getStorage('display_builder');
    /** @var \Drupal\display_builder\DisplayBuilderInterface $displayBuilderConfig */
    $displayBuilderConfig = $storage->load($builder_config_id);
    $contexts = $this->stateManager->getContexts($builder_id) ?? [];

    return $displayBuilderConfig->build($builder_id, $contexts);
  }

  /**
   * Load the main display builder for page layout.
   *
   * @param string $builder_id
   *   The builder instance id.
   *
   * @return array
   *   The display builder editable.
   */
  public function managePageManager(string $builder_id): array {
    if (empty($builder_id)) {
      throw new \LogicException('Missing builder id in the path.');
    }

    // @todo handle if this page manager has been deleted.
    if ($this->stateManager->load($builder_id) === NULL) {
      throw new \LogicException('Missing builder data associated, see page manager variant configuration.');
    }

    // Get the page manager config to have the configuration.
    $builder_config_id = $this->stateManager->getEntityConfigId($builder_id);

    $storage = $this->entityTypeManager()->getStorage('display_builder');
    /** @var \Drupal\display_builder\DisplayBuilderInterface $displayBuilderConfig */
    $displayBuilderConfig = $storage->load($builder_config_id);

    $contexts = $this->stateManager->getContexts($builder_id) ?? [];

    return $displayBuilderConfig->build($builder_id, $contexts);
  }

  /**
   * Generate a simple index of saved display builder.
   *
   * @return array
   *   A render array.
   */
  public function pageManagerIndex(): array {
    $build = [];
    $build['display_builder_table'] = [
      '#theme' => 'table',
      '#header' => [
        'id' => ['data' => $this->t('Id')],
        'display_builder_config' => ['data' => $this->t('Display config')],
        'updated' => ['data' => $this->t('Updated')],
        'log' => ['data' => $this->t('Last log')],
      ],
    ];

    $pages_related = NULL;

    try {
      $pages = $this->entityTypeManager()->getStorage('page');
      $build['display_builder_table']['#header']['used'] = ['data' => $this->t('Used in page')];
      $build['display_builder_table']['#header']['type'] = ['data' => $this->t('Display type')];

      /** @var \Drupal\page_manager\PageInterface $page */
      foreach ($pages->loadMultiple() as $page) {
        foreach ($page->getVariants() as $variant) {
          if (!isset($variant->get('variant_settings')['display_builder_id'])) {
            continue;
          }
          $pages_related[$variant->get('variant_settings')['display_builder_id']] = [
            'type' => $variant->getVariantPlugin()->getPluginId(),
            'label' => $page->label(),
            'id' => $page->id(),
            'url' => $page->toUrl(),
          ];
        }
      }
    }
    catch (\Throwable $th) {
      // Throw new \Exception($th->getMessage());
    }

    $build['display_builder_table']['#header']['operations'] = ['data' => $this->t('Operations')];

    // Load all display builder used as page variant.
    foreach (array_keys($this->stateManager->loadAll()) as $builder_id) {
      if ($pages_related === NULL || !isset($pages_related[$builder_id])) {
        continue;
      }
      $build['display_builder_table']['#rows'][$builder_id] = $this->buildRow($builder_id, $this->stateManager->load((string) $builder_id), $pages_related);
    }

    $build['pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Provides a generic title callback for a display used in pages.
   *
   * @param string $builder_id
   *   The uniq display id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The title for the display page, if found.
   */
  public function title(string $builder_id): ?TranslatableMarkup {
    if (empty($builder_id)) {
      return $this->t('Display builder');
    }

    return $this->t('Display builder: @id', ['@id' => $builder_id]);
  }

  /**
   * Builds a table row for a display builder related to a page managed.
   *
   * @param string $builder_id
   *   The builder id.
   * @param array $builder
   *   An builder to display.
   * @param array $pages_related
   *   Array of page ids and builder type indexed by builder id.
   *
   * @return array
   *   A table row.
   */
  protected function buildRow(string $builder_id, array $builder, array $pages_related): array {
    $row = [];

    $row['id']['data'] = [
      '#type' => 'link',
      '#title' => $builder_id,
      '#url' => Url::fromRoute('display_builder_page_layout.page_manager.manage', ['builder_id' => $builder_id]),
    ];
    $row['display_builder_config']['data'] = $builder['entity_config_id'];
    $row['updated']['data'] = $builder['present']['time'] ?? 0;
    if (isset($builder['present']['log']) && $builder['present']['log'] instanceof TranslatableMarkup) {
      $row['log']['data'] = $this->formatLog($builder['present']['log']);
    }
    else {
      $row['log']['data'] = '-';
    }

    $label = $this->t('Unknown');

    if (isset($pages_related[$builder_id]['label'])) {
      $label = [
        '#type' => 'link',
        '#title' => \sprintf('%s (%s)', $pages_related[$builder_id]['label'], $pages_related[$builder_id]['id']),
        '#url' => $pages_related[$builder_id]['url'],
      ];
    }

    $row['used']['data'] = $label;
    $row['type']['data'] = $pages_related[$builder_id]['type'] ?? $label;

    $links = $this->getOperationLinks($builder_id, isset($pages_related[$builder_id]['page']) ? FALSE : TRUE);

    if (\count($links) > 0) {
      $row['operations']['data']['operations'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    return ['data' => $row];
  }

  /**
   * Format the log.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $log
   *   The log to format.
   *
   * @return string
   *   The formatted log.
   */
  private function formatLog(TranslatableMarkup $log): string {
    return $log->render();
  }

  /**
   * Delete a display builder.
   *
   * @param string $builder_id
   *   The display builder id.
   * @param bool $can_delete
   *   This display builder can be deleted (not attached to a page).
   *
   * @return array
   *   The operation links
   */
  private function getOperationLinks(string $builder_id, bool $can_delete = FALSE): array {
    $build = [
      'manage' => [
        'title' => $this->t('Manage'),
        'url' => Url::fromRoute('display_builder_page_layout.page_manager.manage', [
          'builder_id' => $builder_id,
        ]),
      ],
    ];

    if ($can_delete) {
      $build['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('display_builder_page_layout.page_manager.delete', [
          'builder_id' => $builder_id,
        ]),
      ];
    }

    return $build;
  }

}
