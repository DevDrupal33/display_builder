<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * View builder handler for display builder profiles.
 */
class DisplayBuilderViewBuilder extends EntityViewBuilder implements TrustedCallbackInterface {

  use RenderableBuilderTrait;

  /**
   * The display builder state manager.
   */
  private StateManagerInterface $stateManager;

  /**
   * The display builder island plugin manager.
   */
  private IslandPluginManagerInterface $islandPluginManager;

  /**
   * The entity we are building the view for.
   */
  private DisplayBuilderInterface $entity;

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    // We have 'hacked' the interface by using $view_mode as a way of passing
    // the instance ID from the state manager.
    $builder_id = $view_mode;

    /** @var \Drupal\display_builder\DisplayBuilderInterface $entity */
    $entity = $entity;
    $this->entity = $entity;

    $stateManager = $this->stateManager();
    $contexts = $stateManager->getContexts($builder_id) ?? [];
    $islands_enabled_sorted = $this->getIslandsEnableSorted($contexts);

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:display_builder',
      '#props' => [
        'builder_id' => $builder_id,
        'hash' => $stateManager->getCurrentHash($builder_id),
      ],
      '#slots' => $this->buildSlots($builder_id, $islands_enabled_sorted),
      '#attached' => [
        'drupalSettings' => [
          'dbDebug' => $entity->isDebugModeActivated(),
        ],
      ],
    ];

    // Enable SSE if the active users button is enabled.
    if (isset($islands_enabled_sorted['button']['collaboration'])) {
      $build['#attributes'] = [
        'hx-ext' => 'sse',
        'sse-connect' => Url::fromRoute('display_builder.api_sse', ['builder_id' => $builder_id])->toString(),
      ];
    }

    if ($entity->getLibrary() === 'local') {
      $build['#attached']['library'][] = 'display_builder/shoelace_local';
      $build['#attached']['library'][] = 'display_builder/htmx_sse_local';
    }
    else {
      $build['#attached']['library'][] = 'display_builder/shoelace_cdn';
      $build['#attached']['library'][] = 'display_builder/htmx_sse_cdn';
    }

    return $build;
  }

  /**
   * Builds and returns the value of each slot.
   *
   * @param string $builder_id
   *   The ID of the display builder instance.
   * @param array $islands_enabled_sorted
   *   An array of enabled islands.
   *
   * @return array
   *   An associative array with the value of each slot.
   */
  private function buildSlots(string $builder_id, array $islands_enabled_sorted): array {
    $stateManager = $this->stateManager();

    $builder_data = $stateManager->getCurrentState($builder_id);

    $button_islands = $islands_enabled_sorted[IslandType::Button->value] ?? [];
    $library_islands = $islands_enabled_sorted[IslandType::Library->value] ?? [];
    $contextual_islands = $islands_enabled_sorted[IslandType::Contextual->value] ?? [];
    $menu_islands = $islands_enabled_sorted[IslandType::Menu->value] ?? [];
    $view_islands = $islands_enabled_sorted[IslandType::View->value] ?? [];

    $buttons = [];

    if (!empty($button_islands)) {
      $buttons = $this->buildPanes($builder_id, $button_islands, $this->getKeyboardKeys(), [], 'span');
    }

    if (!empty($menu_islands)) {
      $menu_islands = $this->buildMenuWrapper($builder_id, $menu_islands);
    }

    if (!empty($library_islands)) {
      $library_islands = [
        $this->buildBuilderTabs($builder_id, $library_islands, TRUE),
        $this->buildPanes($builder_id, $library_islands, $builder_data),
      ];
    }

    $view_islands_data = $this->prepareViewIslands($builder_id, $view_islands, $builder_data);
    $view_sidebar = $view_islands_data['view_sidebar'];
    $view_main = $view_islands_data['view_main'];

    // Library content can be in main or sidebar.
    // @todo Move the logic to LibrariesIsland::build().
    if (isset($view_sidebar['library']) && !empty($library_islands)) {
      $view_sidebar['library']['content'] = $library_islands;
    }
    elseif (isset($view_main['library']) && !empty($library_islands)) {
      $view_main['library']['content'] = $library_islands;
    }

    if (!empty($contextual_islands)) {
      $contextual_islands = $this->buildContextualIslands($builder_id, $islands_enabled_sorted, $builder_data);
    }

    return [
      'view_sidebar_buttons' => $view_islands_data['view_sidebar_buttons'],
      'view_sidebar' => $view_sidebar,
      'view_main_tabs' => $view_islands_data['view_main_tabs'],
      'view_main' => $view_main,
      'buttons' => $buttons,
      'contextual_islands' => $contextual_islands,
      'menu_islands' => $menu_islands,
    ];
  }

  /**
   * Prepares view islands data.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $islands
   *   The sorted, enabled View islands.
   * @param array $builder_data
   *   The builder data.
   *
   * @return array
   *   The prepared view islands data.
   */
  private function prepareViewIslands(string $builder_id, array $islands, array $builder_data): array {
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

    if (!empty($view_sidebar_buttons)) {
      $view_sidebar_buttons = $this->buildStartButtons($builder_id, $view_sidebar_buttons);
    }

    if (!empty($view_main_tabs)) {
      $view_main_tabs = $this->buildBuilderTabs($builder_id, $view_main_tabs, FALSE, TRUE);
    }

    $view_sidebar = $this->buildPanes($builder_id, $view_islands_sidebar, $builder_data);
    // Default hidden.
    $view_main = $this->buildPanes($builder_id, $view_islands_main, $builder_data, ['shoelace-tabs__tab--hidden']);

    return [
      'view_sidebar_buttons' => $view_sidebar_buttons,
      'view_main_tabs' => $view_main_tabs,
      'view_sidebar' => $view_sidebar,
      'view_main' => $view_main,
    ];
  }

  /**
   * Build contextual islands which are tabbed sub islands.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $islands_enabled_sorted
   *   The islands enabled sorted.
   * @param array $builder_data
   *   The builder data.
   *
   * @return array
   *   The contextual islands render array.
   */
  private function buildContextualIslands(string $builder_id, array $islands_enabled_sorted, array $builder_data): array {
    $contextual_islands = $islands_enabled_sorted[IslandType::Contextual->value] ?? [];

    if (empty($contextual_islands)) {
      return [];
    }

    $filter = $this->buildInput($builder_id, '', 'search', 'medium', 'off', $this->t('Filter by name'), TRUE, 'search');
    // @see assets/js/search.js
    $filter['#attributes']['class'] = ['db-search-contextual'];

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      // Used for custom styling in assets/css/form.css.
      '#attributes' => [
        'id' => \sprintf('%s-contextual', $builder_id),
        'class' => ['db-form'],
      ],
      'tabs' => $this->buildBuilderTabs($builder_id, $contextual_islands),
      'filter' => $filter,
      'panes' => $this->buildPanes($builder_id, $contextual_islands, $builder_data),
    ];
  }

  /**
   * Builds panes.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param \Drupal\display_builder\IslandInterface[] $islands
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
  private function buildPanes(string $builder_id, array $islands, array $data = [], array $classes = [], string $tag = 'div'): array {
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
        'children' => $island->build($builder_id, $data),
        '#attributes' => [
          // `id` attribute is used by HTMX OOB swap.
          'id' => $island->getHtmlId($builder_id),
          // `sse-swap` attribute is used by HTMX SSE swap.
          'sse-swap' => $island->getHtmlId($builder_id),
          'class' => $island_classes,
        ],
      ];
    }

    return $panes;
  }

  /**
   * Build the buttons to hide/show the drawer.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param \Drupal\display_builder\IslandInterface[] $islands
   *   An array of island objects for which buttons will be created.
   *
   * @return array
   *   An array of render arrays for the drawer buttons.
   */
  private function buildStartButtons(string $builder_id, array $islands): array {
    $build = [];

    foreach ($islands as $island) {
      $island_id = $island->getPluginId();

      $build[$island_id] = [
        '#type' => 'component',
        '#component' => 'display_builder:button',
        '#props' => [
          'id' => \sprintf('start-btn-%s-%s', $builder_id, $island_id),
          'label' => (string) $island->label(),
          'icon' => $island->getIcon(),
          'attributes' => [
            'data-open-first-drawer' => TRUE,
            'data-target' => $island_id,
          ],
        ],
      ];

      // Keep only first keyboard key.
      if ($keyboard = $island->getKeyboardShortcuts()) {
        $build[$island_id]['#attributes']['data-keyboard'] = \key($keyboard);
      }
    }

    return $build;
  }

  /**
   * Builds tabs.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param \Drupal\display_builder\IslandInterface[] $islands
   *   The islands to build tabs for.
   * @param bool $contextual
   *   (Optional) Whether the tabs are contextual.
   * @param bool $enableKeyboard
   *   (Optional) Add the keyboard data value.
   *
   * @return array
   *   The tabs render array.
   */
  private function buildBuilderTabs(string $builder_id, array $islands, bool $contextual = FALSE, bool $enableKeyboard = FALSE): array {
    // Global id is based on last island.
    $id = '';
    $tabs = [];

    foreach ($islands as $island) {
      $id = $island_id = $island->getHtmlId($builder_id);
      $attributes = [];

      if ($enableKeyboard) {
        $key = \array_keys($island->getKeyboardShortcuts());

        if (!empty($key)) {
          $attributes = ['data-keyboard' => $key[0]];
        }
      }
      $tabs[] = [
        'title' => $island->label(),
        'url' => '#' . $island_id,
        'attributes' => $attributes,
      ];
    }

    // Id is needed for storage tabs state, @see component tabs.js file.
    return $this->buildTabs($id, $tabs, $contextual);
  }

  /**
   * Builds menu with islands as entries.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param \Drupal\display_builder\IslandInterface[] $islands
   *   The islands to build tabs for.
   * @param array $data
   *   (Optional) The data to pass to the islands.
   *
   * @return array
   *   The islands render array.
   *
   * @see assets/js/contextual_menu.js
   */
  private function buildMenuWrapper(string $builder_id, array $islands, array $data = []): array {
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:contextual_menu',
      '#slots' => [
        'label' => $this->t('Select an action'),
      ],
      '#attributes' => [
        'class' => ['db-background', 'db-menu'],
        // Require for JavaScript.
        // @see assets/js/contextual_menu.js
        'data-db-id' => $builder_id,
      ],
    ];

    $items = [];

    foreach ($islands as $island) {
      $items = \array_merge($items, $island->build($builder_id, $data));
    }
    $build['#slots']['items'] = $items;

    return $build;
  }

  /**
   * Get keyboard keys defined in islands.
   *
   * @return array
   *   The keyboard array list as key => description.
   */
  private function getKeyboardKeys(): array {
    $island_enable = \array_keys($this->entity->getIslandEnabled());
    $output = $this->islandPluginManager()->getIslandsKeyboard(\array_flip($island_enable));
    \ksort($output, \SORT_NATURAL | \SORT_FLAG_CASE);

    return $output;
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
    $islands_enable_by_weight = $this->entity->getIslandEnabled();

    return $this->islandPluginManager()->getIslandsByTypes($contexts, $this->entity->getIslandConfigurations(), $islands_enable_by_weight);
  }

  /**
   * Gets the display builder state manager.
   *
   * @return \Drupal\display_builder\StateManager\StateManagerInterface
   *   The state manager.
   */
  private function stateManager(): StateManagerInterface {
    if (!isset($this->stateManager)) {
      $this->stateManager = \Drupal::service('display_builder.state_manager');
    }

    return $this->stateManager;
  }

  /**
   * Gets the display builder island plugin manager.
   *
   * @return \Drupal\display_builder\IslandPluginManagerInterface
   *   The island plugin manager.
   */
  private function islandPluginManager(): IslandPluginManagerInterface {
    if (!isset($this->islandPluginManager)) {
      $this->islandPluginManager = \Drupal::service('plugin.manager.db_island');
    }

    return $this->islandPluginManager;
  }

}
