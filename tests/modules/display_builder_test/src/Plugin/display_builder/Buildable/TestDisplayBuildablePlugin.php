<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\Entity\ProfileInterface;

/**
 * Test class for DisplayBuildablePluginBase.
 *
 * @internal
 */
#[DisplayBuildable(
  id: 'test',
  label: new TranslatableMarkup('Test'),
  instance_prefix: 'test__',
)]
final class TestDisplayBuildablePlugin extends DisplayBuildablePluginBase {

  /**
   * Profile entity ID.
   */
  public ?ProfileInterface $profile = NULL;

  /**
   * State API.
   */
  private StateInterface $state;

  /**
   * Instance ID.
   */
  private ?string $instanceId;

  /**
   * Cardinality.
   */
  private int $cardinality = self::CARDINALITY_UNLIMITED;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = \Drupal::state();
    // Because there is no proper Drupal integration to rely on, we set the
    // instance ID and the profile entity themselves as plugin configuration.
    // Nullable because ::collectDisplays() is answered by a plugin built with
    // no configuration at all.
    $this->instanceId = $configuration['instance_id'] ?? NULL;
    $this->profile = Profile::load($configuration['profile_id'] ?? '');
    // For ApiControllerConstraintsTest.
    $this->cardinality = $configuration['cardinality'] ?? self::CARDINALITY_UNLIMITED;
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    return $this->profile;
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
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    // Because there is no proper Drupal integration to rely on, we store the
    // published data in a tempstore instead of a permanent storage.
    $data = $this->getInstance()?->getCurrentState();

    if ($data !== NULL) {
      $this->state->set('display_builder_test__' . $this->instanceId, $data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    // Because there is no proper Drupal integration to rely on, we store the
    // published data in a tempstore instead of a permanent storage.
    return $this->state->get('display_builder_test__' . $this->instanceId) ?? [];
  }

  /**
   * {@inheritdoc}
   *
   * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceAfterLastUsed
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    return [
      'id' => $instance_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function collectInstances(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRootCardinality(): int {
    return $this->cardinality;
  }

}
