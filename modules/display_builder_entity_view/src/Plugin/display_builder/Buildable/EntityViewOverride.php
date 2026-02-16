<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\display_builder\Buildable;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\InstanceStorageInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'entity_view_override',
  label: new TranslatableMarkup('Entity view override'),
  instance_prefix: 'entity_override__',
)]
final class EntityViewOverride extends DisplayBuildablePluginBase {

  /**
   * The time service.
   */
  protected ?TimeInterface $time;

  /**
   * The field items where the override is stored.
   */
  private FieldItemListInterface $field;

  /**
   * The display buildable plugin manager.
   */
  private DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * The overridden display.
   */
  private DisplayBuilderEntityDisplayInterface $display;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->field = $configuration['field'];
    \assert(\is_string($this->field->getName()));
    $entity = $this->field->getEntity();
    $this->display = self::getEntityViewDisplay(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $this->field->getName(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    return 'content';
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    \assert(\is_string($this->field->getName()));
    $entity = $this->field->getEntity();
    $entity_type_id = $this->display->getTargetEntityTypeId();
    $parameters = [
      $entity_type_id => $entity->id(),
      'view_mode_name' => $this->display->getMode(),
    ];

    return Url::fromRoute(\sprintf('entity.%s.display_builder.%s', $entity_type_id, $this->display->getMode()), $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::getPrefix())) {
      return NULL;
    }
    [, $entity_type_id, $entity_id, $field_name] = \explode('__', $instance_id);

    return [
      'entity_type_id' => $entity_type_id,
      'entity_id' => $entity_id,
      'field_name' => $field_name,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    [, $entity_type_id, $entity_id, $field_name] = \explode('__', $instance_id);

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    if (!$entity) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }

    $display = self::getEntityViewDisplay($entity_type_id, $entity->bundle(), $field_name);

    if (!$display) {
      return Url::fromRoute('entity.display_builder_instance.collection');
    }
    $params = [
      $entity_type_id => $entity_id,
      'view_mode_name' => $display->getMode(),
    ];

    $route_name = \sprintf('entity.%s.display_builder.%s', $entity_type_id, $display->getMode());

    return Url::fromRoute($route_name, $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    \assert($this->display instanceof DisplayBuilderOverridableInterface);

    return $this->display->getDisplayBuilderOverrideProfile();
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->field->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $data = $this->getInstance()->getCurrentState();
    $entity = $this->field->getEntity();

    if ($entity instanceof ContentEntityInterface) {
      $this->setRevision($entity);
    }
    $entity->save();
    $this->field->setValue($data);
    $this->field->getEntity()->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    [, $entity_type_id, $entity_id] = \explode('__', $instance_id);
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);

    if (!$entity) {
      return AccessResult::neutral();
    }

    return $entity->access('update', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceStorage $storage */
    $storage = $this->entityTypeManager()->getStorage('display_builder_instance');

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->load($this->getInstanceId());

    if (!$instance) {
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    $sources = $this->getSources();

    if (\count($sources) === 0) {
      \assert(\is_string($this->field->getName()));
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager()->createInstance('entity_view', ['entity' => $this->display]);

      if ($buildable->getProfile() !== NULL) {
        $sources = $buildable->getSources();
      }
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $entity = $this->field->getEntity();
    $bundle = $entity->bundle();
    \assert(\is_string($this->field->getName()));

    $view_mode = $this->display->getMode();
    $contexts = [
      'entity' => EntityContext::fromEntity($entity),
      'bundle' => new Context(ContextDefinition::create('string'), $bundle),
      'view_mode' => new Context(ContextDefinition::create('string'), $view_mode),
    ];

    return RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->field->getEntity()->isNew()) {
      return NULL;
    }

    $entity = $this->field->getEntity();

    return \sprintf(
      '%s%s__%s__%s',
      self::getPrefix(),
      $entity->getEntityTypeId(),
      $entity->id(),
      $this->field->getName()
    );
  }

  /**
   * Set revision if appropriate.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to set revision if appropriate.
   */
  public function setRevision(ContentEntityInterface $entity): void {
    $bundle = $entity->getBundleEntity();

    if ($bundle instanceof RevisionableEntityBundleInterface
      && !$bundle->shouldCreateNewRevision()
    ) {
      return;
    }

    $entity->setNewRevision();

    if ($entity instanceof RevisionLogInterface) {
      $entity->setRevisionLogMessage($this->t('Updated using Display Builder.')->render());
      $entity->setRevisionCreationTime($this->time()->getCurrentTime());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function collectInstances(InstanceStorageInterface $instanceStorage, ?EntityTypeManagerInterface $entityTypeManager = NULL): array {
    $instances = [];
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $storage = $entityTypeManager->getStorage('entity_view_display');
    $instance_storage = $entityTypeManager->getStorage('display_builder_instance');
    $entity_query = $entity_storage = [];

    foreach ($storage->loadMultiple() as $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $display_builder = $display->getThirdPartySettings('display_builder');

      if (!isset($display_builder[DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY], $display_builder[DisplayBuildableInterface::OVERRIDE_PROFILE_PROPERTY])) {
        continue;
      }

      $entity_type = $display->getTargetEntityTypeId();
      $field_name = $display_builder[DisplayBuildableInterface::OVERRIDE_FIELD_PROPERTY] ?? NULL;

      if (!$field_name) {
        continue;
      }
      $entity_storage[$entity_type] ??= $entityTypeManager->getStorage($entity_type);
      $entity_query[$entity_type] ??= $entity_storage[$entity_type]->getQuery()->accessCheck(FALSE);
      $instances = \array_merge($instances, self::collectInstancesByField($field_name, $entity_type, $instance_storage, $entity_query[$entity_type]));
    }

    return $instances;
  }

  /**
   * Collect instances by field storage.
   *
   * @param string $field_name
   *   Field name.
   * @param string $entity_type
   *   Entity type ID.
   * @param \Drupal\Core\Entity\EntityStorageInterface $instance_storage
   *   Instance entity storage handler.
   * @param \Drupal\Core\Entity\Query\QueryInterface $entity_query
   *   Entity query handler.
   *
   * @return array
   *   A associative array of Instance entities or null values.
   */
  protected static function collectInstancesByField(string $field_name, string $entity_type, EntityStorageInterface $instance_storage, QueryInterface $entity_query): array {
    $instances = [];
    $entity_query->exists($field_name);
    // QueryInterface::execute() returns an integer for count queries or an
    // array of ids.
    /** @var array $ids */
    $ids = $entity_query->execute();

    if (empty($ids)) {
      return [];
    }

    foreach ($ids as $id) {
      $instance_id = \sprintf(
        '%s%s__%s__%s',
        self::getPrefix(),
        $entity_type,
        $id,
        $field_name,
      );
      // We are OK with keeping the null values if the instance entity
      // doesn't exists in storage. So the caller can decide to create
      // the missing Instance entities.
      $instances[$instance_id] = $instance_storage->load($instance_id);
    }

    return $instances;
  }

  /**
   * Get the time service.
   *
   * @return \Drupal\Component\Datetime\TimeInterface
   *   The time service.
   */
  protected function time(): TimeInterface {
    return $this->time ??= \Drupal::service('datetime.time');
  }

  /**
   * Get the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager(): EntityTypeManagerInterface {
    return $this->entityTypeManager ??= \Drupal::service('entity_type.manager');
  }

  /**
   * Gets the display buildable manager.
   *
   * @return \Drupal\display_builder\DisplayBuildablePluginManager
   *   The manager for display buildable.
   */
  protected function displayBuildableManager(): DisplayBuildablePluginManager {
    return $this->displayBuildableManager ??= \Drupal::service('plugin.manager.display_buildable');
  }

  /**
   * Get entity view display entity.
   *
   * @param string $entity_type_id
   *   Entity type ID.
   * @param string $bundle
   *   Entity's bundle which support fields.
   * @param string $fieldName
   *   Field name of the display.
   *
   * @return \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface|null
   *   The corresponding entity view display.
   */
  private static function getEntityViewDisplay(string $entity_type_id, string $bundle, string $fieldName): ?DisplayBuilderEntityDisplayInterface {
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface[] $displays */
    $displays = \Drupal::entityTypeManager()->getStorage('entity_view_display')->loadByProperties([
      'targetEntityType' => $entity_type_id,
    ]);

    foreach ($displays as $display) {
      if ($display instanceof DisplayBuilderOverridableInterface
        && $display->getDisplayBuilderOverrideField() === $fieldName
        && $display->getTargetEntityTypeId()
        && $display->getTargetBundle() === $bundle
      ) {
        return $display;
      }
    }

    return NULL;
  }

}
