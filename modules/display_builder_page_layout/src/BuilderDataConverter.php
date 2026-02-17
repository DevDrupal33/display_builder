<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\block\BlockRepositoryInterface;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Convert data between page regions and Display Builder.
 */
class BuilderDataConverter {

  public function __construct(
    private BlockRepositoryInterface $blockRepository,
    private ThemeManagerInterface $themeManager,
    private ThemeInitializationInterface $themeInitialization,
    private ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Convert page regions to UI Patterns sources.
   *
   * @return array
   *   A single UI Patterns source data.
   */
  public function convertPage(): array {
    $sources = [
      'source_id' => 'page_layout',
      'source' => [
        'regions' => [],
      ],
    ];

    // Current theme is probably the admin one, so let's switch to the front
    // one before retrieving the blocks.
    $current_theme = $this->themeManager->getActiveTheme();
    $theme_name = $this->configFactory->get('system.theme')->get('default');
    $theme = $this->themeInitialization->getActiveThemeByName($theme_name);
    $this->themeManager->setActiveTheme($theme);

    foreach ($this->blockRepository->getVisibleBlocksPerRegion() as $region => $blocks) {
      foreach ($blocks as $block) {
        $source = $this->convertBlock($block->getPlugin());

        // Remove config related to the block config entity and keep only the
        // properties from the plugin itself.
        if ($block_id = $source['source']['plugin_id'] ?? NULL) {
          unset(
            $source['source'][$block_id]['id'],
            $source['source'][$block_id]['label'],
            $source['source'][$block_id]['label_display'],
            $source['source'][$block_id]['provider'],
          );
        }

        $sources['source']['regions'][$region][] = $source;
      }
    }

    // Restore default theme after rendering.
    $this->themeManager->setActiveTheme($current_theme);

    return $sources;
  }

  /**
   * Convert block plugins.
   *
   * @param \Drupal\Core\Block\BlockPluginInterface $block
   *   The block plugin to convert.
   *
   * @return array
   *   A single UI Patterns source data.
   */
  private function convertBlock(BlockPluginInterface $block): array {
    $block_id = $block->getPluginId();

    if ($block_id === 'system_main_block') {
      return [
        'source_id' => 'main_page_content',
        'source' => [],
      ];
    }

    if ($block_id === 'page_title_block') {
      return [
        'source_id' => 'page_title',
        'source' => [],
      ];
    }

    return [
      'source_id' => 'block',
      'source' => [
        'plugin_id' => $block_id,
        $block_id => $block->getConfiguration(),
      ],
    ];
  }

}
