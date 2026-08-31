<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Plugin;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\RegionPlaceholderSourceTrait;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Base class for the page regions a page layout places but does not fill.
 *
 * These sources have no value of their own. What they stand for arrives with
 * the page request: the title of the route being rendered, the display of the
 * entity being viewed. In a builder there is no request, so they would render
 * as nothing, and a node that renders as nothing is the one node the user
 * cannot select, move or delete.
 *
 * So they render a named region instead, and the two states are the same for
 * every one of them, which is the whole reason this base exists. What differs
 * per region is only what it is called, what fills it, and how much room that
 * deserves, so each subclass answers those three and nothing else.
 */
abstract class PageRegionSourceBase extends SourcePluginBase {

  use RegionPlaceholderSourceTrait;

  /**
   * The settings key the page pipeline wraps its payload in.
   *
   * The presence of this key is the whole signal, which is why it is a wrapper
   * rather than a value: a page whose main content is legitimately empty and a
   * builder where nothing was injected both arrive as an empty array, and only
   * one of them wants a placeholder. Anything that tests the payload itself
   * gets that page wrong.
   *
   * @see \Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant::replaceTitleAndContent()
   */
  public const PAGE_PROVIDED = 'display_builder_page_region';

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    return $this->buildRegionPlaceholder($this->regionLabel(), $this->regionHelp(), $this->regionSize());
  }

  /**
   * Get the value from settings.
   *
   * The settings are not configuration here, they are the payload. On a real
   * page PageLayoutPageVariant has already swapped this node's `source` for
   * what the request provides, wrapped in ::PAGE_PROVIDED, and that arrives as
   * the settings. Wrapped means injected, so it wins even when what it carries
   * is empty - a page whose main content renders to nothing is still a page,
   * and must not get the builder's placeholder.
   *
   * The key is absent only in the builder, where nothing replaced the node,
   * and that is the one case with a placeholder to show instead.
   *
   * ComponentElementBuilder resolves every prop and slot through ::getValue(),
   * so ::getPropValue() is only ever reached from the parent below - which is
   * called rather than ::getPropValue() directly so the placeholder still goes
   * through prop type conversion.
   *
   * @param \Drupal\ui_patterns\PropTypeInterface|null $prop_type
   *   The prop type.
   *
   * @see \Drupal\ui_patterns\SourcePluginBase::getValue()
   * @see \Drupal\display_builder_page_layout\Plugin\DisplayVariant\PageLayoutPageVariant::replaceTitleAndContent()
   * @see \Drupal\display_builder\DisplayBuilderHelpers::findArrayReplaceSource()
   */
  public function getValue(?PropTypeInterface $prop_type = NULL): mixed {
    $settings = $this->configuration['settings'] ?? [];

    if (\is_array($settings) && \array_key_exists(self::PAGE_PROVIDED, $settings)) {
      return $settings[self::PAGE_PROVIDED];
    }

    return parent::getValue($prop_type);
  }

  /**
   * The name shown in the region's place.
   *
   * The region's job, never a plugin id: "Main content", not
   * "main_page_content".
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The region name.
   */
  abstract protected function regionLabel(): TranslatableMarkup;

  /**
   * One sentence saying that the page fills this region, not the user.
   *
   * The same sentence for every region on purpose. Naming the region says
   * which one it is; the help only has to answer whether there is anything
   * here to configure, and for all of them the answer is no.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The help sentence.
   */
  protected function regionHelp(): TranslatableMarkup {
    return new TranslatableMarkup('This placeholder will be replaced by the page value.');
  }

  /**
   * The room the region gets in the Canvas.
   *
   * A CSS class suffix, and a floor rather than a height: 'md' is a strip,
   * 'lg' stands for a page's whole content area. The room a region gets is
   * part of what it says, so it belongs beside the words.
   *
   * @return string
   *   The size suffix.
   *
   * @see components/display_builder/css/display_builder.css
   */
  protected function regionSize(): string {
    return 'md';
  }

}
