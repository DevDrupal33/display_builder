<?php

declare(strict_types=1);

namespace Drupal\display_builder\Island;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\DisplayBuilderHtmx;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\ui_patterns\SourcePluginManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for island plugins.
 */
abstract class IslandPluginBase extends PluginBase implements IslandInterface {

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
   * The tree node id (when the island is executed in the context of a node).
   */
  protected ?string $nodeId = NULL;

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
    protected LoggerInterface $logger,
    protected FormBuilderInterface $formBuilder,
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
      $container->get('logger.factory')->get('display_builder'),
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $this->builder = $builder;

    $builder_id = (string) $builder->id();
    $this->builderId = $builder_id;
    $this->nodeId = $data['node_id'] ?? NULL;

    // First, get specific data for the plugin.
    if (isset($data['third_party_settings'][$this->getPluginId()])) {
      $this->data = $data['third_party_settings'][$this->getPluginId()];
    }
    // Otherwise, fallback on global data.
    else {
      $this->data = $data;
    }

    if (!$this->isApplicable()) {
      return [];
    }

    if (!$this instanceof IslandWithFormInterface) {
      return $this->buildContent($builder, $data, $options);
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

    $build = $this->formBuilder->buildForm($this::getFormClass(), $form_state);

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

    return $this->htmxEvents->onThirdPartyFormChange($element, $this->builderId, $this->nodeId, $island_id);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(): bool {
    $definition = $this->getPluginDefinition();

    return $this->nodeId !== NULL && \is_array($definition) && !empty($this->data);
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
  public function setBuilderId(string $builder_id): void {
    $this->builderId = $builder_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setNodeId(?string $node_id): void {
    $this->nodeId = $node_id;
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
  public function getRegionId(): ?string {
    $definition = (array) $this->getPluginDefinition();

    return $definition['region'] ?? NULL;
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
  public function onAttachToRoot(InstanceInterface $instance, string $node_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(InstanceInterface $instance, string $node_id, string $parent_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onMove(InstanceInterface $instance, string $node_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(InstanceInterface $instance, array $data): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onUpdate(InstanceInterface $instance, string $node_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(InstanceInterface $instance, ?string $parent_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onHistoryChange(InstanceInterface $instance): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onRestore(InstanceInterface $instance): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onRevert(InstanceInterface $instance): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onPublish(InstanceInterface $instance): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function onPresetSave(InstanceInterface $instance): array {
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
   * {@inheritdoc}
   */
  public function isDeferrable(): bool {
    // View panels share a tab strip or a sidebar drawer, the Preview pane is
    // hidden until the preview toggle reveals it beside the editor, and
    // Floating controls ride along with the panel they attach to - so all of
    // them spend most of their time off screen. Everything else is permanently
    // visible.
    return \in_array($this->getTypeId(), [
      IslandType::View->value,
      IslandType::Preview->value,
      IslandType::Floating->value,
    ], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function reload(InstanceInterface $instance): array {
    return $this->reloadWithGlobalData($instance);
  }

  /**
   * Build content for non-form islands.
   *
   * Override this method instead of build() when the plugin does not implement
   * IslandWithFormInterface but still wants to reuse the preamble logic from
   * build() (setting builder context, checking isApplicable()).
   *
   * @param \Drupal\display_builder\InstanceInterface $builder
   *   Display builder instance.
   * @param array $data
   *   The data array.
   * @param array $options
   *   Additional options.
   *
   * @return array
   *   A renderable array.
   */
  protected function buildContent(InstanceInterface $builder, array $data, array $options): array {
    return [];
  }

  /**
   * Helper method to reload island with global data.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithGlobalData(InstanceInterface $instance): array {
    return DisplayBuilderHtmx::outOfBand(
      $this->build($instance, $instance->getSources()),
      '#' . $this->getHtmlId((string) $instance->id()),
      'innerHTML'
    );
  }

  /**
   * Helper method to reload island with provided local data.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param array $data
   *   The local data array to use for building the island.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithLocalData(InstanceInterface $instance, array $data): array {
    return DisplayBuilderHtmx::outOfBand(
      $this->build($instance, $data),
      '#' . $this->getHtmlId((string) $instance->id()),
      'innerHTML'
    );
  }

  /**
   * Helper method to reload island with node-specific data.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   The display builder instance.
   * @param string $node_id
   *   The tree node ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithNodeData(InstanceInterface $instance, string $node_id): array {
    return DisplayBuilderHtmx::outOfBand(
      $this->build($instance, $instance->getNode($node_id)),
      '#' . $this->getHtmlId($node_id),
      'innerHTML'
    );
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
      'instance_id' => $this->nodeId,
      'instance' => $this->data,
    ];
  }

}
