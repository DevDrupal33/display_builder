<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\Form\DisplayBuilderForm;
use Drupal\display_builder\Form\DisplayBuilderIslandPluginForm;
use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_ui\DisplayBuilderListBuilder;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Defines the display builder entity type.
 */
#[ConfigEntityType(
  id: 'display_builder',
  label: new TranslatableMarkup('Display builder'),
  label_collection: new TranslatableMarkup('Display builders'),
  label_singular: new TranslatableMarkup('display builder'),
  label_plural: new TranslatableMarkup('display builders'),
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'description' => 'description',
    'weight' => 'weight',
  ],
  handlers: [
    'route_provider' => [
      'html' => 'Drupal\display_builder\Routing\DisplayBuilderRouteProvider',
    ],
    'list_builder' => DisplayBuilderListBuilder::class,
    'form' => [
      'add' => DisplayBuilderForm::class,
      'edit' => DisplayBuilderForm::class,
      'delete' => EntityDeleteForm::class,
      'edit-plugin' => DisplayBuilderIslandPluginForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/display-builder/add',
    'edit-form' => '/admin/structure/display-builder/{display_builder}',
    'edit-plugin-form' => '/admin/structure/display-builder/{display_builder}/edit/{island_id}',
    'delete-form' => '/admin/structure/display-builder/{display_builder}/delete',
    'collection' => '/admin/structure/display-builder',
  ],
  admin_permission: 'administer display builders',
  constraints: [
    'ImmutableProperties' => [
      'id',
    ],
  ],
  config_export: [
    'id',
    'label',
    'library',
    'description',
    'islands',
    'debug',
    'weight',
  ],
)]
final class DisplayBuilder extends ConfigEntityBase implements DisplayBuilderInterface {

  use RenderableBuilderTrait;
  use StringTranslationTrait;

  /**
   * The display builder description.
   */
  protected string $description;

  /**
   * The display builder config ID.
   */
  protected string $id;

  /**
   * The display builder label.
   */
  protected string $label;

  /**
   * The display builder library mode.
   */
  protected string $library = 'cdn';

  /**
   * The display builder debug mode.
   */
  protected bool $debug = FALSE;

  /**
   * The islands configuration for storage.
   */
  protected ?array $islands = [];

  /**
   * Weight of this page layout when negotiating the page variant.
   *
   * The first/lowest that is accessible according to conditions is loaded.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The display builder state manager.
   */
  private StateManagerInterface $stateManager;

  /**
   * The display builder island plugin manager.
   */
  private IslandPluginManagerInterface $islandPluginManager;

  /**
   * {@inheritdoc}
   */
  public function getIslandConfiguration(string $island_id): array {
    return $this->islands[$island_id] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandConfigurations(): array {
    return $this->islands ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function setIslandConfiguration(string $island_id, array $configuration = []): void {
    // When $configuration is updated from DisplayBuilderIslandPluginForm,
    // 'weight', 'enable' and 'region' properties are missing but they must not
    // be reset.
    $configuration['weight'] = $configuration['weight'] ?? $this->islands[$island_id]['weight'] ?? 0;
    $configuration['enable'] = $configuration['enable'] ?? $this->islands[$island_id]['enable'] ?? FALSE;

    // Only View islands have regions.
    if (isset($this->islands[$island_id]['region'])) {
      $configuration['region'] = $configuration['region'] ?? $this->islands[$island_id]['region'];
    }

    $this->islands[$island_id] = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $contexts = []): array {
    $stateManager = $this->stateManager();

    $builder_data = $stateManager->getCurrentState($builder_id);
    $islands_enabled_sorted = $this->getIslandsEnableSorted($contexts);
    $hash = $stateManager->getCurrentHash($builder_id);

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

    $build = [
      '#type' => 'component',
      '#component' => 'display_builder:display_builder',
      '#props' => [
        'builder_id' => $builder_id,
        'hash' => $hash,
      ],
      '#slots' => [
        'view_sidebar_buttons' => $view_islands_data['view_sidebar_buttons'],
        'view_sidebar' => $view_sidebar,
        'view_main_tabs' => $view_islands_data['view_main_tabs'],
        'view_main' => $view_main,
        'buttons' => $buttons,
        'contextual_islands' => $contextual_islands,
        'menu_islands' => $menu_islands,
      ],
      '#attached' => [
        'drupalSettings' => [
          'dbDebug' => $this->debug,
        ],
      ],
    ];

    if ($this->library === 'local') {
      $build['#attached']['library'][] = 'display_builder/shoelace_local';
    }
    else {
      $build['#attached']['library'][] = 'display_builder/shoelace_cdn';
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getIslandEnabled(): array {
    $island_enabled = [];

    foreach ($this->islands as $island_id => $configuration) {
      if (isset($configuration['enable']) && (bool) $configuration['enable']) {
        $island_enabled[$island_id] = $configuration['weight'] ?? 0;
      }
    }

    return $island_enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissionName(): string {
    return 'use display builder ' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles(): array {
    // Do not list any roles if the permission does not exist.
    $permission = $this->getPermissionName();

    if (empty($permission)) {
      return [];
    }

    $roles = \array_filter(Role::loadMultiple(), static fn (RoleInterface $role) => $role->hasPermission($permission));

    return \array_map(static fn (RoleInterface $role) => $role->label(), $roles);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = NULL, array $options = []): Url {
    if ($rel === 'edit-plugin-form' && $this->id() && isset($options['island_id'])) {
      $island_id = $options['island_id'];
      unset($options['island_id']);

      return Url::fromRoute(
        'entity.display_builder.edit_plugin_form',
        ['display_builder' => $this->id(), 'island_id' => $island_id],
        $options
      );
    }

    return parent::toUrl($rel, $options);
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
      $classes = \array_merge($classes, [
        'db-island',
        \sprintf('db-island-%s', $island->getTypeId()),
        \sprintf('db-island-%s', $island->getPluginId()),
      ]);

      $panes[$island_id] = [
        '#type' => 'html_tag',
        '#tag' => $tag,
        'children' => $island->build($builder_id, $data),
        '#attributes' => [
          'id' => $island->getHtmlId($builder_id),
          'class' => $classes,
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
    $island_enable = \array_keys($this->getIslandEnabled());
    $output = $this->getIslandPluginManager()->getIslandsKeyboard(\array_flip($island_enable));
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
    $islands_enable_by_weight = $this->getIslandEnabled();

    return $this->getIslandPluginManager()->getIslandsByTypes($contexts, $this->getIslandConfigurations(), $islands_enable_by_weight);
  }

  /**
   * Gets the display builder island plugin manager.
   *
   * @return \Drupal\display_builder\IslandPluginManagerInterface
   *   The island plugin manager.
   */
  private function getIslandPluginManager(): IslandPluginManagerInterface {
    if (!isset($this->islandPluginManager)) {
      $this->islandPluginManager = \Drupal::service('plugin.manager.db_island');
    }

    return $this->islandPluginManager;
  }

  /**
   * Gets the display builder state manager.
   *
   * @return \Drupal\display_builder\StateManager\StateManagerInterface
   *   The state manager.
   */
  private function stateManager(): StateManagerInterface {
    return $this->stateManager ??= \Drupal::service('display_builder.state_manager');
  }

}
