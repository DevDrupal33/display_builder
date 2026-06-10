<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Entity\PatternPreset;
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
    $this->instance = $this->createDisplayBuilderInstance('test', 'test_instance');
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
    self::assertIsArray($response['logs']);
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
    self::assertIsArray($response['logs']);

    // The new node must appear in the slot in persisted state.
    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getCurrentState();
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
   * Tests the ::delete() method.
   */
  public function testDelete(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_delete', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $node_id,
    ]);
    $request = Request::create($url->toString(), 'DELETE');
    $response = $this->controller->delete($request, $this->instance, $node_id);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    self::assertEmpty($saved->getCurrentState());
  }

  /**
   * Tests ::paste() copies a node to root with a fresh node_id.
   */
  public function testPasteToRoot(): void {
    $source_node_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_paste', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $source_node_id,
      'parent_id' => '__root__',
      'slot_id' => '__none__',
      'slot_position' => '0',
    ]);
    $request = Request::create($url->toString(), 'POST');
    $response = $this->controller->paste($request, $this->instance, $source_node_id, '__root__', '__none__', '0');

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
    self::assertIsArray($response['logs']);

    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getCurrentState();
    // Original + pasted copy must both exist at root.
    self::assertCount(2, $state);
    $node_ids = \array_column($state, 'node_id');
    // The pasted copy gets a refreshed node_id.
    self::assertCount(2, \array_unique($node_ids), 'Pasted node must have a unique node_id.');
    self::assertContains($source_node_id, $node_ids, 'Original node must still exist at root.');
    // Both must share the same source_id.
    $source_ids = \array_unique(\array_column($state, 'source_id'));
    self::assertCount(1, $source_ids, 'Pasted node must preserve the source_id.');
  }

  /**
   * Tests ::paste() copies a node into a slot with a fresh node_id.
   */
  public function testPasteToSlot(): void {
    $container_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $source_node_id = $this->instance->attachToRoot(1, 'token', []);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_paste', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $source_node_id,
      'parent_id' => $container_id,
      'slot_id' => 'slot_1',
      'slot_position' => '0',
    ]);
    $request = Request::create($url->toString(), 'POST');
    $response = $this->controller->paste($request, $this->instance, $source_node_id, $container_id, 'slot_1', '0');

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
    self::assertIsArray($response['logs']);

    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getCurrentState();
    // Root still has both original nodes.
    self::assertCount(2, $state);
    // The container's slot_1 must now contain the pasted copy.
    $container = $state[0];
    $slot_sources = $container['source']['component']['slots']['slot_1']['sources'] ?? [];
    self::assertNotEmpty($slot_sources, 'Pasted node must appear in target slot.');
    // The child in the slot must have a different node_id than the source.
    $pasted_node_id = $slot_sources[0]['node_id'] ?? NULL;
    self::assertNotNull($pasted_node_id);
    self::assertNotSame($source_node_id, $pasted_node_id, 'Pasted node must have a refreshed node_id.');
  }

  /**
   * Tests the ::saveAsPreset() with non ASCII characters in entity label.
   */
  public function testLabelEncoding(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    // cspell:disable-next-line
    $iso_8859_1_characters = '¡¢£¤¥¦§¨©ª«¬®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõö÷øùúûüýþÿ';
    // cspell:disable-next-line
    $other_characters = '€œŒ';
    $label = $iso_8859_1_characters . $other_characters;
    $url = Url::fromRoute('display_builder.api_save_preset', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $node_id,
    ]);
    $request = Request::create($url->toString(), 'POST', []);
    $request->headers->add([
      // Browsers send HTTP headers values with the ISO-8859-1 charset.
      'hx-prompt' => \mb_convert_encoding($label, 'ISO-8859-1'),
    ]);
    $this->controller->saveAsPreset($request, $this->instance, $node_id);
    $presets = PatternPreset::loadMultiple();
    $preset = \array_first($presets);
    // Non ISO-8859-1 are replaced by a question mark.
    $label = $iso_8859_1_characters . \str_repeat('?', \mb_strlen($other_characters));
    self::assertEquals($preset->label(), $label);
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
    $state = $saved->getCurrentState();

    // The child must still be present inside slot_1.
    $slot_sources = $state[0]['source']['component']['slots']['slot_1']['sources'] ?? [];
    self::assertNotEmpty($slot_sources, 'Slot children are preserved after setSource().');
    self::assertEquals($child_id, $slot_sources[0]['node_id'], 'Child node ID is unchanged.');
  }

  /**
   * Tests that saveAsPreset() generates a valid config-entity machine name.
   *
   * The ID must match [a-z0-9_], never start with a digit, and must be unique
   * when the same label is saved twice.
   */
  public function testSaveAsPresetGeneratesValidId(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);

    $url = Url::fromRoute('display_builder.api_save_preset', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $node_id,
    ]);

    // First save with a label containing spaces and uppercase.
    $request = Request::create($url->toString(), 'POST', []);
    $request->headers->add(['hx-prompt' => 'My Preset']);
    $this->controller->saveAsPreset($request, $this->instance, $node_id);

    // Second save with the same label must produce a different ID.
    $request2 = Request::create($url->toString(), 'POST', []);
    $request2->headers->add(['hx-prompt' => 'My Preset']);
    $this->controller->saveAsPreset($request2, $this->instance, $node_id);

    $presets = PatternPreset::loadMultiple();
    self::assertCount(2, $presets, 'Both presets were saved.');

    foreach ($presets as $preset) {
      $id = $preset->id();
      self::assertMatchesRegularExpression('/^[a-z_][a-z0-9_]*$/', $id, "ID '{$id}' is a valid machine name.");
    }

    $ids = \array_keys($presets);
    self::assertCount(2, \array_unique($ids), 'Duplicate labels produce unique IDs.');
  }

}
