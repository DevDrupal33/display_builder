<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\HtmxEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the HtmxEvents drag-and-drop and click wiring.
 *
 * Only the low-level DisplayBuilderHtmx helper was covered; the event methods
 * that turn a render array into a working drop target or clickable node were
 * not. These attributes are the contract shared by the drag-and-drop
 * JavaScript, the API routes and the e2e selectors, so pinning them here keeps
 * a frontend regression from looking like a backend one - which is exactly the
 * ambiguity the Wireframe occupied-slot bug created.
 *
 * @internal
 */
#[CoversClass(HtmxEvents::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class HtmxEventsTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * The HTMX events service.
   */
  private HtmxEvents $htmxEvents;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');

    $this->htmxEvents = $this->container->get('display_builder.htmx_events');
  }

  /**
   * Test a slot dropzone posts to the slot-attach route on dragend.
   */
  public function testOnSlotDropTargetsTheSlotAttachRoute(): void {
    $build = [];
    $build = $this->htmxEvents->onSlotDrop($build, 'instance_1', 'scaffold', 'node_1', 'slot_1');
    $url = (string) $build['#attributes']['data-hx-post'];

    // The route resolves the instance, the parent node, the slot, and which
    // island the drop came from - all four are needed to place the node.
    self::assertStringContainsString('instance_1', $url);
    self::assertStringContainsString('node_1', $url);
    self::assertStringContainsString('slot_1', $url);
    self::assertStringContainsString('scaffold', $url);

    self::assertSame('dragend consume', (string) $build['#attributes']['data-hx-trigger']);
  }

  /**
   * Test the originating island is carried through the drop URL.
   *
   * The Canvas and Scaffold panels drop into the same slots but must be
   * distinguishable, so the island ID cannot be hardcoded.
   */
  public function testOnSlotDropKeepsTheOriginIsland(): void {
    $from_builder = $this->htmxEvents->onSlotDrop([], 'instance_1', 'builder', 'node_1', 'slot_1');
    $from_scaffold = $this->htmxEvents->onSlotDrop([], 'instance_1', 'scaffold', 'node_1', 'slot_1');

    self::assertNotSame(
      (string) $from_builder['#attributes']['data-hx-post'],
      (string) $from_scaffold['#attributes']['data-hx-post'],
    );
  }

  /**
   * Test a root dropzone posts on dragend too.
   */
  public function testOnRootDropTargetsTheRootRoute(): void {
    $build = $this->htmxEvents->onRootDrop([], 'instance_1', 'builder');

    self::assertStringContainsString('instance_1', (string) $build['#attributes']['data-hx-post']);
    self::assertSame('dragend consume', (string) $build['#attributes']['data-hx-trigger']);
  }

  /**
   * Test a clicked node exposes its node ID as attribute and as payload.
   *
   * Data-node-id is what the e2e tests and the contextual menu select on;
   * hx-vals is what the request actually sends.
   */
  public function testOnInstanceClickExposesTheNodeId(): void {
    $build = $this->htmxEvents->onInstanceClick([], 'instance_1', 'node_1', 'Test title', 0);

    self::assertSame('node_1', $build['#attributes']['data-node-id']);
    self::assertStringContainsString('node_1', (string) $build['#attributes']['data-hx-vals']);
    self::assertStringContainsString('click', (string) $build['#attributes']['data-hx-trigger']);
  }

  /**
   * Test every simple action event targets its own distinct route.
   *
   * These methods are near-identical, which is exactly why they are worth
   * pinning: a copy-paste slip pointing Redo at the Undo route would be silent
   * and destructive. The distinctness assertion below is the real check.
   */
  public function testActionEventsTargetTheirOwnRoute(): void {
    $urls = [];

    foreach (self::actionEventCases() as $method => $fragment) {
      $build = $this->htmxEvents->{$method}([], 'instance_1');
      $url = (string) $build['#attributes']['data-hx-post'];

      self::assertStringContainsString($fragment, $url, \sprintf('%s() posts to %s', $method, $fragment));
      self::assertStringContainsString('instance_1', $url);
      $urls[$method] = $url;
    }

    self::assertSameSize($urls, \array_unique($urls), 'Every action event has its own endpoint.');
  }

  /**
   * Test the contextual-menu click events target their own routes too.
   */
  public function testContextualClickEventsTargetTheirOwnRoute(): void {
    $urls = [];

    foreach (self::clickEventCases() as $method => $fragment) {
      $build = $method === 'onClickSavePreset'
        ? $this->htmxEvents->onClickSavePreset([], 'instance_1', 'Name?')
        : $this->htmxEvents->{$method}([], 'instance_1');
      $url = (string) $build['#attributes']['data-hx-post'];

      self::assertStringContainsString($fragment, $url, \sprintf('%s() posts to %s', $method, $fragment));
      $urls[$method] = $url;
    }

    self::assertSameSize($urls, \array_unique($urls), 'Every click event has its own endpoint.');
  }

  /**
   * Test deleting closes the settings drawer.
   *
   * The deleted node is what the drawer is showing, so leaving it open would
   * strand the user on a form for something that no longer exists.
   */
  public function testDeleteClosesTheSettingsDrawer(): void {
    $build = $this->htmxEvents->onClickDelete([], 'instance_1');
    $attributes = \array_map('strval', $build['#attributes']);

    self::assertStringContainsString('handleSecondDrawer', \implode(' ', $attributes));
    self::assertStringContainsString('close', \implode(' ', $attributes));
  }

  /**
   * Test saving a preset asks before it writes.
   */
  public function testSavePresetPromptsFirst(): void {
    $build = $this->htmxEvents->onClickSavePreset([], 'instance_1', 'Name this preset');

    self::assertStringContainsString('Name this preset', \implode(' ', \array_map('strval', $build['#attributes'])));
  }

  /**
   * Test the form-change event wires up the source element, not the wrapper.
   *
   * It is applied to $build['source'] so the debounced PUT carries the field's
   * own value; putting it on the wrapper would submit the wrong payload.
   */
  public function testFormChangeAppliesToTheSourceElement(): void {
    $build = ['source' => ['#id' => 'edit-value']];
    $out = $this->htmxEvents->onInstanceFormChange($build, 'instance_1', 'contextual_form', 'node_1');

    $attributes = $out['source']['#attributes'];
    self::assertStringContainsString('node_1', (string) $attributes['data-hx-put']);
    self::assertStringContainsString('change', (string) $attributes['data-hx-trigger']);
    self::assertArrayNotHasKey('#attributes', $out, 'The wrapper itself is untouched.');
  }

  /**
   * Test the update button wires itself up and pulls in the source form.
   *
   * Applied to $build['update'] with an hx-include of the source's ID, so the
   * click submits the form it belongs to.
   */
  public function testUpdateButtonIncludesTheSourceForm(): void {
    $build = ['update' => [], 'source' => ['#id' => 'edit-value']];
    $out = $this->htmxEvents->onInstanceUpdateButtonClick($build, 'instance_1', 'contextual_form', 'node_1');

    $attributes = $out['update']['#attributes'];
    self::assertStringContainsString('node_1', (string) $attributes['data-hx-put']);
    self::assertStringContainsString('edit-value', (string) $attributes['data-hx-include']);
  }

  /**
   * Test both update events are no-ops on a build of the wrong shape.
   *
   * They are called against arbitrary island form structures, so the guards
   * matter: without them a form lacking a source would get half-wired.
   */
  public function testUpdateEventsIgnoreAnUnexpectedBuild(): void {
    self::assertSame([], $this->htmxEvents->onInstanceFormChange([], 'instance_1', 'contextual_form', 'node_1'));
    self::assertSame([], $this->htmxEvents->onInstanceUpdateButtonClick([], 'instance_1', 'contextual_form', 'node_1'));
    // An update button with no source form to include is left alone too.
    self::assertSame(
      ['update' => []],
      $this->htmxEvents->onInstanceUpdateButtonClick(['update' => []], 'instance_1', 'contextual_form', 'node_1')
    );
  }

  /**
   * Test third party settings post to their own island-scoped endpoint.
   */
  public function testThirdPartyFormChangeIsIslandScoped(): void {
    $build = $this->htmxEvents->onThirdPartyFormChange([], 'instance_1', 'node_1', 'styles');
    // PUT, not POST: this updates existing third party settings.
    $url = (string) $build['#attributes']['data-hx-put'];

    self::assertStringContainsString('node_1', $url);
    self::assertStringContainsString('styles', $url);
  }

  /**
   * Simple action events, keyed by method with a fragment of their route.
   *
   * @return array<string, string>
   *   Method name => expected URL fragment.
   */
  private static function actionEventCases(): array {
    return [
      'onUndo' => 'undo',
      'onRedo' => 'redo',
      'onReset' => 'restore',
      // onRevert is deliberately absent: display_builder.api_revert carries
      // `_module_dependencies: display_builder_entity_view`, so the route does
      // not exist unless that submodule is installed. Covered there instead.
      'onPublish' => 'publish',
    ];
  }

  /**
   * Contextual click events, keyed by method with a fragment of their route.
   *
   * @return array<string, string>
   *   Method name => expected URL fragment.
   */
  private static function clickEventCases(): array {
    return [
      'onClickDelete' => 'delete',
      'onClickSavePreset' => 'preset',
      'onClickPaste' => 'paste',
      'onClickDuplicate' => 'duplicate',
      'onClickPasteStyles' => 'paste-styles',
      'onClickDeleteStyles' => 'delete-styles',
    ];
  }

}
