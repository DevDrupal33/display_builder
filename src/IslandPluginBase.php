<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for island plugins.
 */
abstract class IslandPluginBase extends PluginBase implements IslandInterface {

  use HtmxTrait;
  use RenderableBuilderTrait;
  use StringTranslationTrait;

  /**
   * The island data.
   */
  protected array $data;

  /**
   * The builder id.
   */
  protected string $builderId;

  /**
   * The builder instance entity.
   */
  protected ?InstanceInterface $builder = NULL;

  /**
   * The current island id which trigger action.
   */
  protected string $currentIslandId;

  /**
   * The instance id for this plugin.
   */
  protected ?string $instanceId = NULL;

  /**
   * The form builder.
   */
  protected ?FormBuilderInterface $formBuilder = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $sdcManager,
    protected HtmxEvents $htmxEvents,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SourcePluginManager $sourceManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->data = $configuration;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.sdc'),
      $container->get('display_builder.htmx_events'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.ui_patterns_source'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $this->builder = $builder;

    $builder_id = (string) $builder->id();
    $this->builderId = $builder_id;
    $this->instanceId = $data['node_id'] ?? NULL;

    // First, get specific data for the plugin.
    if (isset($data['_third_party_settings'][$this->getPluginId()])) {
      $this->data = $data['_third_party_settings'][$this->getPluginId()];
    }
    // Otherwise, fallback on global data.
    else {
      $this->data = $data;
    }

    if (!$this->isApplicable()) {
      return [];
    }

    if (!$this instanceof IslandWithFormInterface) {
      return [];
    }

    $contexts = $this->configuration['contexts'] ?? [];

    $form_state = new FormState();

    // We have to force form to not rebuild, otherwise, we are losing data of an
    // island plugin when another is submitted.
    // Example: submitting Styles Panel make lose default form values for
    // Instance Form Panel.
    $form_state->setRebuild(FALSE);
    $form_state->setExecuted();

    $form_state->addBuildInfo('args', [$this->getArgs(), $contexts, $options]);
    $form_state->setTemporaryValue('gathered_contexts', $contexts);

    $build = $this->formBuilder()->buildForm($this::getFormClass(), $form_state);

    return $this->afterBuild($build, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function afterBuild(array $element, FormStateInterface $form_state): array {
    if (!$this->isApplicable()) {
      return [];
    }

    $definition = $this->getPluginDefinition();
    $island_id = $definition instanceof PluginDefinitionInterface ? $definition->id() : ($definition['id'] ?? '');

    return $this->htmxEvents->onThirdPartyFormChange($element, $this->builderId, $this->instanceId, $island_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    $definition = $this->getPluginDefinition();

    return $this->instanceId !== NULL && \is_array($definition) && !empty($this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public static function keyboardShortcuts(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeId(): string {
    return $this->pluginDefinition['type']->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlId(string $builder_id): string {
    return \implode('-', ['island', $builder_id, $this->pluginDefinition['id']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): ?string {
    return $this->pluginDefinition['icon'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(string $builder_id, string $instance_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(string $builder_id, array $data): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(string $builder_id, string $instance_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(string $builder_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onSave(string $builder_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onPresetSave(string $builder_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return \array_merge($this->defaultConfiguration(), $this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = \array_merge($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function alterRenderable(InstanceInterface $instance, array $build): array {
    return $build;
  }

  /**
   * Helper method to reload island with global data.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithGlobalData(string $builder_id): array {
    if (!$this->builder) {
      // @todo pass \Drupal\display_builder\InstanceInterface object in
      // parameters instead of loading again.
      /** @var \Drupal\display_builder\InstanceInterface $builder */
      $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
      $this->builder = $builder;
    }
    $data = $this->builder->getCurrentState();

    return $this->addOutOfBand(
      $this->build($this->builder, $data),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

  /**
   * Helper method to reload island with provided local data.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $data
   *   The local data array to use for building the island.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithLocalData(string $builder_id, array $data): array {
    if (!$this->builder) {
      // @todo pass \Drupal\display_builder\InstanceInterface object in
      // parameters instead of loading again.
      /** @var \Drupal\display_builder\InstanceInterface $builder */
      $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
      $this->builder = $builder;
    }

    return $this->addOutOfBand(
      $this->build($this->builder, $data),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

  /**
   * Helper method to reload island with instance-specific data.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithInstanceData(string $builder_id, string $instance_id): array {
    if (!$this->builder) {
      // @todo pass \Drupal\display_builder\InstanceInterface object in
      // parameters instead of loading again.
      /** @var \Drupal\display_builder\InstanceInterface $builder */
      $builder = $this->entityTypeManager->getStorage('display_builder_instance')->load($builder_id);
      $this->builder = $builder;
    }
    $data = $this->builder->get($instance_id);

    return $this->addOutOfBand(
      $this->build($this->builder, $data),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

  /**
   * Returns the form builder service.
   *
   * @return \Drupal\Core\Form\FormBuilderInterface
   *   The form builder service.
   */
  protected function formBuilder() {
    if (!$this->formBuilder) {
      $this->formBuilder = \Drupal::formBuilder();
    }

    return $this->formBuilder;
  }

  /**
   * Get args passed to plugin.
   *
   * @return array
   *   Array of arguments.
   */
  private function getArgs(): array {
    return [
      'island_id' => $this->getPluginId(),
      'builder_id' => $this->builderId,
      'instance_id' => $this->instanceId,
      'instance' => $this->data,
    ];
  }

}
