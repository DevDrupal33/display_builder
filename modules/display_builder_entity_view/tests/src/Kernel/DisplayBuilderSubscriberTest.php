<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_entity_view\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Event\DisplayBuilderEvent;
use Drupal\display_builder\Event\DisplayBuilderEvents;
use Drupal\display_builder_entity_view\EventSubscriber\DisplayBuilderSubscriber;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DisplayBuilderSubscriber event reactions.
 *
 * @internal
 */
#[CoversClass(DisplayBuilderSubscriber::class)]
#[Group('display_builder')]
#[Group('display_builder_entity_view')]
final class DisplayBuilderSubscriberTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'display_builder',
    'display_builder_entity_view',
    'display_builder_test',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_field_formatters',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['display_builder']);
    $this->installEntitySchema('display_builder_instance');
    $this->installEntitySchema('display_builder_profile');
  }

  /**
   * Tests that ON_REVERT is a subscribed event and ON_RESTORE is not.
   *
   * Only ON_REVERT requires entity-specific logic in
   * display_builder_entity_view.
   * ON_RESTORE is handled entirely by the base module subscriber.
   */
  public function testOnRevertIsSubscribed(): void {
    $subscribed = DisplayBuilderSubscriber::getSubscribedEvents();
    self::assertArrayHasKey(DisplayBuilderEvents::ON_REVERT, $subscribed);
    self::assertArrayNotHasKey(DisplayBuilderEvents::ON_RESTORE, $subscribed, 'ON_RESTORE must not be subscribed by the entity_view subscriber.');
  }

  /**
   * Tests that ON_REVERT on a non-override instance leaves state unchanged.
   *
   * The subscriber must return early without touching an instance whose ID
   * does not match the entity_override__ prefix, so the saved state must be
   * identical to the state before the event is fired.
   */
  public function testOnRevertNonOverrideInstanceIsNoop(): void {
    $instance = Instance::create([
      'id' => 'standalone__test',
      'label' => 'Test instance',
      'profileId' => 'test',
    ]);
    $node_id = $instance->attachToRoot(0, 'token', []);
    $instance->save();

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = $this->container->get('event_dispatcher');
    $event = new DisplayBuilderEvent($instance);
    $dispatcher->dispatch($event, DisplayBuilderEvents::ON_REVERT);

    // Reload from storage: state must be exactly as before the event.
    $saved = $this->entityTypeManager
      ->getStorage('display_builder_instance')
      ->load($instance->id());
    $state = $saved->getCurrentState();
    self::assertCount(1, $state, 'State is unchanged for a non-override instance.');
    self::assertSame($node_id, $state[0]['node_id']);
  }

}
