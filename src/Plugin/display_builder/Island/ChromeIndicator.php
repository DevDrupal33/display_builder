<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Island plugin implementation.
 *
 * Names, in the Preview pane's own header, whether the instance previews
 * wrapped in the site's chrome or bare - the one thing
 * \Drupal\display_builder\InstanceInterface::previewWithChrome() decides that
 * otherwise has no visible trace anywhere in the builder. Icon plus tooltip
 * rather than a text badge: "chrome" and "bare" are builder jargon, not
 * something a site editor should have to learn to read a status indicator.
 *
 * A pane_header Floating island rather than markup built inline by
 * PreviewPanel: it then rides in the same header row as the viewport switcher
 * for free (@see ViewportSwitcher), instead of needing its own absolute
 * positioning over the pane.
 */
#[Island(
  id: 'chrome_indicator',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Chrome indicator'),
  description: new TranslatableMarkup('Show whether the preview renders wrapped in the site chrome.'),
  type: IslandType::Floating,
  attach_to: ['preview'],
  pane_header: TRUE,
)]
class ChromeIndicator extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $with_chrome = $builder->previewWithChrome();

    if (!$with_chrome) {
      return [];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'sl-tooltip',
      '#attributes' => [
        'content' => $this->t("Shown inside the site's real header and footer, exactly how a visitor would see it."),
        'placement' => 'bottom-end',
        'class' => [
          'db-chrome-indicator',
        ],
      ],
      'icon' => [
        '#type' => 'html_tag',
        '#tag' => 'sl-icon',
        '#attributes' => [
          'name' => 'window',
          'label' => $this->t('Full page preview'),
        ],
      ],
    ];
  }

}
