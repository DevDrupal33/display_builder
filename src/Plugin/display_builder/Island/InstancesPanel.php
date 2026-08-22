<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\DisplayReference;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Instances island plugin implementation.
 *
 * Sidebar panel listing every display the user may travel to, grouped by
 * buildable provider (entity view, page layout, view display...), with the
 * current one marked. It is the traversal primitive for the nesting problem:
 * a page is assembled from a page layout, a display, an override and a Views
 * display, each stored elsewhere and each edited in its own builder, and this
 * is how you move between them without leaving the tool.
 *
 * It lists displays, not instances. A display that is not built with Display
 * Builder yet is exactly the link in the chain a user gets stuck on, so those
 * are listed too, marked, and linked to Manage display instead.
 *
 * @see \Drupal\display_builder\DisplayBuildableInterface::collectDisplays()
 * @see \Drupal\display_builder_ui\InstanceListBuilder
 */
#[Island(
  id: 'instances',
  label: new TranslatableMarkup('Instances'),
  description: new TranslatableMarkup('List all displays available to build and jump to any of them.'),
  type: IslandType::View,
  region: 'sidebar',
  icon: 'files',
)]
class InstancesPanel extends IslandPluginBase {

  /**
   * How many built displays a group shows before it caps the rest.
   *
   * Only built displays count: the not-built ones are already governed by the
   * toggle, and counting them would leave a group showing three rows and a
   * button promising two hundred more nobody asked to see.
   */
  protected const VISIBLE_COUNT = 15;

  /**
   * How many more rows one click on View more reveals.
   */
  protected const REVEAL_STEP = 10;

  /**
   * The display buildable plugin manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $current_id = (string) $builder->id();
    $container_id = 'db-instances-' . $current_id;
    $groups = [];

    foreach ($this->displayBuildableManager->getDefinitions() as $plugin_id => $definition) {
      // Built with no configuration: listing is a question about the kind of
      // display, not about one of them, and a bare plugin still has every
      // service it needs to answer.
      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance((string) $plugin_id, []);
      [$items, $capped] = $this->buildProviderItems($buildable, $current_id);

      if (empty($items)) {
        continue;
      }

      // Wrapped so the filter can hide a whole group, heading included, once
      // every row inside it is filtered out. A heading with nothing under it
      // reads as "no results here" when the truth is "no results anywhere".
      $group = [
        '#type' => 'container',
        '#attributes' => ['class' => ['db-instances__group']],
        'title' => $this->buildGroupTitle($definition['label'], $buildable->collectDisplaysBound()),
        // A div with a role, not a ul: an admin theme has opinions about every
        // list on the page, and none of them are about this one. The role
        // keeps what the markup gave away, so a screen reader still counts
        // the rows.
        'list' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['db-instances__list'],
            'role' => 'list',
          ],
          'items' => $items,
        ],
      ];

      if ($capped > 0) {
        $group['more'] = $this->buildMoreButton($capped);
      }

      $groups[] = $group;
    }

    if (empty($groups)) {
      return [];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['db-instances']],
      '#attached' => ['library' => ['display_builder/instances']],
      'search' => $this->buildSearch($container_id),
      'show_not_built' => $this->buildNotBuiltToggle(),
      'groups' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => $container_id],
        'lists' => $groups,
      ],
    ];
  }

  /**
   * Build the filter input.
   *
   * Reuses the library panel's search, which is entirely data-attribute
   * driven, rather than growing a second implementation. Displays scale as
   * entity types times bundles times view modes, so a flat alphabetical list
   * stops working early on a real site.
   *
   * @param string $container_id
   *   The id of the element holding the rows to filter.
   *
   * @return array
   *   A renderable array.
   *
   * @see components/library_panel/search.js
   */
  protected function buildSearch(string $container_id): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:input',
      '#props' => [
        // Not 'search': Shoelace reflects `type` onto the host, where a
        // theme's `[type=search]` rule reaches it. @see assets/css/_reset.css.
        'variant' => 'text',
        // A placeholder is not an accessible name.
        'label' => $this->t('Filter the displays'),
        'placeholder' => $this->t('Filter displays'),
        // Same size as the library's, so the two panels show one control.
        'size' => 'medium',
        'autocomplete_off' => TRUE,
        'clearable' => TRUE,
        'icon' => 'search',
      ],
      '#attributes' => [
        // Not db-search-library: this panel is not a library, and the class is
        // one of the two the shared behavior binds to.
        'class' => ['db-search-filter'],
        'data-search-container-id' => $container_id,
        'data-elements-selector' => '.db-instances__item',
      ],
    ];
  }

  /**
   * Build the toggle revealing displays not built with Display Builder.
   *
   * Off by default. Listing every display that *could* be a level is what makes
   * the panel useful when the chain runs through one, but on a real site those
   * outnumber the built ones several times over, and a list where the displays
   * you actually work on are a minority is a list nobody scans.
   *
   * A checkbox and a CSS `:has()` rule, no JavaScript: the state is the
   * checkbox's own, so there is nothing to initialize, persist or restore, and
   * it keeps working in a pane that was rebuilt off-screen. Shoelace's, so a
   * theme styling every `input` and `label` on the page cannot reach it, and
   * so it brings its own label rather than needing one wrapped around it.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildNotBuiltToggle(): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:checkbox',
      '#props' => [
        'label' => $this->t('Not using Display Builder'),
        'size' => 'small',
      ],
      '#attributes' => [
        'class' => ['db-instances__toggle'],
      ],
    ];
  }

  /**
   * Build the list items of a single buildable provider.
   *
   * @param \Drupal\display_builder\DisplayBuildableInterface $buildable
   *   The buildable plugin, built with no configuration, doing the listing.
   * @param string $current_id
   *   The ID of the instance currently being built, rendered without a link.
   *
   * @return array{0: array, 1: int}
   *   The renderable list items sorted by label, and how many of them were
   *   capped out of sight past ::VISIBLE_COUNT.
   */
  protected function buildProviderItems(DisplayBuildableInterface $buildable, string $current_id): array {
    $references = [];

    foreach ($buildable->collectDisplays() as $reference) {
      if (!$buildable::checkAccess($reference->instanceId, $this->currentUser)->isAllowed()) {
        continue;
      }

      $references[] = $reference;
    }

    \usort($references, static fn ($a, $b): int => \strnatcasecmp($a->label, $b->label));

    $items = [];
    $shown = 0;

    foreach ($references as $reference) {
      $over_cap = $reference->built && ++$shown > self::VISIBLE_COUNT;
      $items[] = $this->buildItem($reference, $reference->instanceId === $current_id, $over_cap);
    }

    return [$items, \max(0, $shown - self::VISIBLE_COUNT)];
  }

  /**
   * Build one row.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display to link to.
   * @param bool $is_current
   *   Whether this is the display being edited right now.
   * @param bool $over_cap
   *   Whether this row sits past ::VISIBLE_COUNT, and so starts out hidden
   *   behind the group's View more button.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildItem(DisplayReference $reference, bool $is_current, bool $over_cap = FALSE): array {
    $classes = ['db-instances__item'];

    if (!$reference->built) {
      // What the toggle hides. @see ::buildNotBuiltToggle().
      $classes[] = 'db-instances__item--not-built';
    }

    if ($over_cap) {
      // What View more reveals. @see ::buildMoreButton().
      $classes[] = 'db-instances__item--over-cap';
    }

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => $classes,
        'role' => 'listitem',
        'data-keywords' => self::itemKeywords($reference),
      ],
    ];

    $build['label'] = self::buildItemLabel($reference, $is_current);

    // Not being built is the one thing here that is an action rather than a
    // state, and the one row whose destination differs: every other row opens a
    // builder, this one leaves for Manage display to enable one there. So it is
    // the row's only link, and it names the action instead of the state.
    if (!$reference->built) {
      $build['action'] = $this->buildAction(
        $reference->url,
        'plus-lg',
        $this->t('Build'),
        $reference->label,
        $this->t('Not using Display Builder. Enable it on the settings.'),
      );

      return $build;
    }

    // Never on the row being edited. A status is read from saved config, and
    // the one display whose saved config the user is busy changing is this
    // one, so the word here is stale from the first edit until the next full
    // page load: it announces "Empty" over a display that was just published.
    // The panel does not rebuild on builder events on purpose, since listing
    // every display of the site again after every mutation is not a price
    // worth paying for one word about the display already on screen.
    $status = $is_current ? NULL : $reference->status();
    $word = $status === NULL ? NULL : self::statusLabel($status);

    if ($word !== NULL) {
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $word,
        '#attributes' => [
          'class' => ['db-instances__status', 'db-instances__status--' . $status],
        ],
      ];
    }

    // Where this display is configured, which is not where it is built: the
    // row itself opens the builder, this leaves for Manage display, the view
    // edit form, or the content an override belongs to.
    if ($reference->settingsUrl !== NULL) {
      $build['action'] = $this->buildAction(
        $reference->settingsUrl,
        'gear',
        $this->t('Settings'),
        $reference->label,
        $this->t('Settings of @display', ['@display' => $reference->label]),
      );
    }

    return $build;
  }

  /**
   * Build a row's name, linked or not.
   *
   * Two rows carry no link, for different reasons: the one being edited is
   * already here, and the one nobody has built has no builder to travel to,
   * only the row's action. The rows are cut short in a narrow sidebar and the
   * disambiguating half of the name is the view mode at the end, so every
   * shape keeps the full string reachable through a title attribute.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   * @param bool $is_current
   *   Whether this is the display being edited right now.
   *
   * @return array
   *   A renderable array.
   */
  protected static function buildItemLabel(DisplayReference $reference, bool $is_current): array {
    $tooltip = self::itemTooltip($reference);

    if (!$is_current && $reference->built) {
      $build = [
        '#type' => 'component',
        '#component' => 'display_builder:button',
        '#props' => [
          'label' => $reference->label,
          // Flat and borderless: a bordered box per row turns a list of
          // fifteen displays into a wall, and this has to read as a list.
          'variant' => 'text',
        ],
        '#attributes' => [
          'class' => ['db-instances__link'],
          'size' => 'small',
          // Safe on a link: it is focusable, so the tooltip is not the
          // keyboard-inaccessible kind it would be on a span.
          'title' => $tooltip,
        ],
      ];
      self::applyHref($build, $reference->url);

      return $build;
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $reference->label,
      '#attributes' => [
        'class' => [$is_current ? 'db-instances__current' : 'db-instances__label'],
        'title' => $tooltip,
      ],
    ];
  }

  /**
   * Search terms for this panel's client-side filter.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   *
   * @return string
   *   Lowercase keywords.
   */
  protected static function itemKeywords(DisplayReference $reference): string {
    // Trimmed like every other producer of this attribute: a NULL detail
    // otherwise leaves a trailing space in the haystack the filter searches.
    return \strtolower(\trim(\sprintf(
      '%s %s %s %s',
      $reference->kind,
      $reference->label,
      $reference->instanceId,
      $reference->detail ?? '',
    )));
  }

  /**
   * The full name of a display, for a row too narrow to show it.
   *
   * Two lines when there is a detail: the row's own name is the display, and
   * the second line is what that display is of. Joining them with a separator
   * reads as one long name instead.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   *
   * @return string
   *   The tooltip text.
   */
  protected static function itemTooltip(DisplayReference $reference): string {
    return $reference->detail === NULL
      ? $reference->label
      : $reference->label . "\n" . $reference->detail;
  }

  /**
   * Build a row's trailing action link.
   *
   * An icon by default, because the row is narrow and the name it truncates is
   * what the user came for. The word appears beside it once the sidebar is
   * dragged wide enough to spend the room, which is the only place an icon
   * alone is a guess.
   *
   * The word alone is not a name: every one of these links carries the same
   * one, and what tells them apart is the display each acts on. An
   * `aria-label` cannot say so here, because it would sit on the `sl-button`
   * host and the thing that takes focus is the `<a>` in its shadow root. The
   * icon's own label reaches it, so the display rides there and the name reads
   * as the display then the action.
   *
   * @param \Drupal\Core\Url $url
   *   Where the link goes.
   * @param string $icon
   *   A Shoelace icon name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $word
   *   The action in one word, shown only in a wide enough sidebar. It stays in
   *   the accessible name at every width, clipped rather than removed.
   * @param string $display
   *   The name of the display this acts on, which is what tells one of these
   *   links from the next.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $title
   *   The tooltip, when it has more to say than the name: where a "Build" link
   *   goes is not what it is called, and an icon admits neither.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildAction(Url $url, string $icon, TranslatableMarkup $word, string $display, ?TranslatableMarkup $title = NULL): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:button',
      '#props' => [
        'label' => $word,
        'variant' => 'text',
        'icon' => $icon,
        // Its own slot, so the word can be clipped at a narrow width without
        // the icon going with it. @see assets/css/instances.css.
        'icon_position' => 'prefix',
        'icon_label' => $display,
      ],
      '#attributes' => [
        'class' => ['db-instances__action'],
        'size' => 'small',
        'title' => $title,
      ],
    ];
    self::applyHref($build, $url);

    return $build;
  }

  /**
   * Turn a button into a link to a URL.
   *
   * `sl-button` renders an `<a>` instead of a `<button>` as soon as it has an
   * href, so the row keeps link semantics without a themeable `<a>` of its
   * own. Generating the URL with its metadata rather than casting it keeps
   * whatever cacheability the route carries, which `#type: link` would have
   * collected on its own.
   *
   * @param array $build
   *   The button renderable, altered by reference.
   * @param \Drupal\Core\Url $url
   *   Where the button goes.
   */
  protected static function applyHref(array &$build, Url $url): void {
    $generated = $url->toString(TRUE);
    $build['#attributes']['href'] = $generated->getGeneratedUrl();
    $generated->applyTo($build);
  }

  /**
   * Build the button revealing the rows a group caps.
   *
   * @param int $capped
   *   How many rows are hidden right now.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildMoreButton(int $capped): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:button',
      '#props' => [
        'label' => $this->formatPlural($capped, 'View 1 more', 'View @count more'),
        'variant' => 'text',
      ],
      '#attributes' => [
        // Hidden by the shared filter while a query is active: a search runs
        // over the capped rows too, so there is nothing left to reveal then.
        // @see components/library_panel/search.js
        'class' => ['db-instances__more', 'db-filter-hide-on-search'],
        'size' => 'small',
        'data-step' => self::REVEAL_STEP,
      ],
    ];
  }

  /**
   * Build a group's heading, and the note about what its listing left out.
   *
   * Its own h4 rather than item_list's #title, which is an h3: the library
   * panel groups its own list under an h4, and two sidebar panels listing the
   * same kind of thing should not disagree about what level they sit at.
   *
   * The bound rides beside the heading as an icon rather than as a line under
   * the rows, because it qualifies the whole group, not its last row. It is
   * readable three ways: the tooltip on hover, the same string as the icon's
   * accessible name for assistive tech, and the tab stop that lets a keyboard
   * reach the tooltip a mouse gets for free.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The provider's label.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $bound
   *   The provider's own sentence about what it left out, if anything.
   *
   * @return array
   *   A renderable array.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface::collectDisplaysBound()
   */
  protected function buildGroupTitle(string|TranslatableMarkup $label, ?TranslatableMarkup $bound): array {
    $title = [
      '#type' => 'container',
      '#attributes' => ['class' => ['db-instances__title']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h4',
        '#value' => $label,
      ],
    ];

    if ($bound !== NULL) {
      $title['bound'] = [
        '#type' => 'html_tag',
        '#tag' => 'sl-tooltip',
        '#attributes' => [
          'content' => $bound,
          'placement' => 'bottom',
          'class' => ['db-instances__bound'],
        ],
        'icon' => [
          '#type' => 'html_tag',
          '#tag' => 'sl-icon',
          '#attributes' => [
            'name' => 'info-circle',
            'label' => $bound,
            'tabindex' => '0',
          ],
        ],
      ];
    }

    return $title;
  }

  /**
   * The word shown for a built display's status.
   *
   * An empty display is a dead link that looks like a bug unless it says so,
   * and a disabled page layout renders nowhere however full it is. Both are
   * states, so both are words. There is no third: a display that is not built
   * has an action rather than a status, and ::status() returns NULL for it.
   *
   * @param string $status
   *   A status key from DisplayReference::status().
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The word to show, or NULL for a key with no word of its own.
   */
  protected static function statusLabel(string $status): ?TranslatableMarkup {
    // Every arm named, no catch-all: a default arm here would render a status
    // key nobody has written a word for as "Empty", which is a lie about the
    // display rather than a missing label.
    return match ($status) {
      'disabled' => new TranslatableMarkup('Disabled'),
      'empty' => new TranslatableMarkup('Empty'),
      default => NULL,
    };
  }

}
