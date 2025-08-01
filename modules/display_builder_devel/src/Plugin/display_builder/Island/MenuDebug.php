<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Menu debug island plugin implementation.
 */
#[Island(
  id: 'menu_debug',
  label: new TranslatableMarkup('[Debug] Debug information'),
  description: new TranslatableMarkup('Provide debug information in the menu.'),
  type: IslandType::Menu,
)]
class MenuDebug extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    return [
      [
        '<pre class="debug-info"><code></code></pre>',
      ],
      [
        '#attached' => [
          'library' => ['display_builder_devel/contextual_menu_debug'],
        ],
      ],
    ];
  }

}
