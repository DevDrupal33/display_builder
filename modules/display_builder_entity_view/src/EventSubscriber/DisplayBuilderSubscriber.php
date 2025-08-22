<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected StateManagerInterface $stateManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DisplayBuilderEvents::ON_SAVE => 'onSave',
    ];
  }

  /**
   * Event handler for when a display builder is saved.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onSave(DisplayBuilderEvent $event): void {
    $builder_id = $event->getBuilderId();
    $contexts = $this->stateManager->getContexts($builder_id);

    if (\str_starts_with($builder_id, 'entity_view_override__')) {
      [, $entity_type_id, $entity_id, $field_name] = \explode('__', $builder_id);
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($entity_type_id)
        ->load($entity_id);
      /** @var \Drupal\display_builder\WithDisplayBuilderInterface $with_display_builder */
      $with_display_builder = $entity->get($field_name);
      $with_display_builder->saveSources();
    }
    elseif (\str_starts_with($builder_id, 'entity_view__')) {
      if (!$this->stateManager->hasSaveContextsRequirement($builder_id, EntityViewDisplay::getContextRequirement(), $contexts)) {
        return;
      }
      // Entity view display parameters are also in route match.
      /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $display */
      $display = $this->getEntityViewDisplayEntity($contexts['entity'], $contexts['view_mode']);

      if ($display) {
        $display->saveSources();
      }
    }
  }

  /**
   * Get entity view display entity.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface $entity_context
   *   The entity context.
   * @param \Drupal\Core\Plugin\Context\ContextInterface $view_mode_context
   *   The view mode context.
   *
   * @return \Drupal\display_builder\WithDisplayBuilderInterface|null
   *   The entity view display entity or NULL if not found.
   */
  protected function getEntityViewDisplayEntity(ContextInterface $entity_context, ContextInterface $view_mode_context): ?WithDisplayBuilderInterface {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_context->getContextValue();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $view_mode = $view_mode_context->getContextValue();
    $display_id = "{$entity_type_id}.{$bundle}.{$view_mode}";

    /** @var \Drupal\display_builder\WithDisplayBuilderInterface|null $display */
    $display = $this->entityTypeManager->getStorage('entity_view_display')
      ->load($display_id);

    return $display;
  }

}
