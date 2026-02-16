<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\InstanceStorageInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'entity_view',
  label: new TranslatableMarkup('Entity view'),
  instance_prefix: 'entity_view__',
)]
final class EntityView extends DisplayBuildablePluginBase {

  /**
   * The page layout entity storing the display.
   */
  public ?EntityViewDisplayInterface $entity = NULL;

  /**
   * The sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->sampleEntityGenerator = \Drupal::service('ui_patterns.sample_entity_generator');
    $this->entity = $configuration['entity'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    return 'entity';
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    if (!\str_starts_with($instance_id, self::getPrefix())) {
      return NULL;
    }
    [, $entity, $bundle, $view_mode] = \explode('__', $instance_id);

    return [
      'entity' => $entity,
      'bundle' => $bundle,
      'view_mode' => $view_mode,
    ];
  }

  /**
   * Checks access.
   *
   * @param string $instance_id
   *   Instance entity ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @see \Drupal\display_builder\InstanceAccessControlHandler
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $permission = 'administer ' . $params['entity'] . ' display';

    return $account->hasPermission($permission) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $fieldable_entity_type = $this->entityTypeManager->getDefinition($this->entity->getTargetEntityTypeId());
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $parameters = [
      $bundle_parameter_key => $this->entity->getTargetBundle(),
      'view_mode_name' => $this->entity->getMode(),
    ];
    $route_name = \sprintf('display_builder_entity_view.%s', $this->entity->getTargetEntityTypeId());

    return Url::fromRoute($route_name, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $route_name = \sprintf('display_builder_entity_view.%s', $params['entity']);

    return Url::fromRoute($route_name, $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $route_name = \sprintf('entity.entity_view_display.%s.view_mode', $params['entity']);

    return Url::fromRoute($route_name, $params);
  }

  /**
   * Returns the display builder instance.
   *
   * @return \Drupal\display_builder\ProfileInterface|null
   *   The display builder instance, or NULL if not set.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function getProfile(): ?ProfileInterface {
    $display_builder_id = $this->entity->getThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY);

    if ($display_builder_id === NULL) {
      return NULL;
    }

    return $this->loadDisplayBuilder($display_builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      // initialImport() has two implementations:
      // - EntityViewDisplay::initialImport()
      // - LayoutBuilderEntityViewDisplay::initialImport()
      /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $display */
      $display = $this->entity;
      $sources = $display->initialImport();
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $entity_type_id = $this->entity->getTargetEntityTypeId();
    $bundle = $this->entity->getTargetBundle();
    $view_mode = $this->entity->getMode();
    $sampleEntity = $this->sampleEntityGenerator->get($entity_type_id, $bundle);
    $contexts = [
      'entity' => EntityContext::fromEntity($sampleEntity),
      'bundle' => new Context(ContextDefinition::create('string'), $bundle),
      'view_mode' => new Context(ContextDefinition::create('string'), $view_mode),
    ];

    return RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
  }

  /**
   * Returns the sources of the display builder.
   *
   * @return array
   *   The sources of the display builder.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function getSources(): array {
    return $this->entity->getThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, []);
  }

  /**
   * Saves the sources of the display builder.
   *
   * @see \Drupal\display_builder\DisplayBuildableInterface
   */
  public function saveSources(): void {
    $data = $this->getInstance()->getCurrentState();
    $this->entity->setThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, $data);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->entity->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', self::getPrefix(), \str_replace('.', '__', (string) $this->entity->id()));
  }

  /**
   * {@inheritdoc}
   */
  public static function collectInstances(InstanceStorageInterface $instanceStorage, ?EntityTypeManagerInterface $entityTypeManager = NULL): array {
    $instances = [];
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $storage = $entityTypeManager->getStorage('entity_view_display');
    $instance_storage = $entityTypeManager->getStorage('display_builder_instance');

    foreach ($storage->loadMultiple() as $display_id => $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $display_builder = $display->getThirdPartySettings('display_builder');

      if (!empty($display_builder[DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL)) {
        $instance_id = \sprintf('%s%s', self::getPrefix(), \str_replace('.', '__', $display_id));
        // We are OK with keeping the null values if the instance entity
        // doesn't exists in storage. So the caller can decide to create
        // the missing Instance entities.
        $instances[$instance_id] = $instance_storage->load($instance_id);
      }
    }

    return $instances;
  }

  /**
   * Returns the URL for the display builder from an instance id.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return array
   *   The url parameters for this instance id.
   */
  private static function getUrlParamsFromInstanceId(string $instance_id): array {
    [, $entity, $bundle, $view_mode] = \explode('__', $instance_id);
    $fieldable_entity_type = \Drupal::service('entity_type.manager')->getDefinition($entity);
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';

    return [
      $bundle_parameter_key => $bundle,
      'view_mode_name' => $view_mode,
      'entity' => $entity,
    ];
  }

  /**
   * Loads display builder by id.
   *
   * @param string $display_builder_id
   *   The display builder ID.
   *
   * @return \Drupal\display_builder\ProfileInterface|null
   *   The display builder, or NULL if not found.
   */
  private function loadDisplayBuilder(string $display_builder_id): ?ProfileInterface {
    if (empty($display_builder_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');

    /** @var \Drupal\display_builder\ProfileInterface $display_builder */
    $display_builder = $storage->load($display_builder_id);

    return $display_builder;
  }

}
