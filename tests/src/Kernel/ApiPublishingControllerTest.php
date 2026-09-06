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

    // Present state must be restored to the published state.
    $saved = $this->loadInstance($this->instance->id());
    $state = $saved->getSources();
    self::assertCount(1, $state, 'State is reset to the last saved state.');
    self::assertSame($node_id, $state[0]['node_id']);
  }

  /**
   * Tests ::revert() refuses an instance that is not an override.
   *
   * The UI never offers Revert there, so this is a direct POST. Nothing is
   * reverted, and nothing must claim it was: no ON_REVERT dispatch, an error
   * toast instead.
   */
  public function testRevertRefusesNonOverride(): void {
    $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();
    $state = $this->instance->getSources();
    self::assertCount(1, $state, 'State is modified.');

    $request = Request::create(
      '/api/display-builder/' . $this->instance->id() . '/revert',
      'POST',
    );
    $response = $this->controller->revert($request, $this->instance);

    self::assertArrayNotHasKey('history', $response, 'No island was rebuilt.');
    self::assertSame('display_builder:alert', $response['message']['#component']);

    $saved = $this->loadInstance($this->instance->id());
    self::assertSame($state, $saved->getSources(), 'The draft is untouched.');
  }

  /**
   * Tests ::restore() refuses an instance that was never published.
   *
   * Same shape as the revert case: the UI only offers Restore once
   * something is published, so nothing must claim a restore happened.
   */
  public function testRestoreRefusesUnpublished(): void {
    $this->instance->attachToRoot(0, 'token', []);
    $this->instance->save();
    $state = $this->instance->getSources();

    $request = Request::create(
      '/api/display-builder/' . $this->instance->id() . '/restore',
      'POST',
    );
    $response = $this->controller->restore($request, $this->instance);

    self::assertArrayNotHasKey('history', $response, 'No island was rebuilt.');
    self::assertSame('display_builder:alert', $response['message']['#component']);

    $saved = $this->loadInstance($this->instance->id());
    self::assertSame($state, $saved->getSources(), 'The draft is untouched.');
  }

}
