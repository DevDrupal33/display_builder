<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextInterface;

/**
 * Represents an entity field; that is, a list of field item objects.
 */
class ContextFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function setValue(mixed $values, mixed $notify = TRUE): void {
    if (\is_array($values) && !\array_is_list($values)) {
      $contexts = $values;
      $values = [];

      foreach ($contexts as $context_id => $context) {
        if (!($context instanceof ContextInterface)) {
          continue;
        }

        $values[] = [
          'id' => $context_id,
          'type' => $context->getContextDefinition()->getDataType(),
          'value' => $context->getContextData(),
        ];
      }
    }

    parent::setValue($values, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $values = [];

    foreach ($this->list as $item) {
      $value = $item->getValue();
      $definition = ContextDefinition::create($value['type']);
      $values[$value['id']] = new Context($definition, $value['value']);
    }

    return $values;
  }

}
