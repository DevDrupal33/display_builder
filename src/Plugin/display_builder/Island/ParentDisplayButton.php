<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;

/**
 * Help parent link island plugin implementation.
 */
#[Island(
  id: 'parent_display',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Parent display link'),
  description: new TranslatableMarkup('Allow a direct link to parent display.'),
  type: IslandType::Button,
)]
class ParentDisplayButton extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    if (!$builder->canSaveContextsRequirement()) {
      return [];
    }

    $url = self::findParentDisplayFromId((string) $builder->id());

    if (!$url) {
      return [];
    }

    $button = [
      '#type' => 'component',
      '#component' => 'display_builder:button',
      '#props' => [
        'icon' => 'box-arrow-up',
        'tooltip' => $this->t('Go to the parent display that manage this instance.'),
      ],
      '#attributes' => [
        'href' => $url->toString(),
      ],
    ];

    return $button;
  }

  /**
   * Simply determine the parent display type from id.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return \Drupal\Core\Url|null
   *   The url of the instance.
   */
  private static function findParentDisplayFromId(string $instance_id): ?Url {
    $id_values = \explode('__', $instance_id);

    switch ($id_values[0]) {
      case 'entity_view':
        return EntityViewDisplay::getDisplayUrlFromInstanceId($instance_id);

      case 'page_layout':
        return PageLayout::getDisplayUrlFromInstanceId($instance_id);

      case 'view':
        return DisplayExtender::getDisplayUrlFromInstanceId($instance_id);

      default:
        return NULL;
    }
  }

}
