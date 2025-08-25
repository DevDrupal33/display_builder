<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Field;

use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;
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
final class DisplayBuilderItemList extends MapFieldItemList implements WithDisplayBuilderInterface {

  /**
   * The state manager.
   */
  protected ?StateManagerInterface $stateManager = NULL;

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
    // Example: entity_view_override__node__1__field_teaser.
    if (!\str_starts_with($instance_id, 'entity_view_override__')) {
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
    // Example: entity_view_override__node__1__field_teaser.
    [, $entity_type_id, $entity_id, $field_name] = \explode('__', $instance_id);

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    $display = self::getEntityViewDisplay($entity_type_id, $entity->bundle(), $field_name);
    $params = [
      $entity_type_id => $entity_id,
      'view_mode_name' => $display->getMode(),
    ];

    return Url::fromRoute("entity.{$entity_type_id}.display_builder.{$display->getMode()}", $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface {
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

    return \sprintf('entity_view_override__%s__%s__%s',
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
    $data = $this->stateManager()->getCurrentState($this->getInstanceId());
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
    $instance_id = $this->getInstanceId();
    // One instance in State API by entity view display entity.
    $instance = $this->stateManager()->load($instance_id);

    if ($instance !== NULL) {
      // The instance already exists in State Manager, so nothing to do.
      return;
    }
    // Init instance if missing in State Manager because new or deleted in the
    // State API.
    $contexts = $this->initContexts();
    // Get the sources stored in config.
    $sources = $this->getSources();
    $entity = $this->getEntity();

    if (\count($sources) === 0) {
      \assert(\is_string($this->getName()));
      $display = self::getEntityViewDisplay($entity->getEntityTypeId(), $entity->bundle(), $this->getName());

      if ($display->getDisplayBuilder() !== NULL) {
        $sources = $display->getSources();
      }
    }
    $this->stateManager()->create($instance_id, (string) $this->getDisplayBuilder()->id(), $sources, $contexts);
  }

  /**
   * Initialize contexts for this item list.
   *
   * @return array<\Drupal\Core\Plugin\Context\ContextInterface>
   *   The contexts.
   */
  protected function initContexts(): array {
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
   * Get the state manager.
   *
   * @return \Drupal\display_builder\StateManager\StateManagerInterface
   *   The state manager.
   */
  protected function stateManager(): StateManagerInterface {
    return $this->stateManager ??= \Drupal::service('display_builder.state_manager');
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
