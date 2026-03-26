<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\EventSubscriber;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityView;
use Drupal\display_builder_entity_view\Plugin\display_builder\Buildable\EntityViewOverride;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DisplayBuildablePluginManager $displayBuildableManager,
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
    $instance = $event->getInstance();
    $instance_id = (string) $instance->id();
    $contexts = $instance->getContexts();

    // Entity view display overrides.
    if ($params = EntityViewOverride::checkInstanceId($instance_id)) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($params['entity_type_id'])
        ->load($params['entity_id']);
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $override */
      $override = $entity->get($params['field_name']);

      if ($override) {
        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('entity_view_override', ['field' => $override]);
        $buildable->saveSources();
      }
    }

    // Entity view displays.
    elseif (EntityView::checkInstanceId($instance_id)) {
      if (!$instance->hasSaveContextsRequirement(EntityView::getContextRequirement(), $contexts)) {
        return;
      }
      // Entity view display parameters are also in route match.
      $display = $this->getEntityViewDisplayEntity($contexts['entity'], $contexts['view_mode']);

      if ($display) {
        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance('entity_view', ['entity' => $display]);
        $buildable->saveSources();
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
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface|null
   *   The entity view display entity or NULL if not found.
   */
  protected function getEntityViewDisplayEntity(ContextInterface $entity_context, ContextInterface $view_mode_context): ?EntityDisplayInterface {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_context->getContextValue();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $view_mode = $view_mode_context->getContextValue();
    $display_id = "{$entity_type_id}.{$bundle}.{$view_mode}";

    /** @var \Drupal\Core\Entity\Display\EntityDisplayInterface|null $display */
    $display = $this->entityTypeManager->getStorage('entity_view_display')
      ->load($display_id);

    return $display;
  }

}
