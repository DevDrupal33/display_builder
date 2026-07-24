<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Controller\ApiPublishingController;
use Drupal\display_builder\InstanceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the ApiPublishingController class.
 *
 * @internal
 */
#[CoversClass(ApiPublishingController::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class ApiPublishingControllerTest extends DisplayBuilderKernelTestBase {

  /**
   * The controller to test.
   */
  protected ApiPublishingController $controller;

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
    $this->controller = $this->container->get('class_resolver')->getInstanceFromDefinition(ApiPublishingController::class);
  }

  /**
   * Tests ::restore() dispatches ON_RESTORE and resets present state.
   *
   * The instance is mutated after save, then restore() must reset the present
   * state back to what was saved.
   */
  public function testRestore(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    // We need to save before publishing so TestDisplayBuildablePlugin plugin
    // will be able to load the Instance entity.
    $this->instance->save();
    // Simulate publishing to the permanent storage (normally done via the
    // publish route).
    $this->instance->publish();

    // Mutate after save — this unpublished change should be discarded by
    // restore.
    $this->instance->attachToRoot(1, 'token', []);

    $request = Request::create(
      '/api/display-builder/' . $this->instance->id() . '/restore',
      'POST',
    );
    $response = $this->controller->restore($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
    self::assertIsArray($response['logs']);

    // Present state must be restored to the published state.
    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getCurrentState();
    self::assertCount(1, $state, 'State is reset to the last saved state.');
    self::assertSame($node_id, $state[0]['node_id']);
  }

  /**
   * Tests ::revert() dispatches ON_REVERT and returns the expected shape.
   *
   * For a non-override (standalone) instance the subscriber returns early,
   * so the saved state must be identical to the state before the call.
   */
  public function testRevert(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();

    $request = Request::create(
      '/api/display-builder/' . $this->instance->id() . '/revert',
      'POST',
    );
    $response = $this->controller->revert($request, $this->instance);

    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
    self::assertIsArray($response['logs']);

    // Non-override instance: subscriber returns early, state must be unchanged.
    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getCurrentState();
    self::assertCount(1, $state, 'State is unchanged for a non-override instance.');
    self::assertSame($node_id, $state[0]['node_id']);
  }

}
