<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Lets a source explain, in its own words, why it rendered nothing.
 *
 * The Canvas's default explanation ("Configure it to make it visible") is
 * right for a node the user built and left unset. It is wrong for a source
 * that mirrors state it does not own: a view area is empty or not depending
 * on the view itself, not on anything this node can be configured with.
 *
 * An interface rather than a source tag on purpose: the main module cannot
 * name a submodule's source class directly (this module has no dependency on
 * ui_patterns_views), and a tag would go unnoticed on a source that forgets
 * to carry it - the view display sources keep the definitions of
 * ui_patterns_views, only their classes are swapped. Implementing this
 * interface cannot be missed the same way.
 *
 * @see \Drupal\display_builder\RenderableBuilderTrait::buildEmptyPlaceholder()
 */
interface EmptyPlaceholderHelpInterface {

  /**
   * One sentence explaining why this node can render empty.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The help text shown in the empty placeholder.
   */
  public function emptyPlaceholderHelp(): string|TranslatableMarkup;

}
