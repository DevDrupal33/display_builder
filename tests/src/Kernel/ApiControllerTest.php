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
#[CoversClass(ApiController::class)]
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
    $this->installEntitySchema('display_builder_profile');
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

}
