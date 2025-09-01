<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\InstanceInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the ApiController class.
 *
 * @internal
 */
#[CoversClass('\Drupal\display_builder\Controller\ApiController')]
#[Group('display_builder')]
final class ApiControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'display_builder',
    'display_builder_test',
  ];

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
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);

    // Create a real builder entity.
    $this->instance = Instance::create([
      'id' => 'test_instance',
      'label' => 'Test Builder instance',
    ]);
    $this->instance->save();

    // Get the controller from the container.
    $this->controller = $this->container->get('class_resolver')->getInstanceFromDefinition(ApiController::class);
  }

  /**
   * Test the attachToRoot() method.
   */
  public function testAttachToRoot(): void {
    // 1. Test with missing content (should return error message).
    $request = Request::create('/api/display-builder/test_instance', 'POST', []);
    $response = $this->controller->attachToRoot($request, $this->instance);
    self::assertInstanceOf(HtmlResponse::class, $response);
    self::assertStringContainsString('Missing content', $response->getContent());

    // 2. Test with a non-existing instance_id (should return error).
    $request = Request::create('/api/display-builder/test_instance', 'POST', [
      'instance_id' => 'non_existing',
      'position' => 0,
    ]);
    $response = $this->controller->attachToRoot($request, $this->instance);
    self::assertInstanceOf(HtmlResponse::class, $response);
    self::assertStringContainsString('failed', $response->getContent());

    // @todo test with valid source_id, preset_id
    self::markTestIncomplete();
  }

  /**
   * Test the attachToSlot() method.
   */
  public function testAttachToSlot(): void {
    $request = Request::create('/api/display-builder/test_builder/instance/foo/slot1', 'POST', [
      'instance_id' => 'foo',
      'position' => 0,
    ]);
    $response = $this->controller->attachToSlot($request, $this->instance, 'foo', 'slot1');
    self::assertInstanceOf(HtmlResponse::class, $response);
  }

  /**
   * Test the getInstance() method.
   */
  public function testGetInstance(): void {
    // phpcs:disable
    // $request = Request::create('/api/display-builder/test_builder/instance/foo', 'GET');

    // We have to set profile to state manager for now.
    // $this->instance->setRuntimeProfileId('test');
    // $this->instance->save();
    // phpcs:enable
    self::markTestIncomplete();
  }

  /**
   * Test the updateInstance() method.
   */
  public function testUpdateInstance(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the thirdPartySettingsUpdate() method.
   */
  public function testThirdPartySettingsUpdate(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the pasteInstance() method.
   */
  public function testPasteInstance(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the deleteInstance() method.
   */
  public function testDeleteInstance(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the saveInstanceAsPreset() method.
   */
  public function testSaveInstanceAsPreset(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the save() method.
   */
  public function testSave(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the restore() method.
   */
  public function testRestore(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the revert() method.
   */
  public function testRevert(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the undo() method.
   */
  public function testUndo(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the redo() method.
   */
  public function testRedo(): void {
    self::markTestIncomplete();
  }

  /**
   * Test the clear() method.
   */
  public function testClear(): void {
    self::markTestIncomplete();
  }

}
