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

    $view_islands_data = $this->prepareViewIslands($builder, $view_islands, $floating_islands);
    $view_sidebar = $view_islands_data['view_sidebar'];
    $view_main = $view_islands_data['view_main'];

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
      'view_main_tabs' => $view_islands_data['view_main_tabs'],
      'view_main' => $view_main,
      'view_floating_controls' => $view_islands_data['view_floating_controls'],
      'start_buttons' => $this->buildButtons($builder, $button_islands, 'start'),
      'end_buttons' => $this->buildButtons($builder, $button_islands),
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
      'panes' => $this->buildPanes($builder, $library_islands, $builder_data),
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
      $buttons = $this->buildPanes($builder, $islands, [], [], 'span');
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
   * @param array $floating_islands
   *   The sorted, enabled Floating islands, to attach to their target main
   *   View island(s), @see buildFloatingControlsRegion().
   *
   * @return array
   *   The prepared view islands data.
   */
  private function prepareViewIslands(InstanceInterface $builder, array $islands, array $floating_islands = []): array {
    $view_islands_sidebar = [];
    $view_islands_main = [];
    $view_sidebar_buttons = [];
    $view_main_tabs = [];

    foreach ($islands as $id => $island) {
      if ($island->getTypeId() !== IslandType::View->value) {
        continue;
      }

      $configuration = $island->getConfiguration();

      if ($configuration['region'] === 'sidebar') {
        $view_islands_sidebar[$id] = $islands[$id];
        $view_sidebar_buttons[$id] = $islands[$id];
      }
      else {
        $view_islands_main[$id] = $islands[$id];
        $view_main_tabs[$id] = $islands[$id];
      }
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
    $view_main = $this->buildPanes($builder, $view_islands_main, $builder_data, ['shoelace-tabs__tab--hidden']);
    $view_floating_controls = $this->buildFloatingControlsRegion($builder, $view_islands_main, $floating_islands);

    return [
      'view_sidebar_buttons' => $view_sidebar_buttons,
      'view_main_tabs' => $view_main_tabs,
      'view_sidebar' => $view_sidebar,
      'view_main' => $view_main,
      'view_floating_controls' => $view_floating_controls,
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
   * @param array $classes
   *   (Optional) The HTML classes to start with.
   * @param string $tag
   *   (Optional) The HTML tag, defaults to 'div'.
   *
   * @return array
   *   The tabs render array.
   */
  private function buildPanes(InstanceInterface $builder, array $islands, array $data = [], array $classes = [], string $tag = 'div'): array {
    $panes = [];

    foreach ($islands as $island_id => $island) {
      $island_classes = \array_merge($classes, [
        'db-island',
        \sprintf('db-island-%s', $island->getTypeId()),
        \sprintf('db-island-%s', $island->getPluginId()),
      ]);

      $panes[$island_id] = [
        '#type' => 'html_tag',
        '#tag' => $tag,
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
