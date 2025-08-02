<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderInterface;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;

/**
 * A class implementing WithDisplayBuilderInterface for the demos.
 */
class MockEntity implements WithDisplayBuilderInterface {

  /**
   * Instance ID as managed by the State Manager.
   */
  protected ?string $instanceId;

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

  /**
   * MockEntity constructor.
   *
   * @param string $instance_id
   *   The instance ID as managed by the State Manager.
   * @param string $display_builder_id
   *   The entity ID of the Display Builder config entity.
   * @param array $sources
   *   The UI Patterns source tree.
   */
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
    if (!$instance_id = $this->getInstanceId()) {
      return;
    }

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
