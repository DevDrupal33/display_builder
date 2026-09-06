<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\InstanceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the ApiController class.
 *
 * @internal
 */
#[CoversClass(ApiController::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ApiControllerTest extends DisplayBuilderKernelTestBase {

  /**
   * The controller to test.
   */
  protected ApiController $controller;

  /**
   * The builder instance entity.
   */
  protected InstanceInterface $instance;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path_alias',
    'ui_patterns',
    'ui_patterns_field',
    'ui_styles',
    'ui_skins',
    'breakpoint',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);

    // Create a real builder entity.
    $this->instance = $this->createDisplayBuilderInstance('test_base', 'test_instance');
    $this->instance->save();

    // Get the controller from the container.
    $this->controller = $this->container->get('class_resolver')->getInstanceFromDefinition(ApiController::class);
  }

  /**
   * Test the ::attachToRoot() method.
   */
  public function testAttachToRoot(): void {
    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'source_id' => 'token',
      'position' => 0,
    ]);
    $response = $this->controller->attachToRoot($request, $this->instance);
    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
  }

  /**
   * Tests the ::attachToSlot() method when attaching a new source.
   */
  public function testAttachToSlot(): void {
    // First attach a component to root so we have a parent with a slot.
    $parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);

    $url = Url::fromRoute('display_builder.api_slot_attach', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $parent_id,
      'slot' => 'slot_1',
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'source_id' => 'token',
      'position' => 0,
    ]);
    $response = $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    // The new node must appear in the slot in persisted state.
    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getSources();
    self::assertNotEmpty($state[0]['source']['component']['slots']['slot_1']['sources']);
  }

  /**
   * Tests the ::attachToRoot() move path (node_id param triggers moveToRoot).
   */
  public function testAttachToRootMove(): void {
    $node_a = $this->instance->attachToRoot(0, 'token', []);
    $parent_id = $this->instance->attachToRoot(1, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    // Move node_a into slot, then move it back to root via the controller.
    $this->instance->moveToSlot($node_a, $parent_id, 'slot_1', 0);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_a,
      'position' => 0,
    ]);
    $response = $this->controller->attachToRoot($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
    self::assertNull($this->instance->getParentId($node_a));
  }

  /**
   * Tests that setSource() preserves slot children when updating source data.
   *
   * This verifies the A-2 fix: SourceTree stores children in its flat
   * structure (not in source data), so calling setSource() with new settings
   * that omit slot children still preserves those children on round-trip.
   */
  public function testSetSourcePreservesSlotChildren(): void {
    // Attach a component that has slots to root.
    $parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);

    // Attach a child token into slot_1.
    $child_id = $this->instance->attachToSlot($parent_id, 'slot_1', 0, 'token', []);
    $this->instance->save();

    // Update the parent's source with new props but NO slot children in data.
    $this->instance->setSource($parent_id, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $this->instance->save();

    // Reload from storage to verify the persisted state.
    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getSources();

    // The child must still be present inside slot_1.
    $slot_sources = $state[0]['source']['component']['slots']['slot_1']['sources'] ?? [];
    self::assertNotEmpty($slot_sources, 'Slot children are preserved after setSource().');
    self::assertEquals($child_id, $slot_sources[0]['node_id'], 'Child node ID is unchanged.');
  }

  /**
   * Test the ::get() method returns the active node payload.
   */
  public function testGetReturnsTheActiveNode(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();

    $response = $this->controller->get(new Request(), $this->instance, $node_id);

    self::assertIsArray($response);
    self::assertNotEmpty($response);
  }

  /**
   * Test ::undo() and ::redo() walk the revision history.
   *
   * Also exercises InstanceStorage::undo()/redo(), which promote the previous
   * or next revision to be the default one.
   */
  public function testUndoRedoWalkTheRevisionHistory(): void {
    $this->attachTokenToRoot(0);
    $this->attachTokenToRoot(1);
    self::assertCount(2, $this->loadInstance('test_instance')->getSources());

    $this->controller->undo(new Request(), $this->loadInstance('test_instance'));
    self::assertCount(
      1,
      $this->loadInstance('test_instance')->getSources(),
      'Undo drops the most recent attach.'
    );

    $this->controller->redo(new Request(), $this->loadInstance('test_instance'));
    self::assertCount(
      2,
      $this->loadInstance('test_instance')->getSources(),
      'Redo puts it back.'
    );
  }

  /**
   * Test ::undo() on an instance with no history is a safe no-op.
   */
  public function testUndoWithoutHistoryIsNoOp(): void {
    $response = $this->controller->undo(new Request(), $this->instance);

    self::assertIsArray($response);
    self::assertEmpty($this->loadInstance('test_instance')->getSources());
  }

  /**
   * Test ::update() refuses a payload without a form ID.
   *
   * Asserts the effect rather than the error payload's shape: the guard exists
   * to stop a malformed request from writing to the tree.
   */
  public function testUpdateWithoutFormIdLeavesTheNodeUntouched(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();
    $before = $this->loadInstance('test_instance')->getNode($node_id);

    $url = Url::fromRoute('display_builder.api_update', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $node_id,
    ]);
    $request = Request::create($url->toString(), 'POST', ['source' => ['value' => 'no form id']]);

    $response = $this->controller->update($request, $this->instance, $node_id);

    self::assertIsArray($response, 'The controller returns an error payload instead of throwing.');
    self::assertSame(
      $before,
      $this->loadInstance('test_instance')->getNode($node_id),
      'A payload without a form ID must not modify the node.'
    );
  }

  /**
   * Test ::thirdPartySettingsUpdate() refuses a payload without a form ID.
   *
   * Same guard as ::update(), asserted the same way: by its effect on the
   * stored settings rather than by the error payload's shape.
   */
  public function testThirdPartySettingsUpdateWithoutFormIdChangesNothing(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();
    $before = $this->loadInstance('test_instance')->getNode($node_id);

    $request = Request::create('/', 'POST', ['some' => 'value']);
    $response = $this->controller->thirdPartySettingsUpdate($request, $this->instance, $node_id, 'styles');

    self::assertIsArray($response, 'The controller returns an error payload instead of throwing.');
    self::assertSame(
      $before,
      $this->loadInstance('test_instance')->getNode($node_id),
      'A payload without a form ID must not write third party settings.'
    );
  }

  /**
   * Test ::reloadIsland() returns the enabled island's renderable.
   */
  public function testReloadIslandReturnsRenderable(): void {
    // 'history' is enabled on the test_base profile.
    $response = $this->controller->reloadIsland(new Request(), $this->instance, 'history');

    self::assertIsArray($response);
    self::assertNotEmpty($response);
  }

  /**
   * Test ::reloadIsland() tags its response with the instance cache tag.
   *
   * This is the only GET endpoint returning rendered island markup, so it is
   * the only island response Dynamic Page Cache can store. Without the instance
   * cache tag the cached reload is never invalidated when the instance is
   * saved, and the panel serves stale markup until the whole cache is flushed.
   */
  public function testReloadIslandCarriesInstanceCacheTag(): void {
    $response = $this->controller->reloadIsland(new Request(), $this->instance, 'history');

    self::assertContains(
      'display_builder_instance:' . $this->instance->id(),
      $response['#cache']['tags'] ?? [],
    );
  }

  /**
   * Test ::reloadIsland() refuses an island not enabled on the profile.
   *
   * 'tokens' is a real plugin (ui_skins is installed) but is off in test_base,
   * so the enabled-islands guard must short-circuit to an error payload.
   */
  public function testReloadIslandRejectsDisabledIsland(): void {
    $response = $this->controller->reloadIsland(new Request(), $this->instance, 'tokens');

    self::assertIsArray($response, 'A disabled island yields an error payload, not a throw.');
  }

  /**
   * Test ::attachToRoot() refuses a request carrying no content to attach.
   */
  public function testAttachToRootRejectsMissingContent(): void {
    $request = Request::create('/', 'POST', ['position' => 0]);
    $response = $this->controller->attachToRoot($request, $this->instance);

    self::assertIsArray($response, 'A content less request yields an error payload.');
    self::assertEmpty(
      $this->loadInstance('test_instance')->getSources(),
      'Nothing is attached when the request has no source_id/node_id/preset_id.'
    );
  }

  /**
   * Test ::attachToSlot() refuses a request carrying no content to attach.
   */
  public function testAttachToSlotRejectsMissingContent(): void {
    $parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $this->instance->save();

    $request = Request::create('/', 'POST', ['position' => 0]);
    $response = $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');

    self::assertIsArray($response, 'A content less request yields an error payload.');
    $state = $this->loadInstance('test_instance')->getSources();
    self::assertArrayNotHasKey(
      'sources',
      $state[0]['source']['component']['slots']['slot_1'] ?? [],
      'Nothing is attached into the slot.'
    );
  }

  /**
   * Test ::update() writes the validated source when given a form ID.
   */
  public function testUpdateWritesSourceWithFormId(): void {
    $node_id = $this->instance->attachToRoot(0, 'textfield', ['value' => 'original']);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_update', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $node_id,
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'form_id' => 'display_builder_island',
      '_drupal_ajax' => TRUE,
      'value' => 'updated text',
    ]);

    $response = $this->controller->update($request, $this->instance, $node_id);
    self::assertIsArray($response);

    $node = $this->loadInstance('test_instance')->getNode($node_id);
    self::assertSame('updated text', $node['source']['value'], 'The node source is updated.');
  }

  /**
   * Attach a token to the root through the controller, building history.
   *
   * @param int $position
   *   The position to attach at.
   */
  private function attachTokenToRoot(int $position): void {
    $instance = $this->loadInstance('test_instance');
    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'source_id' => 'token',
      'position' => $position,
    ]);
    $this->controller->attachToRoot($request, $instance);
  }

}
