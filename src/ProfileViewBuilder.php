<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Theme\Registry;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View builder handler for display builder profiles.
 */
class ProfileViewBuilder extends EntityViewBuilder implements TrustedCallbackInterface {

  use RenderableBuilderTrait;

  /**
   * The entity we are building the view for.
   */
  protected ProfileInterface $entity;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityRepositoryInterface $entity_repository,
    LanguageManagerInterface $language_manager,
    Registry $theme_registry,
    EntityDisplayRepositoryInterface $entity_display_repository,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected IslandPluginManagerInterface $islandPluginManager,
  ) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.db_island'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    // We have 'hacked' the interface by using $view_mode as a way of passing
    // the Instance entity ID.
    $builder_id = $view_mode;

    /** @var \Drupal\display_builder\Entity\ProfileInterface $entity */
    $entity = $entity;
    $this->entity = $entity;

    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
    $buildable_id = $builder->get('buildable')->first()->get('plugin_id')->getValue() ?? '';
    $contexts = $builder->getAvailableContexts() ?? [];

    $islands_enabled_sorted = $this->getIslandsEnableSorted($contexts);
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:display_builder',
      '#props' => [
        'builder_id' => $builder_id,
        'buildable_id' => Html::getClass($buildable_id),
        'hash' => (string) $builder->getHash(),
      ],
      '#slots' => $this->buildSlots($builder, $islands_enabled_sorted),
      '#attached' => [],
      '#cache' => [
        'tags' => \array_merge(
          $builder->getCacheTags(),
          $entity->getCacheTags()
        ),
      ],
    ];

    foreach ($islands_enabled_sorted as $islands) {
      foreach ($islands as $island) {
        $build = $island->alterRenderable($builder, $build);
      }
    }

    return $build;
  }

  /**
   * Builds and returns the value of each slot.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param array $islands_enabled_sorted
   *   An array of enabled islands.
   *
   * @return array
   *   An associative array with the value of each slot.
   */
  private function buildSlots(InstanceInterface $builder, array $islands_enabled_sorted): array {
    $builder_data = $builder->getCurrentState();

    $button_islands = $islands_enabled_sorted[IslandType::Button->value] ?? [];
    $library_islands = $islands_enabled_sorted[IslandType::Library->value] ?? [];
    $contextual_islands = $islands_enabled_sorted[IslandType::Contextual->value] ?? [];
    $menu_islands = $islands_enabled_sorted[IslandType::Menu->value] ?? [];
    $view_islands = $islands_enabled_sorted[IslandType::View->value] ?? [];
    $preview_islands = $islands_enabled_sorted[IslandType::Preview->value] ?? [];
    $floating_islands = $islands_enabled_sorted[IslandType::Floating->value] ?? [];

    if (!empty($menu_islands)) {
      $menu_islands = $this->buildMenuWrapper($builder, $menu_islands);
    }

    if (!empty($library_islands)) {
      $library_islands = $this->entity->isLibraryFlat()
        ? $this->buildFlatLibraryPanels($builder, $library_islands, $builder_data)
        : [
          $this->buildDynamicTabs($builder, $library_islands, TRUE, $this->entity->getLibraryTabsDisplay()),
          $this->buildPanes($builder, $library_islands, $builder_data),
        ];
    }

    $view_islands_data = $this->prepareViewIslands($builder, $view_islands, $preview_islands, $floating_islands);
    $view_sidebar = $view_islands_data['view_sidebar'];
    $view_main = $view_islands_data['view_main'];

    $view_main_tabs = $view_islands_data['view_main_tabs'];

    // The preview toggle is a workspace-mode toolbar button (like expand),
    // pinning the Preview pane beside the active editor pane. It rides in the
    // toolbar's end region next to the other action buttons. @see js/split.js.
    $preview_toggle = $this->buildPreviewToggle($builder, $view_islands_data['view_main_islands']);
    $end_buttons = $this->buildButtons($builder, $button_islands);

    if (!empty($preview_toggle)) {
      $end_buttons = ['preview_toggle' => $preview_toggle] + $end_buttons;
    }

    // Library content can be in main or sidebar.
    // @todo Move the logic to LibrariesPanel::build().
    // @see https://www.drupal.org/project/display_builder/issues/3542866
    if (isset($view_sidebar['library']) && !empty($library_islands)) {
      $view_sidebar['library']['content'] = $library_islands;
    }
    elseif (isset($view_main['library']) && !empty($library_islands)) {
      $view_main['library']['content'] = $library_islands;
    }

    if (!empty($contextual_islands)) {
      $contextual_islands = $this->buildContextualIslands($builder, $islands_enabled_sorted);
    }

    return [
      'view_sidebar_buttons' => $view_islands_data['view_sidebar_buttons'],
      'view_sidebar' => $view_sidebar,
      'view_main_tabs' => $view_main_tabs,
      'view_main' => $view_main,
      'view_floating_controls' => $view_islands_data['view_floating_controls'],
      'start_buttons' => $this->buildButtons($builder, $button_islands, 'start'),
      'end_buttons' => $end_buttons,
      'contextual_islands' => $contextual_islands,
      'menu_islands' => $menu_islands,
    ];
  }

  /**
   * Build library panels merged into a single flat list with one search box.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $library_islands
   *   The enabled library islands.
   * @param array $builder_data
   *   The builder current state.
   *
   * @return array
   *   A two-element render array: the shared search input, and the panes
   *   wrapped in a container the search input targets.
   */
  private function buildFlatLibraryPanels(InstanceInterface $builder, array $library_islands, array $builder_data): array {
    $container_id = 'db-library-flat-' . $builder->id();

    $search = [
      '#type' => 'component',
      '#component' => 'display_builder:input',
      '#props' => [
        'variant' => 'search',
        // Names the field for assistive tech, which a placeholder cannot do.
        // Visually hidden, @see components/library_panel/search.css.
        'label' => $this->t('Search the library'),
        'placeholder' => $this->t('Search'),
        'size' => 'medium',
        'autocomplete_off' => TRUE,
        'clearable' => TRUE,
        'icon' => 'search',
      ],
      '#attributes' => [
        'class' => ['db-search-library'],
        'data-search-container-id' => $container_id,
        'data-elements-selector' => '.db-placeholder',
        'autofocus' => TRUE,
      ],
    ];

    $content = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => $container_id,
        'class' => ['db-library-flat'],
      ],
      'panes' => $this->buildPanes($builder, $library_islands, $builder_data, TRUE),
    ];

    return [$search, $content];
  }

  /**
   * Build buttons for a region.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $buttonIslands
   *   The button islands.
   * @param string $region
   *   The button region.
   *
   * @return array
   *   The buttons.
   */
  private function buildButtons(InstanceInterface $builder, array $buttonIslands, string $region = 'end'): array {
    $islands = [];

    foreach ($buttonIslands as $id => $island) {
      $islandRegion = $island->getConfiguration()['region'] ?? 'end';

      if ($islandRegion === $region) {
        $islands[$id] = $island;
      }
    }

    $buttons = [];

    if (!empty($islands)) {
      $buttons = $this->buildPanes($builder, $islands, [], FALSE, [], 'span');
    }

    return $buttons;
  }

  /**
   * Prepares view islands data.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param array $islands
   *   The sorted, enabled View islands.
   * @param array $preview_islands
   *   The sorted, enabled Preview islands. Preview is its own island type, not
   *   a View tab: these render in the main region as hidden panes, revealed
   *   only by the preview toggle. @see buildPreviewToggle().
   * @param array $floating_islands
   *   The sorted, enabled Floating islands, to attach to their target main
   *   View island(s), @see buildFloatingControlsRegion().
   *
   * @return array
   *   The prepared view islands data.
   */
  private function prepareViewIslands(InstanceInterface $builder, array $islands, array $preview_islands = [], array $floating_islands = []): array {
    $view_islands_sidebar = [];
    $view_islands_main = [];
    $view_sidebar_buttons = [];
    $view_main_tabs = [];

    foreach ($islands as $id => $island) {
      if ($island->getTypeId() !== IslandType::View->value) {
        continue;
      }

      $configuration = $island->getConfiguration();

      if (isset($configuration['region']) && $configuration['region'] === 'sidebar') {
        $view_islands_sidebar[$id] = $islands[$id];
        $view_sidebar_buttons[$id] = $islands[$id];
      }
      else {
        $view_islands_main[$id] = $islands[$id];
        $view_main_tabs[$id] = $islands[$id];
      }
    }

    // Preview panes are a distinct island type, never a View tab. They join the
    // main region so they render (hidden until the preview toggle reveals them.
    // @see js/split.js, and so pane_header Floating islands like the viewport
    // switcher, which attach to 'preview', find their target pane. They are
    // never added to $view_main_tabs, so they get no tab of their own.
    foreach ($preview_islands as $id => $island) {
      $view_islands_main[$id] = $island;
    }

    $view_panels_display = $this->entity->getViewPanelsDisplay();

    if (!empty($view_sidebar_buttons)) {
      $view_sidebar_buttons = $this->buildStartButtons($builder, $view_sidebar_buttons, $view_panels_display);
    }

    if (!empty($view_main_tabs)) {
      $view_main_tabs = $this->buildDynamicTabs($builder, $view_main_tabs, FALSE, $view_panels_display);
    }

    $builder_data = $builder->getCurrentState();
    $view_sidebar = $this->buildPanes($builder, $view_islands_sidebar, $builder_data);
    // Default hidden.
    $view_main = $this->buildPanes($builder, $view_islands_main, $builder_data, FALSE, ['shoelace-tabs__tab--hidden']);

    // pane_header Floating islands (e.g. the viewport switcher) render inside
    // their target pane as its first child, above the pane content.
    foreach ($this->buildPaneHeaders($builder, $view_islands_main, $floating_islands) as $pane_id => $header) {
      if (isset($view_main[$pane_id])) {
        $view_main[$pane_id] = ['pane_header' => $header] + $view_main[$pane_id];
      }
    }

    $view_floating_controls = $this->buildFloatingControlsRegion($builder, $view_islands_main, $floating_islands);

    return [
      'view_sidebar_buttons' => $view_sidebar_buttons,
      'view_main_tabs' => $view_main_tabs,
      'view_main_islands' => $view_islands_main,
      'view_sidebar' => $view_sidebar,
      'view_main' => $view_main,
      'view_floating_controls' => $view_floating_controls,
    ];
  }

  /**
   * Builds the preview toggle that pins the Preview pane beside the editor.
   *
   * Needs a Preview pane in the main region and at least one other pane to sit
   * next to; otherwise there is nothing to place it beside, so no toggle
   * renders.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $view_main_islands
   *   The enabled main-region islands (View tabs plus the Preview pane), keyed
   *   by plugin ID.
   *
   * @return array
   *   The toggle button render array, or an empty array when it does not apply.
   *
   * @see components/display_builder/js/split.js
   */
  private function buildPreviewToggle(InstanceInterface $builder, array $view_main_islands): array {
    if (!isset($view_main_islands['preview']) || \count($view_main_islands) < 2) {
      return [];
    }

    $island = $view_main_islands['preview'];
    $target = '#' . $island->getHtmlId((string) $builder->id());

    $attributes = [
      'class' => ['db-split-toggle'],
      'data-db-split-toggle' => TRUE,
      'data-split-target' => $target,
      'aria-pressed' => 'false',
    ];

    // Preview is neither a sidebar start button nor a main tab, so the two
    // places that normally carry an island's shortcut never see it: this
    // toggle is its only affordance, and therefore the only element that can
    // carry the key. @see components/display_builder/js/keyboard.js, which
    // maps every [data-keyboard-key] under the builder and clicks the match.
    if ($keyboard = $island::keyboardShortcuts()) {
      $attributes['data-keyboard-key'] = $keyboard['key'] ?? '';
      $attributes['data-keyboard-help'] = $keyboard['help'] ?? '';
      $attributes['aria-keyshortcuts'] = $keyboard['key'] ?? '';
    }

    return [
      '#type' => 'component',
      '#component' => 'display_builder:button',
      '#props' => [
        'id' => \sprintf('preview-toggle-%s', $builder->id()),
        'label' => $this->t('Preview'),
        'tooltip' => $this->t('Show the preview beside the editor'),
        'attributes' => $attributes,
      ],
    ];
  }

  /**
   * Build contextual islands which are tabbed sub islands.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param array $islands_enabled_sorted
   *   The islands enabled sorted.
   *
   * @return array
   *   The contextual islands render array.
   */
  private function buildContextualIslands(InstanceInterface $builder, array $islands_enabled_sorted): array {
    $contextual_islands = $islands_enabled_sorted[IslandType::Contextual->value] ?? [];

    if (empty($contextual_islands)) {
      return [];
    }

    $filter = $this->buildInput((string) $builder->id(), '', 'search', 'medium', 'off', $this->t('Search'), TRUE, 'search');
    // @see components/library_panel/search.js
    $filter['#attributes']['class'] = ['db-search-instance'];

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      // Used for custom styling in components/display_builder/form.css.
      '#attributes' => [
        'id' => \sprintf('%s-contextual', $builder->id()),
        'class' => ['db-form'],
      ],
      'tabs' => $this->buildDynamicTabs($builder, $contextual_islands, FALSE, $this->entity->getContextualTabsDisplay()),
      'filter' => $filter,
      'panes' => $this->buildPanes($builder, $contextual_islands, $builder->getCurrentState()),
    ];
  }

  /**
   * Builds panes.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $islands
   *   The islands to build tabs for.
   * @param array $data
   *   (Optional) The data to pass to the islands.
   * @param bool $label
   *   (Optional) Show label before content.
   * @param array $classes
   *   (Optional) The HTML classes to start with.
   * @param string $tag
   *   (Optional) The HTML tag, defaults to 'div'.
   *
   * @return array
   *   The tabs render array.
   */
  private function buildPanes(InstanceInterface $builder, array $islands, array $data = [], bool $label = FALSE, array $classes = [], string $tag = 'div'): array {
    $panes = [];

    foreach ($islands as $island_id => $island) {
      $island_classes = \array_merge($classes, [
        'db-island',
        \sprintf('db-island-%s', $island->getTypeId()),
        \sprintf('db-island-%s', $island->getPluginId()),
      ]);

      $title = '';
      if ($label) {
        $title = [
          '#type' => 'html_tag',
          '#tag' => 'h5',
          'value' => $island->label(),
          '#attributes' => [
            'class' => ['db-island-label', 'db-library-search-hide', 'db-island-label--' . $island->getPluginId()],
          ],
        ];
      }

      $panes[$island_id] = [
        '#type' => 'html_tag',
        '#tag' => $tag,
        'title' => $title,
        'children' => $island->build($builder, $data),
        '#attributes' => [
          // `id` attribute is used by HTMX OOB swap.
          'id' => $island->getHtmlId((string) $builder->id()),
          // `sse-swap` attribute is used by HTMX SSE swap.
          'sse-swap' => $island->getHtmlId((string) $builder->id()),
          'class' => $island_classes,
          'data-testid' => \sprintf('%s_%s', $island->getTypeId(), $island->getPluginId()),
        ] + $this->buildDeferrableAttribute($island),
      ];
    }

    return $panes;
  }

  /**
   * Marks a pane the client may leave stale while it is off screen.
   *
   * The server owns this decision, so the client does not have to re-derive it
   * from island types and stay in sync with them - it just looks for the
   * attribute.
   *
   * @param \Drupal\display_builder\Island\IslandInterface $island
   *   The island the pane belongs to.
   *
   * @return array
   *   The attribute to merge in, or an empty array if the island always
   *   renders.
   *
   * @see components/display_builder/js/deferred_islands.js
   * @see \Drupal\display_builder\Island\IslandInterface::isDeferrable()
   */
  private function buildDeferrableAttribute(IslandInterface $island): array {
    return $island->isDeferrable() ? ['data-db-deferrable' => 'true'] : [];
  }

  /**
   * Builds the floating controls region for the main-region View islands.
   *
   * Each Floating island is rendered exactly once, as a flex child of a
   * single `.db-island-floating-controls` box, no matter how many View panes
   * it attaches to. It carries a comma-separated `data-attached-to` listing
   * every attached pane present in this profile, and stays visible while ANY
   * of them is the active tab (@see components/shoelace/tabs/tabs.js
   * syncPanes()). Rendering per Floating island rather than per View pane is
   * what keeps its `id` unique - a pane that attached the same Floating island
   * twice (e.g. Highlight on both Canvas and Scaffold) used to emit duplicate
   * ids, an invalid-HTML/axe failure, and it also keeps the reload/OOB target
   * (@see IslandPluginBase::reloadWithGlobalData(), which swaps `#{getHtmlId}`)
   * pointing at a single element.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $view_islands
   *   The enabled main-region View islands, keyed by plugin ID.
   * @param \Drupal\display_builder\Island\IslandInterface[] $floating_islands
   *   The sorted, enabled Floating islands.
   *
   * @return array
   *   A single wrapper render array holding one child per Floating island, or
   *   an empty array when none attach to a present pane.
   */
  private function buildFloatingControlsRegion(InstanceInterface $builder, array $view_islands, array $floating_islands): array {
    $data = $builder->getCurrentState();
    $children = [];

    foreach ($floating_islands as $floating_island) {
      $definition = $floating_island->getPluginDefinition();

      // pane_header islands are the pane's own chrome and render inside it, not
      // in this overlay box. @see buildPaneHeaders().
      if (\is_array($definition) && !empty($definition['pane_header'])) {
        continue;
      }

      $attach_to = \is_array($definition) ? ($definition['attach_to'] ?? []) : [];

      // The selectors of the panes this island attaches to that are actually
      // present in this profile. The tabs system already uses the same
      // selector for a pane's data-target, so syncPanes() shows/hides this
      // control in step with those panes.
      $targets = [];

      foreach ($attach_to as $view_island_id) {
        if (isset($view_islands[$view_island_id])) {
          $targets[] = '#' . $view_islands[$view_island_id]->getHtmlId((string) $builder->id());
        }
      }

      if (empty($targets)) {
        continue;
      }

      $floating_island_id = $floating_island->getPluginId();
      // Same db-island-{type}/db-island-{plugin_id} classing as buildPanes()
      // - other JS (e.g. assets/js/viewport_switcher.js) selects on it.
      $children[$floating_island_id] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => $floating_island->getHtmlId((string) $builder->id()),
          // `sse-swap` attribute is used by HTMX SSE swap.
          'sse-swap' => $floating_island->getHtmlId((string) $builder->id()),
          'class' => [
            'db-island',
            \sprintf('db-island-%s', $floating_island->getTypeId()),
            \sprintf('db-island-%s', $floating_island_id),
            // Starts hidden like the panes do; the initial syncPanes() call on
            // page load corrects it.
            'shoelace-tabs__tab--hidden',
          ],
          'data-testid' => \sprintf('%s_%s', $floating_island->getTypeId(), $floating_island_id),
          'data-attached-to' => \implode(',', $targets),
        ] + $this->buildDeferrableAttribute($floating_island),
        'children' => $floating_island->build($builder, $data),
      ];
    }

    if (empty($children)) {
      return [];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['db-island-floating-controls'],
      ],
      'children' => $children,
    ];
  }

  /**
   * Builds the in-pane header bars for pane_header Floating islands.
   *
   * A Floating island flagged pane_header (@see
   * \Drupal\display_builder\Attribute\Island) is the pane's own chrome rather
   * than an overlay: it renders inside its attach_to pane as an in-flow header
   * bar (e.g. the viewport switcher above the Preview iframe), so it rides with
   * the pane and needs no visibility syncing - hence no `data-attached-to` or
   * starts-hidden class here. Returned keyed by target pane plugin ID for the
   * caller to inject as that pane's first child.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $view_islands
   *   The enabled main-region View islands, keyed by plugin ID.
   * @param \Drupal\display_builder\Island\IslandInterface[] $floating_islands
   *   The sorted, enabled Floating islands.
   *
   * @return array
   *   A header render array per target pane, keyed by the pane's plugin ID.
   */
  private function buildPaneHeaders(InstanceInterface $builder, array $view_islands, array $floating_islands): array {
    $data = $builder->getCurrentState();
    $headers = [];

    foreach ($floating_islands as $floating_island) {
      $definition = $floating_island->getPluginDefinition();

      if (!\is_array($definition) || empty($definition['pane_header'])) {
        continue;
      }

      $floating_island_id = $floating_island->getPluginId();

      foreach (($definition['attach_to'] ?? []) as $view_island_id) {
        if (!isset($view_islands[$view_island_id])) {
          continue;
        }

        $headers[$view_island_id][$floating_island_id] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'id' => $floating_island->getHtmlId((string) $builder->id()),
            // `sse-swap` attribute is used by HTMX SSE swap.
            'sse-swap' => $floating_island->getHtmlId((string) $builder->id()),
            'class' => [
              'db-island',
              \sprintf('db-island-%s', $floating_island->getTypeId()),
              \sprintf('db-island-%s', $floating_island_id),
            ],
            'data-testid' => \sprintf('%s_%s', $floating_island->getTypeId(), $floating_island_id),
          ] + $this->buildDeferrableAttribute($floating_island),
          'children' => $floating_island->build($builder, $data),
        ];
      }
    }

    $result = [];

    foreach ($headers as $pane_id => $children) {
      $result[$pane_id] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['db-island-pane-header'],
        ],
        'children' => $children,
      ];
    }

    return $result;
  }

  /**
   * Build the buttons to hide/show the drawer.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $islands
   *   An array of island objects for which buttons will be created.
   * @param string $display
   *   (Optional) How to show each island: 'label', 'icon' or 'icon_label'.
   *   Default 'icon_label'.
   *
   * @return array
   *   An array of render arrays for the drawer buttons.
   */
  private function buildStartButtons(InstanceInterface $builder, array $islands, string $display = 'icon_label'): array {
    $build = [];
    ['icon' => $show_icon, 'label' => $show_label] = self::resolvePanelDisplay($display);

    foreach ($islands as $island) {
      $island_id = $island->getPluginId();

      $build[$island_id] = [
        '#type' => 'component',
        '#component' => 'display_builder:button',
        '#props' => [
          'id' => \sprintf('start-btn-%s-%s', $builder->id(), $island_id),
          'label' => $show_label ? (string) $island->label() : '',
          'icon' => $show_icon ? $island->getIcon() : NULL,
          'tooltip' => $show_label ? NULL : (string) $island->label(),
          'attributes' => [
            'data-open-first-drawer' => TRUE,
            'data-target' => $island_id,
          ],
        ],
      ];

      if ($keyboard = $island::keyboardShortcuts()) {
        $build[$island_id]['#attributes']['data-keyboard-key'] = $keyboard['key'] ?? '';
        $build[$island_id]['#attributes']['data-keyboard-help'] = $keyboard['help'] ?? '';
        $build[$island_id]['#attributes']['aria-keyshortcuts'] = $keyboard['key'] ?? '';
      }
    }

    return $build;
  }

  /**
   * Builds tabs.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $islands
   *   The islands to build tabs for.
   * @param bool $contextual
   *   (Optional) Is the tabs contextual? See component for details. Default no.
   * @param string $display
   *   (Optional) How to show each island: 'label', 'icon' or 'icon_label'.
   *   Default 'label'.
   *
   * @return array
   *   The tabs render array.
   */
  private function buildDynamicTabs(InstanceInterface $builder, array $islands, bool $contextual = FALSE, string $display = 'label'): array {
    // Global id is based on last island.
    $id = '';
    $tabs = [];
    ['icon' => $show_icon, 'label' => $show_label] = self::resolvePanelDisplay($display);

    foreach ($islands as $island) {
      $id = $island_id = $island->getHtmlId((string) $builder->id());
      $attributes = [
        'data-testid' => \sprintf('tab_%s_%s', $island->getTypeId(), $island->getPluginId()),
      ];

      if ($keyboard = $island::keyboardShortcuts()) {
        $attributes['data-keyboard-key'] = $keyboard['key'] ?? '';
        $attributes['data-keyboard-help'] = $keyboard['help'] ?? '';
        $attributes['aria-keyshortcuts'] = $keyboard['key'] ?? '';
      }

      $tabs[] = [
        'title' => $island->label(),
        'url' => '#' . $island_id,
        'attributes' => $attributes,
        'icon' => $show_icon ? $island->getIcon() : NULL,
        'show_label' => $show_label,
      ];
    }

    // Id is needed for storage tabs state, @see component tabs.js file.
    return $this->buildTabs($id, $tabs, $contextual);
  }

  /**
   * Resolves a 'label'/'icon'/'icon_label' display mode into show flags.
   *
   * @param string $display
   *   One of 'label', 'icon' or 'icon_label'.
   *
   * @return array
   *   An associative array with 'icon' and 'label' boolean flags.
   */
  private static function resolvePanelDisplay(string $display): array {
    return [
      'icon' => \in_array($display, ['icon', 'icon_label'], TRUE),
      'label' => \in_array($display, ['label', 'icon_label'], TRUE),
    ];
  }

  /**
   * Builds menu with islands as entries.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\Island\IslandInterface[] $islands
   *   The islands to build tabs for.
   *
   * @return array
   *   The islands render array.
   *
   * @see components/contextual_menu/contextual_menu.js
   */
  private function buildMenuWrapper(InstanceInterface $builder, array $islands): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:contextual_menu',
      '#slots' => [
        'label' => $this->t('Select an action'),
      ],
      '#attributes' => [
        'class' => ['db-background', 'db-menu'],
        // Require for JavaScript.
        // @see components/contextual_menu/contextual_menu.js
        'data-db-id' => (string) $builder->id(),
      ],
    ];

    $items = [];

    foreach ($islands as $island) {
      $items = \array_merge($items, $island->build($builder, $builder->getCurrentState()));
    }
    $build['#slots']['items'] = $items;

    return $build;
  }

  /**
   * Get enabled panes sorted by weight.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   An array of contexts, keyed by context name.
   *
   * @return array
   *   The list of enabled islands sorted.
   *
   * @todo just key by weight and default weight in Island?
   */
  private function getIslandsEnableSorted(array $contexts): array {
    // Set island by weight.
    // @todo just key by weight and default weight in Island?
    $islands_enable_by_weight = $this->entity->getEnabledIslands();

    return $this->islandPluginManager->getIslandsByTypes($contexts, $this->entity->getIslandConfigurations(), $islands_enable_by_weight);
  }

}
