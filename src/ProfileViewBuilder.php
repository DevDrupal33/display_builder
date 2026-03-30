<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * View builder handler for display builder profiles.
 */
class ProfileViewBuilder extends EntityViewBuilder implements TrustedCallbackInterface {

  use RenderableBuilderTrait;

  /**
   * The entity type manager.
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The display builder island plugin manager.
   */
  private IslandPluginManagerInterface $islandPluginManager;

  /**
   * The entity we are building the view for.
   */
  private ProfileInterface $entity;

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array {
    // We have 'hacked' the interface by using $view_mode as a way of passing
    // the Instance entity ID.
    $builder_id = $view_mode;

    /** @var \Drupal\display_builder\ProfileInterface $entity */
    $entity = $entity;
    $this->entity = $entity;

    /** @var \Drupal\display_builder\InstanceInterface $builder */
    $builder = $this->entityTypeManager()->getStorage('display_builder_instance')->load($builder_id);
    $contexts = $builder->getContexts();
    $islands_enabled_sorted = $this->getIslandsEnableSorted($contexts);
    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:display_builder',
      '#props' => [
        'builder_id' => $builder_id,
        'hash' => (string) $builder->getCurrent()->getHash(),
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

    if (!empty($menu_islands)) {
      $menu_islands = $this->buildMenuWrapper($builder, $menu_islands);
    }

    if (!empty($library_islands)) {
      $library_islands = [
        $this->buildDynamicTabs($builder, $library_islands, TRUE),
        $this->buildPanes($builder, $library_islands, $builder_data),
      ];
    }

    $view_islands_data = $this->prepareViewIslands($builder, $view_islands);
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
      'start_buttons' => $this->buildButtons($builder, $button_islands, 'start'),
      'end_buttons' => $this->buildButtons($builder, $button_islands),
      'contextual_islands' => $contextual_islands,
      'menu_islands' => $menu_islands,
    ];
  }

  /**
   * Build buttons for a region.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   The builder instance.
   * @param \Drupal\display_builder\IslandInterface[] $buttonIslands
   *   The button islands.
   * @param string $region
   *   The button region.
   *
   * @return array
   *   The buttons.
   */
  private function buildButtons(InstanceInterface $builder, array $buttonIslands, string $region = 'end'): array {
    $islands = [];

    foreach ($buttonIslands as $island) {
      $islandRegion = $island->getConfiguration()['region'] ?? 'end';

      if ($islandRegion === $region) {
        $islands[] = $island;
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
   *
   * @return array
   *   The prepared view islands data.
   */
  private function prepareViewIslands(InstanceInterface $builder, array $islands): array {
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
      $view_sidebar_buttons = $this->buildStartButtons($builder, $view_sidebar_buttons);
    }

    if (!empty($view_main_tabs)) {
      $view_main_tabs = $this->buildDynamicTabs($builder, $view_main_tabs);
    }

    $builder_data = $builder->getCurrentState();
    $view_sidebar = $this->buildPanes($builder, $view_islands_sidebar, $builder_data);
    // Default hidden.
    $view_main = $this->buildPanes($builder, $view_islands_main, $builder_data, ['shoelace-tabs__tab--hidden']);

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

    $filter = $this->buildInput((string) $builder->id(), '', 'search', 'medium', 'off', $this->t('Filter by name'), TRUE, 'search');
    // @see assets/js/search.js
    $filter['#attributes']['class'] = ['db-search-instance'];

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      // Used for custom styling in assets/css/form.css.
      '#attributes' => [
        'id' => \sprintf('%s-contextual', $builder->id()),
        'class' => ['db-form'],
      ],
      'tabs' => $this->buildDynamicTabs($builder, $contextual_islands, FALSE),
      'filter' => $filter,
      'panes' => $this->buildPanes($builder, $contextual_islands, $builder->getCurrentState()),
    ];
  }

  /**
   * Builds panes.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
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
        ],
      ];
    }

    return $panes;
  }

  /**
   * Build the buttons to hide/show the drawer.
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\IslandInterface[] $islands
   *   An array of island objects for which buttons will be created.
   *
   * @return array
   *   An array of render arrays for the drawer buttons.
   */
  private function buildStartButtons(InstanceInterface $builder, array $islands): array {
    $build = [];

    foreach ($islands as $island) {
      $island_id = $island->getPluginId();

      $build[$island_id] = [
        '#type' => 'component',
        '#component' => 'display_builder:button',
        '#props' => [
          'id' => \sprintf('start-btn-%s-%s', $builder->id(), $island_id),
          'label' => (string) $island->label(),
          'icon' => $island->getIcon(),
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
   * @param \Drupal\display_builder\IslandInterface[] $islands
   *   The islands to build tabs for.
   * @param bool $contextual
   *   (Optional) Is the tabs contextual? See component for details. Default no.
   *
   * @return array
   *   The tabs render array.
   */
  private function buildDynamicTabs(InstanceInterface $builder, array $islands, bool $contextual = FALSE): array {
    // Global id is based on last island.
    $id = '';
    $tabs = [];

    foreach ($islands as $island) {
      $id = $island_id = $island->getHtmlId((string) $builder->id());
      $attributes = [];

      if ($keyboard = $island::keyboardShortcuts()) {
        $attributes['data-keyboard-key'] = $keyboard['key'] ?? '';
        $attributes['data-keyboard-help'] = $keyboard['help'] ?? '';
        $attributes['aria-keyshortcuts'] = $keyboard['key'] ?? '';
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
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param \Drupal\display_builder\IslandInterface[] $islands
   *   The islands to build tabs for.
   *
   * @return array
   *   The islands render array.
   *
   * @see assets/js/contextual_menu.js
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
        // @see assets/js/contextual_menu.js
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

    return $this->islandPluginManager()->getIslandsByTypes($contexts, $this->entity->getIslandConfigurations(), $islands_enable_by_weight);
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  private function entityTypeManager(): EntityTypeManagerInterface {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::service('entity_type.manager');
    }

    return $this->entityTypeManager;
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
