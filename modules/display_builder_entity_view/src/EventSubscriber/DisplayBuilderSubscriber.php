<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\EventSubscriber;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\StorageProperties;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event subscriber for display builder islands.
 */
class DisplayBuilderSubscriber implements EventSubscriberInterface {

  public const CONTEXT_REQUIREMENT = 'display_builder_entity_view';

  public function __construct(
    protected StateManagerInterface $stateManager,
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

    if (!$this->stateManager->hasSaveContextsRequirement($builder_id, self::CONTEXT_REQUIREMENT, $contexts)) {
      return;
    }

    // Entity view display parameters are also in route match.
    /** @var \Drupal\Core\Plugin\Context\EntityContext $entity_context */
    $entity_context = $contexts['entity'];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_context->getContextValue();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $view_mode = $contexts['view_mode']->getContextValue();
    // Save to the config entity.
    // @todo Implement this.
    /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityViewDisplay $entity_view_display */
    // phpcs:disable DrupalPractice.Objects.GlobalClass.GlobalClass
    // @phpstan-ignore-next-line
    $entity_view_display = EntityViewDisplay::load("{$entity_type_id}.{$bundle}.{$view_mode}");

    if ($entity_view_display) {
      // Set the third_party_settings.
      $builder_data = $this->stateManager->getCurrentState($builder_id);
      $entity_view_display->setThirdPartySetting('display_builder', 'sources', $builder_data);
      $entity_view_display->setThirdPartySetting('display_builder', StorageProperties::ConfigEntityId->value, $this->stateManager->getEntityConfigId($builder_id));
      // Save the configuration entity.
      $entity_view_display->save();
    }
  }

}
