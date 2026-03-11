<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\PatternPreset;
use Drupal\display_builder\InstanceInterface;
use Drupal\KernelTests\KernelTestBase;
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
final class ApiControllerTest extends KernelTestBase {

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
    $this->instance = Instance::create([
      'id' => 'test_instance',
      'label' => 'Test Builder instance',
      'profileId' => 'test',
    ]);
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
    $saved = \Drupal::entityTypeManager()
      ->getStorage('display_builder_instance')
      ->load($this->instance->id());
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
    self::assertSame('', $this->instance->getParentId($node_a));
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

    $saved = \Drupal::entityTypeManager()
      ->getStorage('display_builder_instance')
      ->load($this->instance->id());
    self::assertEmpty($saved->getCurrentState());
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
      'hx-prompt' => mb_convert_encoding($label, 'ISO-8859-1'),
    ]);
    $this->controller->saveAsPreset($request, $this->instance, $node_id);
    $presets = PatternPreset::loadMultiple();
    $preset = array_first($presets);
    // Non ISO-8859-1 are replaced by a question mark.
    $label = $iso_8859_1_characters . \str_repeat('?', mb_strlen($other_characters));
    self::assertEquals($preset->label(), $label);
  }

}
