<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Entity\EntityStorageInterface;
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
   * The display_builder_instance storage.
   */
  protected EntityStorageInterface $instanceStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');
    $instance->currentUser = $container->get('current_user');
    $instance->instanceStorage = $container->get('entity_type.manager')->getStorage('display_builder_instance');

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
      [$items, $capped] = $this->buildProviderItems($buildable, $builder);

      if (empty($items)) {
        continue;
      }

      // Wrapped so the filter can hide a whole group, heading included, once
      // every row inside it is filtered out. A heading with nothing under it
      // reads as "no results here" when the truth is "no results anywhere".
      $group = [
        '#type' => 'container',
        '#attributes' => ['class' => ['db-instances__group']],
        'title' => $this->buildGroupTitle($definition['label'], $buildable->collectDisplaysBound(), $buildable->getCollectionUrl(), $buildable->getAddUrl()),
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
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The instance currently being built, rendered without a link. Its own
   *   ::getHash() answers the current row's save status with no extra load.
   *
   * @return array{0: array, 1: int}
   *   The renderable list items sorted by label, and how many of them were
   *   capped out of sight past ::VISIBLE_COUNT.
   */
  protected function buildProviderItems(DisplayBuildableInterface $buildable, InstanceInterface $builder): array {
    $current_id = (string) $builder->id();
    $references = [];

    foreach ($buildable->collectDisplays() as $reference) {
      if (!$buildable::checkAccess($reference->instanceId, $this->currentUser)->isAllowed()) {
        continue;
      }

      $references[] = $reference;
    }

    \usort($references, static fn ($a, $b): int => \strnatcasecmp($a->label, $b->label));

    $hashes = $this->loadCurrentHashes($references, $current_id);
    $items = [];
    $shown = 0;

    foreach ($references as $reference) {
      $over_cap = $reference->built && ++$shown > self::VISIBLE_COUNT;
      $is_current = $reference->instanceId === $current_id;
      $issues = $this->resolveRowIssues($reference, $is_current, $builder, $hashes);
      $items[] = $this->buildItem($reference, $is_current, $over_cap, $issues);
    }

    return [$items, \max(0, $shown - self::VISIBLE_COUNT)];
  }

  /**
   * Batch-load the current ::hash of every built, non-current reference.
   *
   * One query for the whole list via ::loadMultiple(), not one per row: the
   * N+1 pattern this avoids is exactly what #3616451 flags elsewhere in this
   * area of the module.
   *
   * @param \Drupal\display_builder\DisplayReference[] $references
   *   The references this group is about to render.
   * @param string $current_id
   *   The instance id being built right now, excluded - its hash is read
   *   live off $builder instead, @see ::hasUnpublishedChanges().
   *
   * @return array<string, int|null>
   *   Instance id to its stored ::getHash(), for every loaded instance.
   */
  protected function loadCurrentHashes(array $references, string $current_id): array {
    $ids = [];

    foreach ($references as $reference) {
      if ($reference->built && $reference->instanceId !== $current_id) {
        $ids[] = $reference->instanceId;
      }
    }

    if ($ids === []) {
      return [];
    }

    $hashes = [];

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    foreach ($this->instanceStorage->loadMultiple($ids) as $instance) {
      $hashes[(string) $instance->id()] = $instance->getHash();
    }

    return $hashes;
  }

  /**
   * Whether a row's saved state has not been published yet.
   *
   * The published state itself is not an issue: it is the common case, and a
   * mark on every row that is fine would be noise, not signal.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   * @param bool $is_current
   *   Whether this is the display being edited right now.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The instance currently being built.
   * @param array<string, int|null> $hashes
   *   Instance id to stored hash, from ::loadCurrentHashes().
   *
   * @return bool
   *   TRUE when the display is built, has a saved hash, and that hash
   *   differs from what is published.
   */
  protected function hasUnpublishedChanges(DisplayReference $reference, bool $is_current, InstanceInterface $builder, array $hashes): bool {
    if (!$reference->built) {
      return FALSE;
    }

    $hash = $is_current ? $builder->getHash() : ($hashes[$reference->instanceId] ?? NULL);

    return $hash !== NULL && $hash !== $reference->publishedHash;
  }

  /**
   * Every issue a row has right now, in priority order.
   *
   * One dot, not two mechanisms: this used to be a save-status dot plus a
   * separate italic status word for 'disabled'/'empty', two different visual
   * languages for what is really one question - does this row need a look?
   * Everything answering that question is collected here, so ::buildItem()
   * has exactly one thing to render for it. The order returned is also the
   * priority order ::buildStatusDot() reads its color from: 'disabled'
   * (nobody can reach this at all) and 'empty' (built but nothing published)
   * come from the same status, since a display is never both; 'unpublished'
   * can join either, or stand alone.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   * @param bool $is_current
   *   Whether this is the display being edited right now.
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The instance currently being built.
   * @param array<string, int|null> $hashes
   *   Instance id to stored hash, from ::loadCurrentHashes().
   *
   * @return string[]
   *   Issue keys ('disabled', 'empty', 'unpublished'), most severe first.
   */
  protected function resolveRowIssues(DisplayReference $reference, bool $is_current, InstanceInterface $builder, array $hashes): array {
    $issues = [];

    // 'empty' is read from saved config, and the one display whose saved
    // config the user is busy changing is this one, so the word would be
    // stale from the first edit until the next full page load: it would
    // announce "empty" over a display that was just published. 'disabled'
    // has no such problem - it is the page layout entity's own enabled bit,
    // untouched by editing the tree - so it stays visible on the current row.
    $status = $reference->status();

    if ($status !== NULL && !($is_current && $status === 'empty')) {
      $issues[] = $status;
    }

    if ($this->hasUnpublishedChanges($reference, $is_current, $builder, $hashes)) {
      $issues[] = 'unpublished';
    }

    return $issues;
  }

  /**
   * The sentence explaining one issue, for the status dot's tooltip.
   *
   * @param string $issue
   *   An issue key from ::resolveRowIssues().
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The sentence.
   */
  protected static function issueSentence(string $issue): TranslatableMarkup {
    // Every real arm named; the default only satisfies match's exhaustiveness
    // check against $issue's plain `string` type - ::resolveRowIssues() is
    // this method's one caller and never emits a fourth key, so this never
    // actually throws, it just refuses to silently print nothing for one.
    return match ($issue) {
      'disabled' => new TranslatableMarkup('This page layout is disabled: nobody can see it.'),
      'empty' => new TranslatableMarkup('Nothing is published here yet.'),
      'unpublished' => new TranslatableMarkup('Saved but not published: this draft differs from what is live.'),
      default => throw new \InvalidArgumentException(\sprintf('Unknown instances panel issue key "%s".', $issue)),
    };
  }

  /**
   * Build the dot prefixed to a row's name.
   *
   * Red, not amber, when 'disabled' is among the issues: disabled is the one
   * case nobody can reach the display at all, which reads as a different
   * order of problem than "reachable but not quite there yet" - amber covers
   * both 'empty' and 'unpublished' rather than adding a third hue, since
   * telling those two apart only matters once you are already reading the
   * tooltip. Not the toolbar save_status pip's green either: that pip fires
   * once, as feedback that an action just succeeded, and green fits
   * confirming that - this dot sits at rest in a list scanned from a
   * distance, saying "this one needs a look", which green would read
   * backwards for.
   *
   * @param string[] $issues
   *   Issue keys from ::resolveRowIssues(), most severe first.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildStatusDot(array $issues): array {
    $attributes = ['class' => ['db-instances__status-dot']] + self::buildDotStateAttributes($issues);

    if ($issues !== []) {
      [$severity, $label] = self::describeIssues($issues);

      $attributes['class'][] = 'db-instances__status-dot--' . $severity;
      $attributes['role'] = 'img';
      $attributes['aria-label'] = $label;
      $attributes['title'] = $label;
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => $attributes,
    ];
  }

  /**
   * Data attributes describing a row's dot after its own next action.
   *
   * Every row carries these - cheap to compute, and simpler than gating on
   * "is this the current row" - but only the current row's are ever read:
   * assets/js/instances.js scopes its lookup to
   * '.db-instances__item--current', because Publish, Restore and Revert
   * only ever apply to the display being edited right now.
   *
   * Exactly one of the three is ever available on a row at a time, and each
   * has a knowable effect on 'unpublished' - the only issue any of them can
   * change:
   * - Publish and Restore both end with the draft matching what is
   *   published (Restore overwrites the draft with the published data,
   *   Publish the reverse), clearing it.
   * - Revert - only ever available on an override display - deletes the
   *   published override outright and replaces the draft with the base
   *   display's sources, so the two can never agree again; it adds
   *   'unpublished' back. 'disabled' does not apply to an override display,
   *   and 'empty' is already suppressed for the current row by
   *   ::resolveRowIssues(), so 'unpublished' ends up the only issue.
   *
   * Knowing the result up front lets a small client script flip the dot
   * straight from whichever action's response arrives, instead of
   * round-tripping through the island/event system to rebuild this whole
   * panel.
   *
   * @param string[] $issues
   *   Issue keys from ::resolveRowIssues(), most severe first.
   *
   * @return array
   *   The data attribute pair for whichever action applies - just the
   *   severity one, empty, when that action would leave the dot with
   *   nothing to explain.
   *
   * @see assets/js/instances.js
   */
  protected static function buildDotStateAttributes(array $issues): array {
    [$prefix, $resulting] = \in_array('unpublished', $issues, TRUE)
      ? ['data-published-state', \array_values(\array_diff($issues, ['unpublished']))]
      : ['data-unpublished-state', [...$issues, 'unpublished']];

    if ($resulting === []) {
      return [$prefix => ''];
    }

    [$severity, $label] = self::describeIssues($resulting);

    return [
      $prefix => $severity,
      $prefix . '-label' => $label,
    ];
  }

  /**
   * The dot's severity and tooltip for a non-empty set of issues.
   *
   * @param string[] $issues
   *   Issue keys, most severe first. Never empty - a dot with nothing to
   *   report is the caller's job to short-circuit before reaching here.
   *
   * @return array{0: string, 1: string}
   *   The severity ('danger' or 'warning') and the joined tooltip sentence.
   */
  protected static function describeIssues(array $issues): array {
    $severity = \in_array('disabled', $issues, TRUE) ? 'danger' : 'warning';
    $sentences = \array_map(static fn (string $issue): string => (string) self::issueSentence($issue), $issues);

    return [$severity, \implode(' ', $sentences)];
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
   * @param string[] $issues
   *   Issue keys from ::resolveRowIssues(), most severe first.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildItem(DisplayReference $reference, bool $is_current, bool $over_cap = FALSE, array $issues = []): array {
    $build = $this->buildItemContainer($reference, $over_cap, $is_current);

    $build['status'] = $this->buildStatusDot($issues);

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

    if ($reference->settingsUrl !== NULL) {
      $build['action'] = $this->buildItemSettingsAction($reference, $reference->settingsUrl);
    }

    return $build;
  }

  /**
   * Build a row's outer container, empty of content.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   * @param bool $over_cap
   *   Whether this row sits past ::VISIBLE_COUNT.
   * @param bool $is_current
   *   Whether this is the display being edited right now.
   *
   * @return array
   *   A renderable array, ready for its content keys.
   */
  protected function buildItemContainer(DisplayReference $reference, bool $over_cap, bool $is_current): array {
    $classes = ['db-instances__item'];

    if (!$reference->built) {
      // What the toggle hides. @see ::buildNotBuiltToggle().
      $classes[] = 'db-instances__item--not-built';
    }

    if ($over_cap) {
      // What View more reveals. @see ::buildMoreButton().
      $classes[] = 'db-instances__item--over-cap';
    }

    if ($is_current) {
      // The whole row, not just its label: bold text alone reads as barely
      // different from the rows around it at this font size.
      $classes[] = 'db-instances__item--current';
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => $classes,
        'role' => 'listitem',
        'data-keywords' => self::itemKeywords($reference),
      ],
    ];
  }

  /**
   * Build a built row's Settings action.
   *
   * Where this display is configured, which is not where it is built: the
   * row itself opens the builder, this leaves for Manage display, the view
   * edit form, or the content an override belongs to.
   *
   * @param \Drupal\display_builder\DisplayReference $reference
   *   The display the row is for.
   * @param \Drupal\Core\Url $url
   *   The display's settings URL, i.e. $reference->settingsUrl narrowed
   *   non-null by the caller.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildItemSettingsAction(DisplayReference $reference, Url $url): array {
    return $this->buildAction(
      $url,
      'gear',
      $this->t('Settings'),
      $reference->label,
      $this->t('Settings of @display', ['@display' => $reference->label]),
    );
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
   * @param \Drupal\Core\Url|null $collection_url
   *   Where every display of this kind is managed, from
   *   ::getCollectionUrl(). NULL leaves the heading as plain text.
   * @param \Drupal\Core\Url|null $add_url
   *   Where a new display of this kind is added, from ::getAddUrl(). NULL
   *   shows no add action.
   *
   * @return array
   *   A renderable array.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface::collectDisplaysBound()
   */
  protected function buildGroupTitle(string|TranslatableMarkup $label, ?TranslatableMarkup $bound, ?Url $collection_url = NULL, ?Url $add_url = NULL): array {
    $heading = [
      '#type' => 'html_tag',
      '#tag' => 'h4',
    ];

    if ($collection_url === NULL) {
      $heading['#value'] = $label;
    }
    else {
      $heading['link'] = $this->buildGroupHeadingLink($label, $collection_url);
    }

    $title = [
      '#type' => 'container',
      '#attributes' => ['class' => ['db-instances__title']],
      'heading' => $heading,
    ];

    if ($add_url !== NULL) {
      $title['add'] = $this->buildAction(
        $add_url,
        'plus-lg',
        $this->t('Add'),
        (string) $label,
        $this->t('Add a new @kind', ['@kind' => $label]),
      );
    }

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
   * Build a group heading as a link to its buildable's collection page.
   *
   * Same button-as-link idiom as ::buildItemLabel(), plain instead of small:
   * the heading it replaces was plain text at heading size, and a link taking
   * over that role must still read as a heading, not shrink to a row's size.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The provider's label.
   * @param \Drupal\Core\Url $url
   *   The buildable's collection page, from ::getCollectionUrl().
   *
   * @return array
   *   A renderable array.
   */
  protected function buildGroupHeadingLink(string|TranslatableMarkup $label, Url $url): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:button',
      '#props' => [
        'label' => $label,
        'variant' => 'text',
      ],
      '#attributes' => [
        'class' => ['db-instances__title-link'],
        'title' => $this->t('All @kind', ['@kind' => $label]),
      ],
    ];
    self::applyHref($build, $url);

    return $build;
  }

}
