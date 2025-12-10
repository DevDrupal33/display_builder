<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder_ui\Form\InstanceListFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a listing of display builders instances.
 */
final class InstanceListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $limit = 20;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly FormBuilderInterface $formBuilder,
    private readonly PagerManagerInterface $pagerManager,
    private readonly RequestStack $requestStack,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('form_builder'),
      $container->get('pager.manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_instance_list_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'id' => [
        'data' => $this->t('ID'),
        'class' => ['hidden'],
      ],
      'context' => [
        'data' => $this->t('Context'),
        'class' => ['priority-medium'],
      ],
      'name' => [
        'data' => $this->t('Instance'),
        'class' => ['priority-medium'],
      ],
      'profile' => $this->t('Profile'),
      'updated' => [
        'data' => $this->t('Updated'),
        'class' => ['priority-medium', 'db-nowrap'],
      ],
      'log' => [
        'data' => $this->t('Last log'),
        'class' => ['priority-low'],
      ],
      'save' => $this->t('Save is present?'),
      'history' => $this->t('History (past - future)'),
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['#attached']['library'][] = 'display_builder_ui/instance_list';

    $build['notice'] = [
      '#markup' => '<p>' . $this->t('Instances are versions of displays (entity views, page layouts, views...) currently under work.') . ' '
      . $this->t('They are created automatically from the displays and must be managed from them.') . '</p>',
      '#weight' => -11,
    ];

    $providers = $this->moduleHandler->invokeAll('display_builder_provider_info');
    $build['filters'] = $this->formBuilder->getForm(InstanceListFilterForm::class, $providers);
    $build['filters']['#weight'] = -10;

    $build['pager'] = [
      '#type' => 'pager',
      '#weight' => 100,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $instance): array {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance_id = (string) $instance->id();

    $row = [];

    $row['id']['data'] = $instance_id;
    $row['id']['class'] = ['hidden'];

    $type = '-';
    $providers = $this->moduleHandler->invokeAll('display_builder_provider_info');

    foreach ($providers as $provider) {
      if (\str_starts_with($instance_id, $provider['prefix'])) {
        $type = $provider['label'];

        break;
      }
    }

    // Set a human readable name from id.
    $row['context']['data'] = $type;
    $row['context']['class'] = ['priority-medium'];
    $name = \explode('__', $instance_id);
    \array_shift($name);
    $row['name']['data'] = \ucfirst(\implode(' ', $name));
    $row['name']['class'] = ['priority-medium'];

    $row['profile']['data'] = $instance->getProfile()->label();

    /** @var \Drupal\display_builder\HistoryStep $present */
    $present = $instance->getCurrent();
    $row['updated']['data'] = $present->time ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $present->time) : '-';
    $row['updated']['class'] = ['priority-medium', 'db-nowrap'];
    $row['log']['data'] = $present->log ?? '-';
    $row['log']['class'] = ['priority-low'];

    $row['save']['data'] = $instance->saveIsCurrent() ? $this->T('Yes') : $this->t('No');
    $row['history']['data'] = \sprintf('%d - %d', \count($instance->past ?? 0), \count($instance->future ?? 0));

    $result = [
      'data' => $row + parent::buildRow($instance),
      'class' => $instance_id,
    ];

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();

    // Apply filters from session.
    $entities = $this->filterEntities($entities);

    // Sort depending on request 'sort' query (updated | id).
    $filters = $this->getSessionFilters();
    $sort = $filters['sort'] !== '' ? $filters['sort'] : 'updated_desc';

    if ($sort === 'updated_desc') {
      \usort($entities, static function ($a, $b) {
        return $b->present->time <=> $a->present->time;
      });
    }
    elseif ($sort === 'updated_asc') {
      \usort($entities, static function ($a, $b) {
        return $a->present->time <=> $b->present->time;
      });
    }
    else {
      \usort($entities, static function ($a, $b) {
        return \strcmp((string) $a->id(), (string) $b->id());
      });
    }

    // Initialize pager for the (filtered & sorted) results.
    $total = \count($entities);
    $limit = (int) $this->limit;

    $pager = $this->pagerManager->createPager($total, $limit);
    $current_page = $pager->getCurrentPage();
    $offset = $current_page * $limit;

    // Slice the entities array for the current page, preserve keys.
    if ($total > $limit) {
      $entities = \array_slice($entities, $offset, $limit, TRUE);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    // To avoid implementing EntityStorageInterface::getQuery()
    // @todo implement storage to have access to query and pager.
    return \array_keys($this->getStorage()->loadMultiple());
  }

  /**
   * Retrieve filter values from the current request (GET).
   *
   * Returns array with keys: context, name, sort.
   */
  private function getSessionFilters(): array {
    $filters = $this->requestStack->getSession()->get('db_instances_overview_filter', []);

    return [
      'context' => isset($filters['context']) ? (string) $filters['context'] : '',
      'name' => isset($filters['name']) ? (string) $filters['name'] : '',
      'sort' => isset($filters['sort']) ? (string) $filters['sort'] : '',
    ];
  }

  /**
   * Filter the loaded entities according to GET filters.
   *
   * @param array $entities
   *   Loaded entities.
   *
   * @return array
   *   Filtered entities.
   */
  private function filterEntities(array $entities): array {
    $filters = $this->getSessionFilters();
    $context = $filters['context'] ?? '';
    $name = $filters['name'] ?? '';

    if ($context === '' && $name === '') {
      return $entities;
    }

    $result = [];

    foreach ($entities as $entity) {
      $instance_id = (string) $entity->id();

      // Context filter: id starts with provider prefix.
      if ($context !== '' && !\str_starts_with($instance_id, $context)) {
        continue;
      }

      // Name filter: attempt to compute display name (same logic as buildRow).
      $parts = \explode('__', $instance_id);
      \array_shift($parts);
      $display_name = \ucfirst(\implode(' ', $parts));

      if ($name !== '' && \mb_stripos($display_name, $name) === FALSE) {
        continue;
      }

      $result[] = $entity;
    }

    return $result;
  }

}
