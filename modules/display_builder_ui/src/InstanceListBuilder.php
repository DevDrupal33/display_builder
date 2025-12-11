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
use Drupal\Core\Utility\TableSort;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder_ui\Form\InstanceListFilterForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
        'field' => 'name',
        'sort' => 'asc',
        'class' => ['priority-medium'],
      ],
      'profile' => $this->t('Profile'),
      'updated' => [
        'data' => $this->t('Updated'),
        'field' => 'updated',
        'sort' => 'desc',
        'class' => ['priority-medium', 'db-nowrap'],
      ],
      'log' => [
        'data' => $this->t('Last log'),
        'class' => ['priority-low'],
      ],
      'save' => [
        'data' => $this->t('Save is present?'),
        'field' => 'save_is_present',
        'sort' => 'asc',
      ],
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

    // Build headers & request once.
    $headers = $this->buildHeader();
    $request = $this->requestStack->getCurrentRequest() ?? \Drupal::request();
    $order = TableSort::getOrder($headers, $request);
    $direction = TableSort::getSort($headers, $request);
    $sortKey = $order['sql'] ?? 'updated';

    // Sort using a dedicated helper.
    $this->sortEntities($entities, $sortKey, $direction);

    // Apply pager and return the page slice.
    return $this->applyPager($entities);
  }

  /**
   * Retrieve filter values from the current request (GET).
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   Current session.
   *
   * @return array
   *   Associative array of filters.
   */
  public static function getSessionFilters(SessionInterface $session): array {
    $filters = $session->get('db_instances_overview_filter', []);

    return [
      'context' => isset($filters['context']) ? (string) $filters['context'] : '',
      'name' => isset($filters['name']) ? (string) $filters['name'] : '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    // To avoid implementing EntityStorageInterface::getQuery().
    return \array_keys($this->getStorage()->loadMultiple());
  }

  /**
   * Sort the entities array in place according to provided sort key/direction.
   *
   * @param array $entities
   *   Entities to sort (passed by reference).
   * @param string $sortKey
   *   The SQL sort key from TableSort.
   * @param string|int $direction
   *   Sort direction value.
   */
  private function sortEntities(array &$entities, string $sortKey, $direction): void {
    // Factor to invert comparison when descending.
    $factor = ($direction === TableSort::DESC) ? -1 : 1;

    switch ($sortKey) {
      case 'updated':
        \usort($entities, static function ($a, $b) use ($factor) {
          $aTime = (int) ($a->present->time ?? 0);
          $bTime = (int) ($b->present->time ?? 0);

          // Default comparator is ascending, multiply by factor to handle desc.
          return $factor * ($aTime <=> $bTime);
        });

        break;

      case 'name':
        \usort($entities, static function ($a, $b) use ($factor) {
          $aName = self::extractEntityName((string) $a->id());
          $bName = self::extractEntityName((string) $b->id());

          // Use case-insensitive string comparison.
          return $factor * \strcasecmp($aName, $bName);
        });

        break;

      case 'save_is_present':
        \usort($entities, static function ($a, $b) use ($factor) {
          $aVal = $a->saveIsCurrent() ? 1 : 0;
          $bVal = $b->saveIsCurrent() ? 1 : 0;

          return $factor * ($aVal <=> $bVal);
        });

        break;

      default:
        // Unknown sort: fallback to updated desc behavior for predictability.
        \usort($entities, static function ($a, $b) {
          return (int) ($b->present->time ?? 0) <=> (int) ($a->present->time ?? 0);
        });

        break;
    }
  }

  /**
   * Apply Drupal pager to an array of entities.
   *
   * @param array $entities
   *   The full list of (already filtered & sorted) entities.
   *
   * @return array
   *   The paged slice of entities for the current page.
   */
  private function applyPager(array $entities): array {
    $total = \count($entities);
    $limit = (int) $this->limit;

    if ($limit <= 0 || $total <= $limit) {
      // No paging needed.
      return $entities;
    }

    $pager = $this->pagerManager->createPager($total, $limit);
    $current_page = $pager->getCurrentPage();
    $offset = $current_page * $limit;

    return \array_slice($entities, $offset, $limit, TRUE);
  }

  /**
   * Extract a human readable name from an instance id.
   *
   * Example: "provider__my_display" -> "My display"
   *
   * @param string $instance_id
   *   The instance id.
   *
   * @return string
   *   The extracted display name.
   */
  private static function extractEntityName(string $instance_id): string {
    $parts = \explode('__', $instance_id);

    if (\count($parts) > 1) {
      \array_shift($parts);

      return \ucfirst(\implode(' ', $parts));
    }

    return $instance_id;
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
    $filters = self::getSessionFilters($this->requestStack->getSession());
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
