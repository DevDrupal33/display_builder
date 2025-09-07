<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface;
use Drupal\display_builder_entity_view\Entity\DisplayBuilderOverridableInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Defines an item list class for layout section fields.
 *
 * @internal
 *   Plugin classes are internal.
 *
 * @see \Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem
 *
 * phpcs:disable DrupalPractice.Objects.GlobalDrupal.GlobalDrupal
 */
final class DisplayBuilderItemList extends MapFieldItemList implements DisplayBuildableInterface {

  /**
   * The entity type manager.
   */
  protected ?EntityTypeManagerInterface $entityTypeManager;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    return 'entity_override__';
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
    \assert(\is_string($this->getName()));
    $entity = $this->getEntity();
    $entity_view = self::getEntityViewDisplay(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $this->getName(),
    );
    $entity_type_id = $entity_view->getTargetEntityTypeId();
    $parameters = [
      $entity_type_id => $entity->id(),
      'view_mode_name' => $entity_view->getMode(),
    ];

    return Url::fromRoute("entity.{$entity_type_id}.display_builder.{$entity_view->getMode()}", $parameters);
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
    $display = self::getEntityViewDisplay($entity_type_id, $entity->bundle(), $field_name);
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
    \assert(\is_string($this->getName()));
    $entity = $this->getEntity();

    $entity_view = self::getEntityViewDisplay(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $this->getName(),
    );
    \assert($entity_view instanceof DisplayBuilderOverridableInterface);

    return $entity_view->getDisplayBuilderOverrideProfile();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->getEntity()->isNew()) {
      return NULL;
    }

    $entity = $this->getEntity();

    return \sprintf('%s%s__%s__%s',
      self::getPrefix(),
      $entity->getEntityTypeId(),
      $entity->id(),
      $this->getName()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    $data = [];

    foreach ($this->list as $offset => $item) {
      $data[$offset] = $item->getValue();
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $data = $this->getInstance()->getCurrentState();
    $this->list = [];

    foreach ($data as $offset => $item) {
      $this->list[$offset] = $this->createItem($offset, $item);
    }
    $this->getEntity()->save();
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
    $entity = $this->getEntity();

    if (\count($sources) === 0) {
      \assert(\is_string($this->getName()));
      $display = self::getEntityViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $this->getName());

      if ($display->getProfile() !== NULL) {
        $sources = $display->getSources();
      }
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $entity = $this->getEntity();
    $bundle = $entity->bundle();
    \assert(\is_string($this->getName()));

    $view_mode = self::getEntityViewDisplay($entity->getEntityTypeId(), $bundle, $this->getName())->getMode();
    $contexts = [
      'entity' => EntityContext::fromEntity($entity),
      'bundle' => new Context(ContextDefinition::create('string'), $bundle),
      'view_mode' => new Context(ContextDefinition::create('string'), $view_mode),
    ];

    return RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
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

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  private function getInstance(): ?InstanceInterface {
    if (!isset($this->instance)) {
      /** @var \Drupal\display_builder\InstanceInterface|null $instance */
      $instance = $this->entityTypeManager()->getStorage('display_builder_instance')->load($this->getInstanceId());
      $this->instance = $instance;
    }

    return $this->instance;
  }

}
