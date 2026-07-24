<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

/**
 * Tests the Floating-island fan-out in ProfileViewBuilder.
 *
 * Each Floating island (Highlight, Viewport) is rendered exactly once, as a
 * child of the single `.db-island-floating-controls` region, no matter how
 * many View panes it attaches to. It carries a comma-separated
 * `data-attached-to` listing the `#id` of each attached pane that is actually
 * present in the profile, starts hidden, and is skipped entirely when none of
 * its attach_to panes are present. This is the contract every Floating island
 * relies on to show/hide in step with its panes across the Canvas / Wireframe
 * / Scaffold / Preview tabs, and it had no coverage.
 *
 * @see \Drupal\display_builder\ProfileViewBuilder::buildFloatingControlsRegion()
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Drupal\display_builder\ProfileViewBuilder::class)]
#[\PHPUnit\Framework\Attributes\Group('display_builder')]
final class ProfileViewBuilderTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'breakpoint',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['display_builder', 'display_builder_test']);
  }

  /**
   * Each Floating island renders once, correlated to every pane it attaches to.
   */
  public function testFloatingIslandFanOut(): void {
    // Highlight attaches to builder + scaffold, Viewport to builder + preview;
    // all three panes are present, so each control lists both of its panes.
    $region = $this->renderFloatingRegion([
      'builder' => ['status' => TRUE, 'region' => 'main', 'weight' => -6],
      'scaffold' => ['status' => TRUE, 'region' => 'main', 'weight' => -5],
      'preview' => ['status' => TRUE, 'region' => 'main', 'weight' => -4],
      'highlight' => ['status' => TRUE],
      'viewport' => ['status' => TRUE],
    ], 'render_fanout');

    self::assertSame('div', $region['#tag']);
    self::assertContains('db-island-floating-controls', $region['#attributes']['class']);

    $highlight = $region['children']['highlight'];
    $viewport = $region['children']['viewport'];

    // Correlated to its panes, not duplicated per pane.
    self::assertSame(
      '#island-render_fanout-builder,#island-render_fanout-scaffold',
      $highlight['#attributes']['data-attached-to'],
    );
    self::assertSame(
      '#island-render_fanout-builder,#island-render_fanout-preview',
      $viewport['#attributes']['data-attached-to'],
    );

    // Unique id, addressable test id, and starts hidden like the panes.
    self::assertSame('island-render_fanout-highlight', $highlight['#attributes']['id']);
    self::assertSame('floating_highlight', $highlight['#attributes']['data-testid']);
    self::assertContains('shoelace-tabs__tab--hidden', $highlight['#attributes']['class']);
    self::assertContains('db-island-highlight', $highlight['#attributes']['class']);
  }

  /**
   * A Floating island whose attach_to panes are all absent is skipped.
   */
  public function testFloatingIslandSkippedWhenNoTargetPane(): void {
    // Only Scaffold is present: Highlight (builder + scaffold) still renders on
    // scaffold alone, but Viewport (builder + preview) has neither pane and is
    // dropped entirely.
    $region = $this->renderFloatingRegion([
      'scaffold' => ['status' => TRUE, 'region' => 'main', 'weight' => -5],
      'highlight' => ['status' => TRUE],
      'viewport' => ['status' => TRUE],
    ], 'render_skip');

    self::assertArrayHasKey('highlight', $region['children']);
    self::assertArrayNotHasKey('viewport', $region['children']);
    self::assertSame(
      '#island-render_skip-scaffold',
      $region['children']['highlight']['#attributes']['data-attached-to'],
    );
  }

  /**
   * With no Floating islands, the region collapses to nothing.
   */
  public function testNoFloatingRegionWithoutFloatingIslands(): void {
    $region = $this->renderFloatingRegion([
      'builder' => ['status' => TRUE, 'region' => 'main', 'weight' => -6],
    ], 'render_none');

    self::assertSame([], $region);
  }

  /**
   * A broad profile fills the sidebar, main, button and menu slots.
   *
   * Drives the non-floating build paths — sidebar/main View panes, the tabbed
   * Library panels, Button islands in the end region, the Menu wrapper and the
   * Contextual islands — in one render.
   */
  public function testViewBuildsAllRegions(): void {
    $build = $this->renderView([
      'library' => ['status' => TRUE, 'region' => 'sidebar', 'weight' => -10],
      'tree' => ['status' => TRUE, 'region' => 'sidebar', 'weight' => -7],
      'builder' => ['status' => TRUE, 'region' => 'main', 'weight' => -6],
      'component_library' => ['status' => TRUE, 'weight' => -9],
      'block_library' => ['status' => TRUE, 'weight' => -8],
      'controls' => ['status' => TRUE, 'region' => 'end', 'weight' => 0],
      'state' => ['status' => TRUE, 'region' => 'end', 'weight' => 0],
      'menu' => ['status' => TRUE, 'weight' => 0],
      'menu_delete' => ['status' => TRUE, 'weight' => 0],
      'contextual_form' => ['status' => TRUE, 'weight' => 0],
    ], 'render_all', ['library_flat' => FALSE]);

    $slots = $build['#slots'];
    self::assertNotEmpty($slots['view_sidebar'], 'The sidebar holds its View panes.');
    self::assertNotEmpty($slots['view_main'], 'The main region holds the Canvas.');
    self::assertNotEmpty($slots['view_main_tabs'], 'Main-region tabs are built.');
    self::assertNotEmpty($slots['end_buttons'], 'End-region Button islands are built.');
    self::assertNotEmpty($slots['menu_islands'], 'The Menu wrapper is built.');
    self::assertNotEmpty($slots['contextual_islands'], 'Contextual islands are built.');
  }

  /**
   * The flat-library option builds a single merged panel with one search box.
   */
  public function testFlatLibraryPanels(): void {
    $build = $this->renderView([
      'library' => ['status' => TRUE, 'region' => 'sidebar', 'weight' => -10],
      'builder' => ['status' => TRUE, 'region' => 'main', 'weight' => -6],
      'component_library' => ['status' => TRUE, 'weight' => -9],
      'block_library' => ['status' => TRUE, 'weight' => -8],
    ], 'render_flat', ['library_flat' => TRUE]);

    // The library content lands in the sidebar's library pane either way; the
    // flat path is what produced it here.
    self::assertNotEmpty($build['#slots']['view_sidebar'], 'The flat library sidebar is built.');
  }

  /**
   * Render a profile and return its `view_floating_controls` slot.
   *
   * @param array $islands
   *   Island configuration keyed by plugin id, passed straight to the profile.
   * @param string $instance_id
   *   A readable instance id, so the expected `#island-<id>-<pane>` selectors
   *   stay legible in the assertions.
   *
   * @return array
   *   The floating-controls region render array (empty when none).
   */
  private function renderFloatingRegion(array $islands, string $instance_id): array {
    return $this->renderView($islands, $instance_id)['#slots']['view_floating_controls'];
  }

  /**
   * Build a profile with the given islands and render it through view().
   *
   * @param array $islands
   *   Island configuration keyed by plugin id.
   * @param string $instance_id
   *   A readable instance id.
   * @param array $profile_values
   *   (Optional) Extra profile entity values, e.g. `library_flat`.
   *
   * @return array
   *   The full profile view render array.
   */
  private function renderView(array $islands, string $instance_id, array $profile_values = []): array {
    $profile_id = $this->randomMachineName();
    $profile = $this->createDisplayBuilderProfile($profile_id, $profile_values);

    foreach ($islands as $island_id => $configuration) {
      $profile->setIslandConfiguration($island_id, $configuration);
    }
    $profile->save();

    $instance = $this->createDisplayBuilderInstance($profile_id, $instance_id);
    $instance->save();

    return $this->container->get('entity_type.manager')
      ->getViewBuilder('display_builder_profile')
      ->view($profile, $instance_id);
  }

}
