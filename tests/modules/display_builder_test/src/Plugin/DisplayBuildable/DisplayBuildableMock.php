<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\DisplayBuildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\ProfileInterface;

/**
 * A class implementing DisplayBuildableInterface for tests.
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterface
 */
#[DisplayBuildable(
  id: 'mock',
  label: new TranslatableMarkup('Mock for tests'),
  instance_prefix: 'test__',
)]
final class DisplayBuildableMock extends DisplayBuildablePluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $instance_id = $configuration['instance_id'];
    $profile_id = $configuration['profile_id'];

    $storage = \Drupal::service('entity_type.manager')->getStorage('display_builder_instance');
    $instance = $storage->create([
      'id' => $instance_id ?? 'test__' . \uniqid(),
    ]);

    if ($profile_id) {
      $instance->setProfile($profile_id);
    }
    $instance->save();

    $this->instance = $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    return 'test__';
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
  public static function checkInstanceId(string $instance_id): ?array {
    return [
      'id' => $instance_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
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
  public static function getUrlFromInstanceId(string $instance_id): Url {
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
  public function getProfile(): ?ProfileInterface {
    return $this->instance?->getProfile();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    return $this->instance ? (string) $this->instance->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {}

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    return $this->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->instance->getCurrentState();
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    return [];
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
  public function saveSources(): void {}

}
