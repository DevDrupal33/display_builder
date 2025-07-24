<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\Component\Plugin\Exception\ContextException;
use Drupal\Component\Plugin\Exception\MissingValueContextException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Condition\ConditionAccessResolverTrait;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the page layout entity type.
 *
 * @see \Drupal\display_builder_page_layout\Entity\PageLayout
 */
class AccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  use ConditionAccessResolverTrait;

  /**
   * The plugin context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The context manager service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    ContextHandlerInterface $context_handler,
    ContextRepositoryInterface $context_repository,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($entity_type);
    $this->contextHandler = $context_handler;
    $this->contextRepository = $context_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): AccessControlHandler {
    return new static(
      $entity_type,
      $container->get('context.handler'),
      $container->get('context.repository'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Load the first suitable Page Layout entity.
   *
   * A 'suitable' Page Layout is an entity which is enabled, meeting the right
   * conditions, and with non-empty sources.
   *
   * @return ?PageLayoutInterface
   *   A Page Layout entity.
   */
  public function loadCurrentPageLayout(): ?PageLayoutInterface {
    $storage = $this->entityTypeManager->getStorage('page_layout');
    $entity_ids = $storage->getQuery()->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface[] $page_layouts */
    $page_layouts = $storage->loadMultiple($entity_ids);

    foreach ($page_layouts as $page_layout) {
      if ($this->access($page_layout, 'view')) {
        return $page_layout;
      }
    }

    // If no suitable Page Layout found, PageVariantSubscriber will load the
    // usual Block Layout with theme's page.html.twig.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    if ($operation !== 'view') {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Don't grant access to disabled page layouts.
    if (!$entity->status() || empty($entity->getSources())) {
      return AccessResult::forbidden()->addCacheableDependency($entity);
    }

    $conditions = [];
    $missing_context = FALSE;
    $missing_value = FALSE;

    foreach ($entity->getConditions() as $condition_id => $condition) {
      if ($condition instanceof ContextAwarePluginInterface) {
        try {
          $contexts = $this->contextRepository->getRuntimeContexts(\array_values($condition->getContextMapping()));
          $this->contextHandler->applyContextMapping($condition, $contexts);
        }
        catch (MissingValueContextException) {
          $missing_value = TRUE;
        }
        catch (ContextException) {
          $missing_context = TRUE;
        }
      }
      $conditions[$condition_id] = $condition;
    }
    $access = $this->getAccessResult($missing_value, $missing_context, $conditions);
    $this->mergeCacheabilityFromConditions($access, $conditions);

    // Ensure that access is evaluated again when the entity changes.
    return $access->addCacheableDependency($entity);
  }

  /**
   * Get access result.
   */
  protected function getAccessResult(bool $missing_value, bool $missing_context, array $conditions): AccessResult {
    if ($missing_context) {
      // If any context is missing then we might be missing cacheable
      // metadata, and don't know based on what conditions the layout is
      // accessible or not. Make sure the result cannot be cached.
      $access = AccessResult::forbidden()->setCacheMaxAge(0);
    }
    elseif ($missing_value) {
      // The contexts exist but have no value. Deny access without
      // disabling caching. For example the node type condition will have a
      // missing context on any non-node route like the frontpage.
      $access = AccessResult::forbidden();
    }
    elseif ($this->resolveConditions($conditions, 'and') !== FALSE) {
      $access = AccessResult::allowed();
    }
    else {
      $reason = \count($conditions) > 1
      ? "One of the conditions ('%s') denied access."
      : "The  condition '%s' denied access.";
      $access = AccessResult::forbidden(\sprintf($reason, \implode("', '", \array_keys($conditions))));
    }

    return $access;
  }

  /**
   * Merges cacheable metadata from conditions onto the access result object.
   *
   * @param \Drupal\Core\Access\AccessResult $access
   *   The access result object.
   * @param \Drupal\Core\Condition\ConditionInterface[] $conditions
   *   List of visibility conditions.
   */
  protected function mergeCacheabilityFromConditions(AccessResult $access, array $conditions): void {
    foreach ($conditions as $condition) {
      if ($condition instanceof CacheableDependencyInterface) {
        $access->addCacheTags($condition->getCacheTags());
        $access->addCacheContexts($condition->getCacheContexts());
        $access->setCacheMaxAge(Cache::mergeMaxAges($access->getCacheMaxAge(), $condition->getCacheMaxAge()));
      }
    }
  }

}
