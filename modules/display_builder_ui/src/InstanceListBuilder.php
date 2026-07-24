<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\TableSort;
use Drupal\display_builder\DisplayBuildablePluginManager;
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
   * Cached list of display builder providers.
   */
  protected array $providers = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected DateFormatterInterface $dateFormatter,
    protected FormBuilderInterface $formBuilder,
    protected PagerManagerInterface $pagerManager,
    protected RequestStack $requestStack,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DisplayBuildablePluginManager $displayBuildableManager,
  ) {
    parent::__construct($entity_type, $storage);

    // Cache providers so we don't call invokeAll multiple times.
    $this->providers = $this->displayBuildableManager->getDefinitions();
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
    ];

    return $header + parent::buildHeader();
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

    /** @var \Drupal\display_builder\Plugin\Field\FieldType\PluginItem $item */
    $item = $instance->get('buildable')->first();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $item->getInstance();
    $type = $buildable->label() ?? '-';

    // Set a human readable name from id.
    $row['context']['data'] = $type;
    $row['context']['class'] = ['priority-medium'];

    $row['name']['data'] = $instance->label();
    $row['name']['class'] = ['priority-medium'];

    $row['profile']['data'] = $instance->getProfile()?->label() ?? '';

    $row['updated']['data'] = $instance->get('revision_created') ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $instance->get('revision_created')->getString()) : '-';
    $row['updated']['class'] = ['priority-medium', 'db-nowrap'];
    $row['log']['data'] = $instance->getRevisionLogMessage() ?: '-';
    $row['log']['class'] = ['priority-low'];

    $result = [
      'data' => $row + parent::buildRow($instance),
      'class' => $instance_id,
    ];

    return $result;
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.display_buildable'),
    );
  }

  /**
   * Retrieve filter values from session.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   Current session.
   *
   * @return array
   *   Associative array of filters.
   */
  public static function getSessionFilters(SessionInterface $session): array {
    $state = $session->get('db_instances_overview', []);
    $filters = $state['filters'] ?? [];

    return [
      'context' => isset($filters['context']) ? (string) $filters['context'] : '',
      'name' => isset($filters['name']) ? (string) $filters['name'] : '',
    ];
  }

  /**
   * Retrieve sort values from session.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   Current session.
   *
   * @return array
   *   Associative array with 'key' and 'direction'.
   */
  public static function getSessionSort(SessionInterface $session): array {
    $state = $session->get('db_instances_overview', []);

    return $state['sort'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function load(): array {
    $entities = $this->getInstancesFromProviders();

    // Apply filters from session and create missing instances if any.
    $entities = $this->filterEntities($entities);

    // Build headers & request once.
    $headers = $this->buildHeader();
    $request = $this->requestStack->getCurrentRequest() ?? \Drupal::request();
    $session = $this->requestStack->getSession();

    if ($request->query->has('order') || $request->query->has('sort')) {
      // Sort params are explicit in the URL — use and merge into session.
      $order = TableSort::getOrder($headers, $request);
      $direction = TableSort::getSort($headers, $request);
      $sortKey = $order['sql'] ?? 'updated';
      $state = $session->get('db_instances_overview', []);
      $state['sort'] = ['key' => $sortKey, 'direction' => $direction];
      $session->set('db_instances_overview', $state);
    }
    else {
      // No sort in URL — restore from session or fall back to default.
      $saved = self::getSessionSort($session);
      $sortKey = $saved['key'] ?? 'updated';
      $direction = $saved['direction'] ?? TableSort::DESC;
    }

    // Sort using a dedicated helper.
    $this->sortEntities($entities, $sortKey, $direction);

    // Apply pager and return the page slice.
    return $this->applyPager($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $request = $this->requestStack->getCurrentRequest() ?? \Drupal::request();

    // When sort is not in the URL, inject the effective sort (session or
    // default) into the request so TableSort marks the correct header column.
    if (!$request->query->has('order') && !$request->query->has('sort')) {
      $saved = self::getSessionSort($this->requestStack->getSession());
      $sortKey = $saved['key'] ?? 'updated';
      $direction = $saved['direction'] ?? TableSort::DESC;
      // TableSort matches 'order' against header 'data' labels (translated).
      $labelMap = [
        'name' => (string) $this->t('Instance'),
        'updated' => (string) $this->t('Updated'),
      ];
      $request->query->set('order', $labelMap[$sortKey] ?? (string) $this->t('Updated'));
      $request->query->set('sort', $direction);
    }

    $build = parent::render();

    $build['#attached']['library'][] = 'display_builder_ui/instance_list';

    $info = $this->t('Instances are existing displays (entity views, page layouts, views...) from configuration or currently under work.');

    $build['notice'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $info,
      '#attributes' => ['class' => ['description']],
      '#weight' => -11,
    ];

    $build['filters'] = $this->formBuilder->getForm(InstanceListFilterForm::class, $this->providers);
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
  public function getOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    /** @var \Drupal\display_builder\Plugin\Field\FieldType\PluginItem $field */
    $field = $entity->get('buildable')->first();
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $field->getInstance();
    $operations = [
      'build' => [
        'title' => new TranslatableMarkup('Build display'),
        'url' => $buildable::getUrlFromInstanceId((string) $entity->id()),
        'weight' => -1,
      ],
      'edit' => [
        'title' => new TranslatableMarkup('Edit display'),
        'url' => $buildable::getDisplayUrlFromInstanceId((string) $entity->id()),
        'weight' => 10,
      ],
    ];

    return \array_merge(
      $operations,
      parent::getOperations($entity),
    );
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

    $result = [];

    foreach ($entities as $entity) {
      if ($context !== '' && $context !== $entity['context']) {
        continue;
      }

      if ($name !== '' && !\str_contains($entity['id'] ?? '', $name)) {
        continue;
      }

      $result[] = $entity['instance'];
    }

    return $result;
  }

  /**
   * Get instances from providers definitions.
   *
   * @return array
   *   List of instances indexed by id.
   */
  private function getInstancesFromProviders(): array {
    $instances = [];

    foreach ($this->providers as $provider_id => $provider) {
      foreach ($provider['class']::collectInstances($this->entityTypeManager) as $instance_id => $instance) {
        $instances[$instance_id] = [
          'id' => $instance_id,
          'instance' => $instance,
          'context' => $provider_id,
        ];
      }
    }

    return $instances;
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
          $aTime = (int) ($a->get('revision_created')->getString() ?? 0);
          $bTime = (int) ($b->get('revision_created')->getString() ?? 0);

          // Default comparator is ascending, multiply by factor to handle desc.
          return $factor * ($aTime <=> $bTime);
        });

        break;

      case 'name':
        \usort($entities, static function ($a, $b) use ($factor) {
          $aName = $a->label();
          $bName = $b->label();

          // Use case-insensitive string comparison.
          return $factor * \strcasecmp($aName, $bName);
        });

        break;

      default:
        // Unknown sort: fallback to updated desc behavior for predictability.
        \usort($entities, static function ($a, $b) {
          return (int) ($b->get('revision_created')->getString() ?? 0) <=> (int) ($a->get('revision_created')->getString() ?? 0);
        });

        break;
    }
  }

}
