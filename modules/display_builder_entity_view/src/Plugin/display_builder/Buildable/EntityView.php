<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Plugin\display_builder\Buildable;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\DisplayBuildable;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\DisplayReference;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder_entity_view\BuilderDataConverter;
use Drupal\display_builder_entity_view\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\display_builder_entity_view\EntityCanonicalRouteTrait;
use Drupal\display_builder_entity_view\EntityDisplayLabelTrait;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the display_buildable.
 */
#[DisplayBuildable(
  id: 'entity_view',
  label: new TranslatableMarkup('Entity view'),
  instance_prefix: 'entity_view__',
)]
final class EntityView extends DisplayBuildablePluginBase {

  use EntityDisplayLabelTrait;
  use EntityCanonicalRouteTrait;

  /**
   * The sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * The data converter from Manage Display and Layout Builder.
   */
  protected BuilderDataConverter $dataConverter;

  /**
   * The display entity, once passed in or loaded by ::getDisplay().
   */
  protected ?EntityViewDisplayInterface $entity = NULL;

  /**
   * {@inheritdoc}
   *
   * Configuration, as stored in the Instance entity:
   * - display_id (string)
   *
   * The display itself may also be passed as 'display', to work on an object
   * the caller already holds rather than a reloaded copy of it.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    if (!isset($configuration['display'])) {
      return;
    }
    $this->entity = $configuration['display'];
    unset($this->configuration['display']);
    $this->configuration['display_id'] = $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->sampleEntityGenerator = $container->get('ui_patterns.sample_entity_generator');
    $instance->dataConverter = $container->get('display_builder_entity_view.builder_data_converter');
    $instance->bundleInfo = $container->get('entity_type.bundle.info');
    $instance->entityDisplayRepository = $container->get('entity_display.repository');

    return $instance;
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
   * {@inheritdoc}
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $permission = 'administer ' . $params['entity'] . ' display';

    return $account->hasPermission($permission) ? AccessResult::allowed() : AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel(): ?string {
    // Through ::getDisplay(), not the property: the display is resolved
    // lazily, so reading it raw only names a plugin somebody already handed
    // the object to. A plugin built from a stored display_id - which is how
    // Instance::label() builds it - would answer NULL for a display that is
    // right there in config.
    $display = $this->getDisplay();

    if (!$display) {
      return NULL;
    }
    $entity_type_id = $display->getTargetEntityTypeId();

    return $this->composeDisplayLabel(
      $this->getBundleLabel($entity_type_id, $display->getTargetBundle()),
      $this->getViewModeLabel($entity_type_id, $display->getMode()),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $display = $this->getDisplay();
    $entity_type_id = $display->getTargetEntityTypeId();
    $fieldable_entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $parameters = [
      $bundle_parameter_key => $display->getTargetBundle(),
      'view_mode_name' => $display->getMode(),
    ];
    $route_name = \sprintf('display_builder_entity_view.%s', $entity_type_id);

    return Url::fromRoute($route_name, $parameters);
  }

  /**
   * {@inheritdoc}
   *
   * Unlike page_layout and view_display, entity view displays have no
   * dedicated admin page of their own - they are managed per bundle through
   * Field UI. The Instances panel's group heading links to the flat instance
   * list instead, pre-filtered to this buildable's own kind.
   */
  public function getCollectionUrl(): Url {
    return Url::fromRoute('entity.display_builder_instance.collection', [], [
      'query' => ['context' => $this->getPluginId()],
    ]);
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
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $profile_id = $this->getDisplay()->getThirdPartySetting('display_builder', DisplayBuildableInterface::PROFILE_PROPERTY);

    if ($profile_id === NULL) {
      return NULL;
    }

    /** @var \Drupal\display_builder\Entity\ProfileInterface $profile */
    $profile = $this->entityTypeManager->getStorage('display_builder_profile')->load($profile_id);

    return $profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->getDisplay()->getThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, []);
  }

  /**
   * {@inheritdoc}
   */
  public function publish(): void {
    $data = $this->getInstance()->getSources();
    $display = $this->getDisplay();
    $display->setThirdPartySetting('display_builder', DisplayBuildableInterface::SOURCES_PROPERTY, $data);
    $display->save();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    $display = $this->getDisplay();

    // Usually an entity is new if no ID exists for it yet.
    if ($display->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', self::getPrefix(), \str_replace('.', '__', (string) $display->id()));
  }

  /**
   * {@inheritdoc}
   */
  public function collectInstances(): array {
    $instances = [];
    $storage = $this->entityTypeManager->getStorage('entity_view_display');

    foreach ($storage->loadMultiple() as $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      $display_builder = $display->getThirdPartySettings('display_builder');

      if (!empty($display_builder[DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL)) {
        /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
        $buildable = $this->displayBuildableManager->createInstance(
          'entity_view',
          ['display' => $display]
        );
        $buildable->initInstanceIfMissing();
        $instance = $buildable->getInstance();
        $instances[(string) $instance->id()] = $instance;
      }
    }

    return $instances;
  }

  /**
   * {@inheritdoc}
   *
   * Every enabled display is listed, not only the ones already built with
   * Display Builder: the nesting chain a user is chasing usually runs through
   * a display that is still a plain formatter display, and hiding those is
   * what made the panel fail at the moment it was needed.
   *
   * Disabled displays are skipped because a disabled display is not what
   * renders: core falls back to the bundle's default. So is the '_custom' mode,
   * which exists to render fields in isolation and is never a page level.
   */
  public function collectDisplays(array $options = []): array {
    $references = [];

    foreach ($this->entityTypeManager->getStorage('entity_view_display')->loadMultiple() as $display) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display */
      if (!$display->status() || \str_starts_with($display->getMode(), '_')) {
        continue;
      }

      /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
      $buildable = $this->displayBuildableManager->createInstance('entity_view', ['display' => $display]);
      $instance_id = $buildable->getInstanceId();

      if ($instance_id === NULL) {
        continue;
      }

      $settings = $display->getThirdPartySettings('display_builder');
      $built = !empty($settings[DisplayBuildableInterface::PROFILE_PROPERTY] ?? NULL);
      // Read from config, never from an Instance entity: this listing must not
      // touch instance storage.
      $sources = $settings[DisplayBuildableInterface::SOURCES_PROPERTY] ?? [];
      $empty = empty($sources);
      $settings_url = self::manageDisplayUrl($instance_id);
      $url = $built ? $buildable->getBuilderUrl() : $settings_url;

      if ($url === NULL) {
        continue;
      }

      $references[] = new DisplayReference(
        instanceId: $instance_id,
        kind: $buildable->label(),
        label: $buildable->getDisplayLabel() ?? $instance_id,
        url: $url,
        built: $built,
        empty: $built && $empty,
        settingsUrl: $settings_url,
        publishedHash: $built ? Instance::getUniqId($sources) : NULL,
      );
    }

    return $references;
  }

  /**
   * {@inheritdoc}
   */
  public function previewWithChrome(): bool {
    $display = $this->getDisplay();

    return $this->previewsFullPage($display->getTargetEntityTypeId(), $display->getTargetBundle(), $display->getMode());
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids): array {
    // Contexts not needed by Display Builder but expected by UiPatterns source
    // plugins.
    $contexts = [];
    $display = $this->getDisplay();
    $entity_type_id = $display->getTargetEntityTypeId();
    $bundle = $display->getTargetBundle();
    $sampleEntity = $this->sampleEntityGenerator->get($entity_type_id, $bundle);
    $contexts['entity'] = EntityContext::fromEntity(self::markSampleEntity($sampleEntity));
    $contexts['view_mode'] = new Context(ContextDefinition::create('string'), $display->getMode());
    $contexts['bundle'] = new Context(ContextDefinition::create('string'), $bundle);
    return RequirementsContext::addToContext(['entity'], $contexts);
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitialSources(): array {
    // Get the sources stored in config.
    $sources = $this->getSources();

    if (empty($sources)) {
      // initialImport() has two implementations:
      // - EntityViewDisplay::initialImport()
      // - LayoutBuilderEntityViewDisplay::initialImport()
      /** @var \Drupal\display_builder_entity_view\Entity\DisplayBuilderEntityDisplayInterface $display */
      $display = $this->getDisplay();

      if ($display instanceof LayoutBuilderEntityViewDisplay && $display->getThirdPartySetting('layout_builder', 'enabled')) {
        $sections = $display->getThirdPartySetting('layout_builder', 'sections', []);

        if (!\is_array($sections)) {
          $sections = [];
        }

        $sources = $this->dataConverter->convertFromLayoutBuilder($sections);
        $this->initialDataSource = 'layout_builder';
      }
      else {
        $sources = $this->dataConverter->convertFromManageDisplay($display->getTargetEntityTypeId(), $display->getTargetBundle(), $display->getComponents());
        $this->initialDataSource = 'manage_display';
      }
    }

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInitializationMessage(): TranslatableMarkup {
    if ($this->initialDataSource === 'layout_builder') {
      return $this->t('Import display from Layout Builder configuration');
    }

    if ($this->initialDataSource === 'manage_display') {
      return $this->t('Import display from Manage Display configuration');
    }

    return $this->t('Initialize display from existing Entity View Display configuration');
  }

  /**
   * Gets the entity view display this plugin builds.
   *
   * Loaded on demand: the constructor runs before ::create() has injected the
   * entity type manager.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface|null
   *   The display, or NULL if the configured one no longer exists.
   */
  protected function getDisplay(): ?EntityViewDisplayInterface {
    if ($this->entity === NULL) {
      /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface|null $display */
      $display = $this->entityTypeManager
        ->getStorage('entity_view_display')
        ->load($this->configuration['display_id'] ?? '');
      $this->entity = $display;
    }

    return $this->entity;
  }

  /**
   * The core Manage display URL, when field_ui provides the route.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return \Drupal\Core\Url|null
   *   The URL, or NULL when field_ui is not installed, and its routes with it.
   */
  private static function manageDisplayUrl(string $instance_id): ?Url {
    $url = self::getDisplayUrlFromInstanceId($instance_id);

    return self::routeExists($url->getRouteName()) ? $url : NULL;
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
   * Marks a sample entity as a preview, the way core's own previews do.
   *
   * A sample entity is never saved, so it has no ID, and a formatter that
   * needs one has nothing to work with: the comment field's "Add comment"
   * form loads the commented entity by ID and asserts its way out on NULL,
   * taking down the render of everything around it. `in_preview` is the flag
   * core already uses to say "not a real page, stand down" - node preview
   * sets it, and comment, history and content_moderation all check it.
   *
   * Only sample entities get it. A display bound to a real entity is a real
   * page and must render like one.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity, saved or sample.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The same entity, for chaining into a context.
   *
   * @see \Drupal\comment\Plugin\Field\FieldFormatter\CommentDefaultFormatter::viewElements()
   */
  private static function markSampleEntity(EntityInterface $entity): EntityInterface {
    if ($entity->id() === NULL) {
      // Undeclared on purpose, by core: `in_preview` is a plain dynamic
      // property that NodeForm and CommentForm set the same way.
      // @phpstan-ignore property.notFound
      $entity->in_preview = TRUE;
    }

    return $entity;
  }

}
