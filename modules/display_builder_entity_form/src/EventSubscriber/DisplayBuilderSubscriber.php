<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder_entity_form\Entity\EntityFormDisplay;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
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
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
    $contexts = $instance->getContexts();

    // Entity view displays.
    if (!EntityFormDisplay::checkInstanceId($builder_id)) {
      return;
    }

    if (!$instance->hasSaveContextsRequirement(EntityFormDisplay::getContextRequirement(), $contexts)) {
      return;
    }

    // Entity view display parameters are also in route match.
    /** @var \Drupal\display_builder\DisplayBuildableInterface|null $display */
    $display = $this->getEntityFormDisplayEntity($contexts['entity'], $contexts['form_mode']);

    if ($display) {
      $display->saveSources();
    }
  }

  /**
   * Get entity view display entity.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface $entity_context
   *   The entity context.
   * @param \Drupal\Core\Plugin\Context\ContextInterface $form_mode_context
   *   The view mode context.
   *
   * @return \Drupal\display_builder\DisplayBuildableInterface|null
   *   The entity view display entity or NULL if not found.
   */
  protected function getEntityFormDisplayEntity(ContextInterface $entity_context, ContextInterface $form_mode_context): ?DisplayBuildableInterface {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_context->getContextValue();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $form_mode = $form_mode_context->getContextValue();
    $display_id = "{$entity_type_id}.{$bundle}.{$form_mode}";

    /** @var \Drupal\display_builder\DisplayBuildableInterface|null $display */
    $display = $this->entityTypeManager->getStorage('entity_form_display')->load($display_id);

    return $display;
  }

}
