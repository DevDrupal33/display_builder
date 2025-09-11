<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_form\Entity;

use Drupal\Core\Entity\Entity\EntityFormDisplay as CoreEntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\ui_patterns\Element\ComponentElementBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Provides an entity view form entity that has a display builder.
 */
class EntityFormDisplay extends CoreEntityFormDisplay implements DisplayBuildableInterface {

  /**
   * The sample entity generator.
   */
  protected SampleEntityGeneratorInterface $sampleEntityGenerator;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The component element builder service.
   */
  protected ComponentElementBuilder $componentElementBuilder;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * Constructs the EntityFormDisplay.
   *
   * @param array $values
   *   The values to initialize the entity with.
   * @param string $entity_type
   *   The entity type ID.
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $this->sampleEntityGenerator = \Drupal::service('ui_patterns.sample_entity_generator');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->componentElementBuilder = \Drupal::service('ui_patterns.component_element_builder');
  }

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    return 'entity_form__';
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
    if (!\str_starts_with($instance_id, EntityFormDisplay::getPrefix())) {
      return NULL;
    }
    [, $entity, $bundle, $form_mode] = \explode('__', $instance_id);

    return [
      'entity' => $entity,
      'bundle' => $bundle,
      'form_mode' => $form_mode,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    $fieldable_entity_type = $this->entityTypeManager->getDefinition($this->getTargetEntityTypeId());
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';
    $parameters = [
      $bundle_parameter_key => $this->getTargetBundle(),
      'form_mode_name' => $this->getMode(),
    ];
    $route_name = \sprintf('display_builder_entity_form.%s', $this->getTargetEntityTypeId());

    return Url::fromRoute($route_name, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $route_name = \sprintf('display_builder_entity_form.%s', $params['entity']);

    return Url::fromRoute($route_name, $params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    $params = self::getUrlParamsFromInstanceId($instance_id);
    $route_name = \sprintf('entity.entity_form_display.%s.form_mode', $params['entity']);

    return Url::fromRoute($route_name, $params);
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    $profile_id = $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::PROFILE_PROPERTY);

    if ($profile_id === NULL) {
      return NULL;
    }

    return $this->loadProfile($profile_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Usually an entity is new if no ID exists for it yet.
    if ($this->isNew()) {
      return NULL;
    }

    return \sprintf('%s%s', EntityFormDisplay::getPrefix(), \str_replace('.', '__', $this->id));
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceStorage $storage */
    $storage = $this->entityTypeManager->getStorage('display_builder_instance');

    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $storage->load($this->getInstanceId());

    if (!$instance) {
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    // @todo import
    return $this->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    $entity_type_id = $this->getTargetEntityTypeId();
    $bundle = $this->getTargetBundle();
    $form_mode = $this->getMode();
    $sampleEntity = $this->sampleEntityGenerator->get($entity_type_id, $bundle);
    $contexts = [
      'entity' => EntityContext::fromEntity($sampleEntity),
      'bundle' => new Context(ContextDefinition::create('string'), $bundle),
      'form_mode' => new Context(ContextDefinition::create('string'), $form_mode),
    ];

    return RequirementsContext::addToContext([self::getContextRequirement()], $contexts);
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->getThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, []);
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {
    $data = $this->getInstance()->getCurrentState();
    $this->setThirdPartySetting('display_builder', ConfigFormBuilderInterface::SOURCES_PROPERTY, $data);
    $this->save();
  }

  /**
   * Is display builder enabled?
   *
   * @return bool
   *   The display builder is enabled if there is a Display Builder entity.
   */
  public function isDisplayBuilderEnabled(): bool {
    // Display Builder must not be enabled for the '_custom' view mode that is
    // used for on-the-fly rendering of fields in isolation from the entity.
    if ($this->getOriginalMode() === static::CUSTOM_MODE) {
      return FALSE;
    }

    return (bool) $this->getProfile();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(FieldableEntityInterface $entity, array &$form, FormStateInterface $form_state): void {
    // If no display builder enabled, stop here and return what is built with
    // 'Form Display'.
    if (!$this->isDisplayBuilderEnabled()) {
      parent::buildForm($entity, $form, $form_state);

      return;
    }
    $sources = $this->getSources();
    $contexts = $this->getContextsForEntity($entity);
    $label = new TranslatableMarkup('@entity being viewed', [
      '@entity' => $entity->getEntityType()->getSingularLabel(),
    ]);
    $contexts['display_builder.entity'] = EntityContext::fromEntity($entity, (string) $label);

    $fake_build = [];

    foreach ($sources as $source_data) {
      $fake_build = $this->componentElementBuilder->buildSource($fake_build, 'content', [], $source_data, $contexts);
    }
    $form['content'] = $fake_build['#slots']['content'] ?? [];
  }

  /**
   * Gets the available contexts for a given entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   An array of context objects for a given entity.
   */
  protected function getContextsForEntity(FieldableEntityInterface $entity): array {
    $available_context_ids = \array_keys($this->contextRepository()->getAvailableContexts());

    return [
      'form_mode' => new Context(ContextDefinition::create('string'), $this->getMode()),
      'entity' => EntityContext::fromEntity($entity),
      'display' => EntityContext::fromEntity($this),
    ] + $this->contextRepository()->getRuntimeContexts($available_context_ids);
  }

  /**
   * Wraps the context repository service.
   *
   * @return \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   *   The context repository service.
   */
  protected function contextRepository(): ContextRepositoryInterface {
    return \Drupal::service('context.repository');
  }

  /**
   * Gets the Display Builder instance.
   *
   * @return \Drupal\display_builder\InstanceInterface|null
   *   A display builder instance.
   */
  protected function getInstance(): ?InstanceInterface {
    if (!isset($this->instance)) {
      /** @var \Drupal\display_builder\InstanceInterface|null $instance */
      $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($this->getInstanceId());
      $this->instance = $instance;
    }

    return $this->instance;
  }

  /**
   * Loads display builder profile by id.
   *
   * @param string $profile_id
   *   The display builder ID.
   *
   * @return \Drupal\display_builder\ProfileInterface|null
   *   The display builder, or NULL if not found.
   */
  private function loadProfile(string $profile_id): ?ProfileInterface {
    if (empty($profile_id)) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('display_builder_profile');

    /** @var \Drupal\display_builder\ProfileInterface $profile */
    $profile = $storage->load($profile_id);

    return $profile;
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
    [, $entity, $bundle, $form_mode] = \explode('__', $instance_id);
    $fieldable_entity_type = \Drupal::service('entity_type.manager')->getDefinition($entity);
    $bundle_parameter_key = $fieldable_entity_type->getBundleEntityType() ?: 'bundle';

    return [
      $bundle_parameter_key => $bundle,
      'form_mode_name' => $form_mode,
      'entity' => $entity,
    ];
  }

}
