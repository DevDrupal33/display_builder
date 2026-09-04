<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;

/**
 * Highlight toggle island plugin implementation.
 *
 * Floating control attached to the Canvas and Scaffold panes: both render
 * real component output that the highlighting annotates. Not the Navigator
 * pane - it is purely schematic, so its structure is already permanently
 * outlined and there's nothing left for a highlight to reveal
 * (@see components/layer/layer.css). In Scaffold it applies to the
 * real-rendered "layout" components only; the schematic cards alongside
 * them keep their own always-on treatment.
 *
 * A dropdown of independent checkboxes (Drop
 * zones/Components/Blocks/Visual spacing), not a single on/off button:
 * each kind of highlight - and the extra breathing room around them - is
 * useful on its own, so they're not mutually exclusive. Components and
 * blocks are split rather than one combined item since a canvas is often
 * dominated by one or the other, and highlighting both at once just adds
 * noise to whichever one you don't care about right now.
 *
 * Built directly here, following the same display_builder:dropdown +
 * display_builder:menu pattern as ViewportSwitcher's compact format. There
 * is no shared trait for Floating island buttons: each of the two builds
 * its own markup, and a trait with two callers wanting different markup
 * is not worth the indirection.
 *
 * @see assets/js/highlight.js
 * @see components/dropzone/dropzone.css
 */
#[Island(
  id: 'highlight',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Highlight'),
  description: new TranslatableMarkup('Highlight zones to ease drag and move around for the canvas and scaffold panels.'),
  type: IslandType::Floating,
  attach_to: ['builder', 'scaffold'],
)]
class HighlightToggle extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    // A plain title attribute, not tooltip (which wraps the trigger in a
    // Shoelace <sl-tooltip>): Floating UI/ computes a permanently broken
    // position for a tooltip that's part of the server-rendered HTML inside a
    // floating controls cluster.
    $button = $this->buildButton('', NULL, 'border');
    $button['#attributes']['title'] = $this->t('Highlight zones to ease drag and move around.');
    $button['#attributes']['size'] = 'small';

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#slots' => [
        'button' => $button,
        'content' => [
          '#type' => 'component',
          '#component' => 'display_builder:menu',
          '#props' => [
            'items' => [
              [
                // Special-cased in highlight.js: checks/unchecks the 4
                // items below together, and reflects whether all 4 are
                // already checked whenever the menu opens.
                'title' => $this->t('Select all'),
                'value' => 'all',
                'type' => 'checkbox',
              ],
              ['divider' => TRUE],
              [
                // A colored dot, not a functional icon: tinted per
                // category in dropzone.css so the menu itself previews
                // which color each checkbox controls, before checking
                // anything.
                'title' => $this->t('Drop zones'),
                'value' => 'slot',
                'type' => 'checkbox',
                'icon' => 'circle-fill',
              ],
              [
                'title' => $this->t('Components'),
                'value' => 'component',
                'type' => 'checkbox',
                'icon' => 'circle-fill',
              ],
              [
                'title' => $this->t('Blocks'),
                'value' => 'block',
                'type' => 'checkbox',
                'icon' => 'circle-fill',
              ],
              ['divider' => TRUE],
              [
                'title' => $this->t('Visual spacing'),
                'value' => 'space',
                'type' => 'checkbox',
              ],
            ],
          ],
          '#attributes' => [
            'class' => ['db-background'],
          ],
        ],
      ],
      '#attributes' => [
        // db-background: visual surface now that this floats over the
        // pane's own content, instead of sitting in the toolbar's chrome.
        'class' => ['db-background'],
        'data-highlight-menu' => TRUE,
        // Checking one box shouldn't close the menu - these are
        // independent toggles, not a single pick.
        'stay-open-on-select' => TRUE,
      ],
      '#attached' => [
        'library' => ['display_builder/highlight'],
      ],
    ];
  }

}
