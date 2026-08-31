<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\UiPatterns\Source;

use Drupal\Core\Plugin\PreviewAwarePluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\RegionPlaceholderSourceTrait;
use Drupal\ui_patterns\Plugin\UiPatterns\Source\BlockSource as UiPatternsBlockSource;

/**
 * Stands in for a block that has no answer inside a builder.
 *
 * Drupal's page chrome blocks answer "where am I and what just happened",
 * which has no answer while a display is being built. Left alone they render
 * as nothing, or worse as the *builder's* own tabs and breadcrumb, a confident
 * lie about the real page. A node that renders as nothing is the one node the
 * user cannot select, move or delete, and no style applied to it is visible.
 *
 * The panels answer the same question for a block that rendered empty, but
 * they can only ask it about the nodes they render themselves. In the Preview
 * those are the root nodes, so a chrome block nested in a component's slot -
 * which is where a real page layout puts it - was invisible. Here the block
 * answers for itself, wherever UI Patterns resolves it, which is what a source
 * can do and a panel cannot.
 *
 * The builder says so with a source context, which every source already hands
 * to the sources it builds itself, so this reaches a block at any depth
 * without anything in between knowing about it.
 *
 * @see \Drupal\display_builder\RenderableBuilderTrait::needsPlaceholder()
 * @see \Drupal\ui_patterns\Element\ComponentElementBuilder::buildSource()
 */
class BlockSource extends UiPatternsBlockSource implements PreviewAwarePluginInterface {

  use RegionPlaceholderSourceTrait;

  /**
   * Block plugin IDs that never resolve inside a builder.
   *
   * Only two of these five are load-bearing, and knowing which matters before
   * anyone deletes the list as redundant:
   *
   * - system_breadcrumb_block renders the builder's own breadcrumb, 752 bytes
   *   of confident lie about the page being built. Not empty, wrong. No
   *   emptiness check will ever derive that; it is a judgement about what core
   *   does, which is what a list is for.
   * - system_messages_block renders an empty wrapper, which is markup as far
   *   as the renderer is concerned, so an emptiness check says FALSE.
   *
   * The other three are insurance. local_tasks_block and local_actions_block
   * render an empty string and help_block throws, so a panel rendering one of
   * them itself already catches it - but only if it renders that node itself.
   *
   * IDs only. What the placeholder says is derived from the block definition,
   * the same way it is for every other block.
   */
  private const FORCE_PLACEHOLDER = [
    'system_messages_block',
    'local_tasks_block',
    'local_actions_block',
    'system_breadcrumb_block',
    'help_block',
  ];

  /**
   * Whether a builder is rendering this rather than a real page.
   */
  protected bool $inPreview = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $plugin_id = $this->getSetting('plugin_id');

    // A prop expecting a scalar has nowhere to put a region, so it keeps the
    // block it asked for.
    if (!\is_string($plugin_id) || !\in_array($plugin_id, self::FORCE_PLACEHOLDER, TRUE) || !$this->isSlotProp()) {
      return parent::getPropValue();
    }

    // Outside a builder this is an ordinary page, where these blocks are
    // exactly what the visitor should get. That includes a display previewed
    // on its own page: the sub-request runs the real page pipeline, and its
    // real messages and tabs are the point of previewing it that way.
    if (!$this->inPreview) {
      return parent::getPropValue();
    }

    return $this->buildBlockRegion($plugin_id) ?? parent::getPropValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setInPreview(bool $in_preview): void {
    $this->inPreview = $in_preview;
  }

  /**
   * A named region standing in for a block that cannot resolve here.
   *
   * A block knows its own name, which beats whatever the source can say about
   * it. What the sentence promises is the same for all of them: the page
   * fills this in, so nothing here is worth configuring.
   *
   * @param string|null $plugin_id
   *   The block plugin ID, or NULL for a node that places no block.
   *
   * @return array|null
   *   A renderable array, or NULL when there is no such block to name.
   */
  private function buildBlockRegion(?string $plugin_id): ?array {
    if ($plugin_id === NULL || !$this->blockManager->hasDefinition($plugin_id)) {
      return NULL;
    }

    $definition = $this->blockManager->getDefinition($plugin_id);

    return $this->buildPlaceholderRegion(
      $definition['admin_label'] ?? $plugin_id,
      new TranslatableMarkup('This placeholder will be replaced by the page value.'),
    );
  }

}
