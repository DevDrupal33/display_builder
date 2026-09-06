<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\Controller\ApiActionsController;
use Drupal\display_builder\Entity\PatternPreset;
use Drupal\display_builder\InstanceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the ApiActionsController class.
 *
 * @internal
 */
#[CoversClass(ApiActionsController::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ApiActionsControllerTest extends DisplayBuilderKernelTestBase {

  /**
   * The controller to test.
   */
  protected ApiActionsController $controller;

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
    // Provides the 'test' style plugin - ON_UPDATE re-renders ScaffoldPanel's
    // third-party-settings summary, which looks up every selected style ID
    // via StylePluginManager::getDefinition(), so any style ID used in
    // these tests must resolve to a real, discovered plugin.
    'ui_styles_test',
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
    $this->controller = $this->container->get('class_resolver')->getInstanceFromDefinition(ApiActionsController::class);
  }

  /**
   * Tests the ::delete() method.
   */
  public function testDelete(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_delete', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id,
    ]);
    $response = $this->controller->delete($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    self::assertEmpty($saved->getSources());
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
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $source_node_id,
      'parent_id' => '__root__',
      'slot_id' => '__none__',
      'slot_position' => '0',
    ]);
    $response = $this->controller->paste($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getSources();
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
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $source_node_id,
      'parent_id' => $container_id,
      'slot_id' => 'slot_1',
      'slot_position' => '0',
    ]);
    $response = $this->controller->paste($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getSources();
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
    ]);
    $request = Request::create($url->toString(), 'POST', ['node_id' => $node_id]);
    $request->headers->add([
      // Browsers send HTTP headers values with the ISO-8859-1 charset.
      'hx-prompt' => \mb_convert_encoding($label, 'ISO-8859-1'),
    ]);
    $this->controller->saveAsPreset($request, $this->instance);
    $presets = PatternPreset::loadMultiple();
    $preset = \array_first($presets);
    // Non ISO-8859-1 are replaced by a question mark.
    $label = $iso_8859_1_characters . \str_repeat('?', \mb_strlen($other_characters));
    self::assertEquals($preset->label(), $label);
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
    ]);

    // First save with a label containing spaces and uppercase.
    $request = Request::create($url->toString(), 'POST', ['node_id' => $node_id]);
    $request->headers->add(['hx-prompt' => 'My Preset']);
    $this->controller->saveAsPreset($request, $this->instance);

    // Second save with the same label must produce a different ID.
    $request2 = Request::create($url->toString(), 'POST', ['node_id' => $node_id]);
    $request2->headers->add(['hx-prompt' => 'My Preset']);
    $this->controller->saveAsPreset($request2, $this->instance);

    $presets = PatternPreset::loadMultiple();
    self::assertCount(2, $presets, 'Both presets were saved.');

    foreach ($presets as $preset) {
      $id = $preset->id();
      self::assertMatchesRegularExpression('/^[a-z_][a-z0-9_]*$/', $id, "ID '{$id}' is a valid machine name.");
    }

    $ids = \array_keys($presets);
    self::assertCount(2, \array_unique($ids), 'Duplicate labels produce unique IDs.');
  }

  /**
   * Tests ::pasteStyles() with the default 'replace' mode.
   */
  public function testPasteStylesReplace(): void {
    $source_id = $this->instance->attachToRoot(0, 'token', []);
    $target_id = $this->instance->attachToRoot(1, 'token', []);
    $this->instance->setThirdPartySettings($source_id, 'styles', [
      'selected' => ['test' => 'source-option'],
      'extra' => 'source-extra',
    ]);
    $this->instance->setThirdPartySettings($target_id, 'styles', [
      'selected' => ['test' => 'target-option'],
      'extra' => 'target-extra',
    ]);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_paste_styles', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $target_id,
      'source_node_id' => $source_id,
    ]);
    $response = $this->controller->pasteStyles($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    $styles = $saved->getNode($target_id)['third_party_settings']['styles'];
    // Replace wholly overwrites the target's previous styles.
    self::assertSame(['test' => 'source-option'], $styles['selected']);
    self::assertSame('source-extra', $styles['extra']);
  }

  /**
   * Tests ::pasteStyles() with 'merge' mode.
   *
   * The kernel test environment only has one real ui_styles style plugin
   * available (the 'test' plugin from the ui_styles_test fixture module -
   * StylesPanel::getSummary(), triggered by the ON_UPDATE event this method
   * dispatches, looks up every selected style ID and throws on an unknown
   * one), so this can only exercise the "source overrides target on a
   * shared category" half of merge - not "distinct categories preserved",
   * which needs a second real style ID. array_merge() is standard,
   * well-understood PHP behavior for that half.
   */
  public function testPasteStylesMerge(): void {
    $source_id = $this->instance->attachToRoot(0, 'token', []);
    $target_id = $this->instance->attachToRoot(1, 'token', []);
    $this->instance->setThirdPartySettings($source_id, 'styles', [
      'selected' => ['test' => 'source-option'],
      'extra' => 'source-extra',
    ]);
    $this->instance->setThirdPartySettings($target_id, 'styles', [
      'selected' => ['test' => 'target-option'],
      'extra' => 'target-extra',
    ]);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_paste_styles', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $target_id,
      'source_node_id' => $source_id,
      'mode' => 'merge',
    ]);
    $response = $this->controller->pasteStyles($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    $styles = $saved->getNode($target_id)['third_party_settings']['styles'];
    self::assertSame(['test' => 'source-option'], $styles['selected'], 'Source overrides target on a shared style category.');
    self::assertSame('target-extra source-extra', $styles['extra']);
  }

  /**
   * Tests ::pasteStyles() refuses a paste whose source node no longer exists.
   *
   * The source node may have been deleted (or moved) after being copied to
   * the clipboard - silently saving blank styles would look like a
   * successful paste of nothing.
   */
  public function testPasteStylesRejectsDeletedSource(): void {
    $target_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->setThirdPartySettings($target_id, 'styles', [
      'selected' => ['test' => 'target-option'],
      'extra' => 'target-extra',
    ]);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_paste_styles', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $target_id,
      'source_node_id' => 'deleted_node_id',
    ]);
    $response = $this->controller->pasteStyles($request, $this->instance);

    self::assertArrayNotHasKey('history', $response, 'A rejected paste is an error message, not a dispatch response.');

    $saved = $this->loadInstance($this->instance->id());
    $styles = $saved->getNode($target_id)['third_party_settings']['styles'];
    self::assertSame(['test' => 'target-option'], $styles['selected'], 'Target styles are untouched by the rejected paste.');
  }

  /**
   * Tests ::deleteStyles() clears a node's styles third-party setting.
   */
  public function testDeleteStyles(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->setThirdPartySettings($node_id, 'styles', [
      'selected' => ['spacing_margin_top' => 'mt-4'],
      'extra' => 'some-class',
    ]);
    $this->instance->save();

    $url = Url::fromRoute('display_builder.api_delete_styles', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', ['node_id' => $node_id]);
    $response = $this->controller->deleteStyles($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);

    $saved = $this->loadInstance($this->instance->id());
    $styles = $saved->getNode($node_id)['third_party_settings']['styles'];
    self::assertSame(['selected' => [], 'extra' => ''], $styles);
  }

}
