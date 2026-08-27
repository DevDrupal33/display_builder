<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Entity\Instance;
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
final class ApiControllerConstraintsTest extends DisplayBuilderKernelTestBase {

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
   * Test the ::attachToRoot() method when adding a new source.
   */
  public function testAttachToRoot(): void {
    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'source_id' => 'textfield',
      'position' => 0,
    ]);

    // 1. Root is empty, let's add a source. That's OK.
    self::assertCount(0, $this->instance->getCurrentState());
    $this->controller->attachToRoot($request, $this->instance);
    self::assertCount(1, $this->instance->getCurrentState());

    // 2. Let's add a second source. Still OK.
    $this->controller->attachToRoot($request, $this->instance);
    $state = $this->instance->getCurrentState();
    self::assertCount(2, $state);

    // 3. Let's add a third source. Forbidden.
    $this->controller->attachToRoot($request, $this->instance);
    self::assertSame($state, $this->instance->getCurrentState());
  }

  /**
   * Test the ::attachToRoot() method when moving an existing source.
   */
  public function testMoveToRoot(): void {
    // Let's start with a root populated with a component with the two sources
    // we will try to move.
    $parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => [
        'component_id' => 'display_builder_test:test_1',
      ],
    ]);
    $node_id_1 = $this->instance->attachToSlot($parent_id, 'slot_1', 0, 'textfield', []);
    $node_id_2 = $this->instance->attachToSlot($parent_id, 'slot_1', 0, 'textfield', []);
    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $this->instance->id(),
    ]);

    // 1. Root has only the component, let's move a source. That's OK.
    self::assertCount(1, $this->instance->getCurrentState());
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id_1,
      'position' => 0,
    ]);
    $this->controller->attachToRoot($request, $this->instance);
    self::assertCount(2, $this->instance->getCurrentState());

    // 2. Let's move a second source. Forbidden.
    $state = $this->instance->getCurrentState();
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id_2,
      'position' => 0,
    ]);
    $this->controller->attachToRoot($request, $this->instance);
    self::assertSame($state, $this->instance->getCurrentState());
  }

  /**
   * Test the ::attachToRoot() method when reordering a source inside root.
   */
  public function testMoveInsideRoot(): void {
    $node_id = $this->instance->attachToRoot(0, 'textfield', []);
    $this->instance->attachToRoot(1, 'textfield', []);
    $initial_state = $this->instance->getCurrentState();
    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $this->instance->id(),
    ]);

    // Move to the same position (so nothing change).
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id,
      'position' => 0,
    ]);
    $this->controller->attachToRoot($request, $this->instance);
    self::assertSame($initial_state, $this->instance->getCurrentState());

    // Switch positions.
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id,
      'position' => 1,
    ]);
    $this->controller->attachToRoot($request, $this->instance);
    $new_state = $this->instance->getCurrentState();
    self::assertSame($initial_state[0], $new_state[1]);
    self::assertSame($initial_state[1], $new_state[0]);
  }

  /**
   * Tests the ::attachToSlot() method when attaching a new source.
   */
  public function testAttachToSlot(): void {
    // First attach a component to root so we have a parent with a slot.
    $parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_slot_constraints'],
    ]);

    $url = Url::fromRoute('display_builder.api_slot_attach', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $parent_id,
      'slot' => 'slot_1',
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'source_id' => 'textfield',
      'position' => 0,
    ]);

    // 1. Slot is empty, let's add a first source. That's OK.
    self::assertCount(0, $this->getSlot1Sources());
    $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');
    self::assertCount(1, $this->getSlot1Sources());

    // 2. Let's add a second source. Still OK.
    $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');
    $state = $this->getSlot1Sources();
    self::assertCount(2, $state);

    // 3. Let's add a third source. Forbidden.
    $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');
    self::assertSame($state, $this->getSlot1Sources());
  }

  /**
   * Test the ::attachToSlot() method when moving an existing source.
   */
  public function testMoveToSlot(): void {
    // Populate the root level with 2 components.
    // One without any constraint, storing the sources to move.
    $initial_parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_1'],
    ]);
    $node_id_1 = $this->instance->attachToSlot($initial_parent_id, 'slot_1', 0, 'textfield', []);
    $node_id_2 = $this->instance->attachToSlot($initial_parent_id, 'slot_1', 0, 'textfield', []);
    $node_id_3 = $this->instance->attachToSlot($initial_parent_id, 'slot_1', 0, 'textfield', []);
    // The one with constraints.
    $test_parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_slot_constraints'],
    ]);

    $url = Url::fromRoute('display_builder.api_slot_attach', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $test_parent_id,
      'slot' => 'slot_1',
    ]);

    // 1. Slot is empty, let's move a first source. That's OK.
    self::assertCount(0, $this->getSlot1Sources());
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id_1,
      'position' => 0,
    ]);
    $this->controller->attachToSlot($request, $this->instance, $test_parent_id, 'slot_1');
    self::assertCount(1, $this->getSlot1Sources());

    // 2. Let's move a second source. Still OK.
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id_2,
      'position' => 0,
    ]);
    $this->controller->attachToSlot($request, $this->instance, $test_parent_id, 'slot_1');
    $state = $this->getSlot1Sources();
    self::assertCount(2, $state);

    // 3. Let's move a third source. Forbidden.
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id_3,
      'position' => 0,
    ]);
    $this->controller->attachToSlot($request, $this->instance, $test_parent_id, 'slot_1');
    self::assertSame($state, $this->getSlot1Sources());
  }

  /**
   * Test the ::attachToSlot() method when reordering a source inside a slot.
   */
  public function testMoveToSameSlot(): void {
    // Fill a component slot with maximum capacity.
    $parent_id = $this->instance->attachToRoot(0, 'component', [
      'component' => ['component_id' => 'display_builder_test:test_slot_constraints'],
    ]);
    $node_id = $this->instance->attachToSlot($parent_id, 'slot_1', 0, 'textfield', []);
    $this->instance->attachToSlot($parent_id, 'slot_1', 1, 'textfield', []);
    $initial_state = $this->getSlot1Sources();
    $url = Url::fromRoute('display_builder.api_slot_attach', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $parent_id,
      'slot' => 'slot_1',
    ]);

    // Move to the same position (so nothing change).
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id,
      'position' => 0,
    ]);
    $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');
    self::assertSame($initial_state, $this->getSlot1Sources());

    // Switch positions.
    $request = Request::create($url->toString(), 'POST', [
      'node_id' => $node_id,
      'position' => 1,
    ]);
    $this->controller->attachToSlot($request, $this->instance, $parent_id, 'slot_1');
    $new_state = $this->getSlot1Sources();
    self::assertSame($initial_state[0], $new_state[1]);
    self::assertSame($initial_state[1], $new_state[0]);
  }

  /**
   * Init a test instance with optional id and profile.
   *
   * @param ?string $profile_id
   *   Display builder profile entity ID, if NULL will create one.
   * @param ?string $instance_id
   *   Instance entity ID, if NULL set random.
   *
   * @return \Drupal\display_builder\InstanceInterface
   *   The instance for which to check access.
   */
  protected function createDisplayBuilderInstance(?string $profile_id = NULL, ?string $instance_id = NULL): InstanceInterface {
    $instance_id = $instance_id ?? $this->randomMachineName();
    $profile_id = $profile_id ?? $this->randomMachineName();
    $this->createDisplayBuilderProfile($profile_id);
    $instance = Instance::create([
      'id' => $instance_id,
      // Because there is no proper Drupal integration to rely on, we set the
      // instance ID and the profile entity themselves as plugin configuration.
      'buildable' => [
        'plugin_id' => 'test',
        'configuration' => [
          'instance_id' => $instance_id,
          'profile_id' => $profile_id,
          // This is the main part for the tests.
          'cardinality' => 2,
        ],
      ],
    ]);

    return $instance;
  }

  /**
   * Get slot 1 source.
   *
   * @return array
   *   A list of source arrays.
   */
  private function getSlot1Sources(): array {
    return $this->instance->getCurrentState()[0]['source']['component']['slots']['slot_1']['sources'] ?? [];
  }

}
