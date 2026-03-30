<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\EventSubscriber;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
      DisplayBuilderEvents::ON_REVERT => 'onRevert',
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
   * Event handler for when a display builder override is reverted.
   *
   * Clears the entity field override, then reloads sources from the base
   * entity view display config so the instance reflects the default layout.
   *
   * @param \Drupal\display_builder\Event\DisplayBuilderEvent $event
   *   The event object.
   */
  public function onRevert(DisplayBuilderEvent $event): void {
    $instance = $event->getInstance();
    $instance_id = (string) $instance->id();
    $instanceInfos = EntityViewOverride::checkInstanceId($instance_id);

    if (!isset($instanceInfos['entity_type_id'], $instanceInfos['entity_id'], $instanceInfos['field_name'])) {
      return;
    }

    // Do not get the profile entity ID from Instance context because the
    // data stored there is not reliable yet.
    // See: https://www.drupal.org/project/display_builder/issues/3544545
    $entity = $this->entityTypeManager->getStorage($instanceInfos['entity_type_id'])
      ->load($instanceInfos['entity_id']);

    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    // Remove the saved state as the field values will be deleted.
    $instance->setNewPresent([], 'Revert 1/2: clear overridden data and save');
    $instance->save();
    $instance->setSave($instance->getCurrentState());

    // Clear field value.
    $entity->get($instanceInfos['field_name'])->setValue(NULL);
    $entity->save();

    $contexts = $instance->get('contexts')->first()->getValue();

    if (isset($contexts['view_mode']) && $contexts['view_mode'] instanceof ContextInterface) {
      $viewMode = $contexts['view_mode']->getContextValue();
      $display_id = "{$instanceInfos['entity_type_id']}.{$entity->bundle()}.{$viewMode}";

      /** @var \Drupal\display_builder\DisplayBuildableInterface|null $display */
      $display = $this->entityTypeManager->getStorage('entity_view_display')
        ->load($display_id);

      if ($display) {
        $instance->setNewPresent($display->getSources(), 'Revert 2/2: retrieve existing data from config');
        $instance->save();
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
