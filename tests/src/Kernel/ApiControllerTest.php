<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Entity\Instance;
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

  protected ApiController $controller;

  /**
   * @var \Drupal\display_builder\Entity\Instance
   */
  protected $instance;

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

    // 3. Test with a valid instance_id.
    // $this->builder->set('foo', ['bar']);
    // $request = Request::create('/api/display-builder/test_instance', 'POST', [
    //   'instance_id' => 'test_instance',
    //   'position' => 0,
    // ]);
    // $response = $this->controller->attachToRoot($request, $this->instance);
    // self::assertInstanceOf(HtmlResponse::class, $response);
    // self::assertNotEmpty($response->getContent());

    // 4. Optionally, test with source_id if your builder supports it.
    // $request = Request::create('/api/display-builder/test_instance', 'POST', [
    //   'source_id' => 'some_source',
    //   'source' => json_encode(['foo' => 'bar']),
    //   'position' => 0,
    // ]);
    // $response = $this->controller->attachToRoot($request, $this->instance);
    // $this->assertInstanceOf(HtmlResponse::class, $response);
  }

  public function testAttachToSlot(): void {
    $request = Request::create('/api/display-builder/test_builder/instance/foo/slot1', 'POST', [
      'instance_id' => 'foo',
      'position' => 0,
    ]);
    $response = $this->controller->attachToSlot($request, $this->instance, 'foo', 'slot1');
    self::assertInstanceOf(HtmlResponse::class, $response);
  }

  public function testGetInstance(): void {
    $request = Request::create('/api/display-builder/test_builder/instance/foo', 'GET');

    // We have to set profile to state manager for now.
    $this->instance->setRuntimeProfileId('test');
    $this->instance->save();

    // dump($this->instance->getProfile());
    // $this->instance->setRuntimeData(['foo' => 'bar']);
    // $result = $this->controller->getInstance($request, $this->instance, 'foo');
    // self::assertIsArray($result);
  }

  // public function testDeleteInstance(): void {
  //   $request = Request::create('/api/display-builder/test_builder/instance/foo', 'DELETE');
  //   $response = $this->controller->deleteInstance($request, $this->instance, 'foo');
  //   self::assertInstanceOf(HtmlResponse::class, $response);
  // }

  // public function testUpdateInstance(): void {
  //   $request = Request::create('/api/display-builder/test_builder/instance/foo', 'POST', [
  //     'form_id' => 'test_form',
  //     // Add other required form data here.
  //   ]);
  //   $result = $this->controller->updateInstance($request, $this->instance, 'foo');
  //   self::assertIsArray($result);
  // }

  // public function testThirdPartySettingsUpdate(): void {
  //   $request = Request::create('/api/display-builder/test_builder/instance/foo/settings/bar', 'PUT', [
  //     'form_id' => 'test_form',
  //     // Add other required form data here.
  //   ]);
  //   $response = $this->controller->thirdPartySettingsUpdate($request, $this->instance, 'foo', 'bar');
  //   self::assertInstanceOf(HtmlResponse::class, $response);
  // }

}
