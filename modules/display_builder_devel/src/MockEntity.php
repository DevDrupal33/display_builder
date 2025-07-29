<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\EntityWithDisplayBuilderInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * A class implementing EntityWithDisplayBuilderInterface for the demos.
 */
class MockEntity implements EntityWithDisplayBuilderInterface {

  /**
   * Instance ID as managed by the State Manager.
   *
   * @var string
   */
  protected $instanceId;

  /**
   * Entity ID of the Display Builder config entity.
   */
  protected string $displayBuilderId;

  /**
   * UI Patterns source tree.
   */
  protected array $sources;

  /**
   * The display builder state manager.
   */
  protected StateManagerInterface $stateManager;

  /**
   * The entity type interface.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(string $instance_id, string $display_builder_id, array $sources) {
    $this->instanceId = $instance_id;
    $this->displayBuilderId = $display_builder_id;
    $this->sources = $sources;
    $this->stateManager = \Drupal::service('display_builder.state_manager');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('display_builder_devel.view', ['builder_id' => $this->getInstanceId()]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    return Url::fromRoute('display_builder_devel.view', ['builder_id' => $instance_id]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayBuilder(): ?DisplayBuilderInterface {
    $storage = $this->entityTypeManager->getStorage('display_builder');

    /** @var \Drupal\display_builder\DisplayBuilderInterface $entity */
    $entity = $storage->load($this->displayBuilderId);

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    return $this->instanceId;
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    $instance_id = $this->getInstanceId();

    if (!$this->stateManager->load($instance_id)) {
      $this->stateManager->create($instance_id, (string) $this->getDisplayBuilder()->id(), $this->sources, []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    // We take them directly from the State Manager.
    return $this->stateManager->getCurrentState($this->getInstanceId());
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {}

}
