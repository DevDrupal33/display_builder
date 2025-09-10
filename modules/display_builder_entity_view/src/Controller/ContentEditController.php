<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Controller;

use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder\InstanceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a controller to update the content in enttiy view overrides.
 *
 * @internal
 *   Controller classes are internal.
 */
final class ContentEditController extends ApiController {

  /**
   * {@inheritdoc}
   */
  public function updateContent(Request $request, InstanceInterface $builder): array {
    $content = $request->request->all()['content'];
    $entity_type = $request->request->all()['entity_type'];
    $entity_id = $request->request->all()['entity_id'];

    if (isset($content['form_build_id'])) {
      unset($content['form_build_id'], $content['form_token'], $content['form_id']);
    }
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager()->getStorage($entity_type)->load($entity_id);

    // @todo process form submission instead of directly altering the entity.
    foreach ($content as $field_id => $field) {
      $entity->set($field_id, $field);
    }
    $entity->save();
    $this->builder = $builder;
    $this->islandId = 'content_edit';

    return $this->dispatchDisplayBuilderEventWithRenderApi(
      DisplayBuilderEvents::ON_CONTENT_UPDATE,
      NULL,
      NULL,
    );
  }

}
