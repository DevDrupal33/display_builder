<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceStorageInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder_page_layout\PageLayoutInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'page_layout',
  label: new TranslatableMarkup('Page layout'),
  instance_prefix: 'page_layout__',
)]
final class PageLayout extends DisplayBuildablePluginBase {

  /**
   * The page layout entity storing the display.
   */
  public ?PageLayoutInterface $entity = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entity = $configuration['entity'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    return 'page';
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::getPrefix())) {
      return NULL;
    }
    [, $page_layout] = \explode('__', $instance_id);

    return [
      'page_layout' => $page_layout,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.page_layout.display_builder', ['page_layout' => $this->entity->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    return $account->hasPermission('administer page_layout') ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      // Fallback to the list of instances.
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('entity.page_layout.display_builder', $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::checkInstanceId($instance_id);

    if (!$params) {
      // Fallback to the list of instances.
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    return Url::fromRoute('entity.page_layout.edit_form', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    return $this->entity->getProfile();
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    $sources = $this->getSources();

    if (empty($sources)) {
      // Fallback to a fixture mimicking the standard page layout.
      $sources = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_page_layout', 'default_page_layout');
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $contexts = [];
    $contexts = RequirementsContext::addToContext([self::getContextRequirement()], $contexts);

    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->entity->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $this->entity->setSources($this->getInstance()->getCurrentState());
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function collectInstances(InstanceStorageInterface $instanceStorage, ?EntityTypeManagerInterface $entityTypeManager = NULL): array {
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $instance_storage = $entityTypeManager->getStorage('display_builder_instance');
    $instances = [];
    $displayBuildableManager = \Drupal::service('plugin.manager.display_buildable');
    $storage = $entityTypeManager->getStorage('page_layout');
    $entities = $storage->loadMultiple();

    foreach ($entities as $page_layout) {
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);
      $instance_id = $buildable->getInstanceId();
      // We are OK with keeping the null values if the instance entity
      // doesn't exists in storage. So the caller can decide to create
      // the missing Instance entities.
      $instances[$instance_id] = $instance_storage->load($instance_id);
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->entity->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', self::getPrefix(), $this->entity->id());
  }

}
