<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base class for island plugins.
 */
abstract class IslandPluginBase extends PluginBase implements IslandInterface {

  use RenderableBuilderTrait;
  use HtmxTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $sdcManager,
    protected HtmxEvents $htmxEvents,
    protected StateManagerInterface $stateManager,
    protected EventSubscriberInterface $eventSubscriber,
    protected SourcePluginManager $sourceManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('display_builder.state_manager'),
      $container->get('display_builder.event_subscriber'),
      $container->get('plugin.manager.ui_patterns_source'),
    );
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
  public function getTypeId(): string {
    return $this->pluginDefinition['type']->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlId(string $builder_id): string {
    return implode('-', ['island', $builder_id, $this->pluginDefinition['id']]);
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyboardShortcuts(): array {
    return $this->pluginDefinition['keyboard_shortcuts'] ?? [];
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
  abstract public function build(string $builder_id, array $data, array $options = []): array;

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
   * Helper method to reload island with global data.
   *
   * @param string $builder_id
   *   The builder ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function reloadWithGlobalData(string $builder_id): array {
    $data = $this->stateManager->getCurrentState($builder_id);

    return $this->addOutOfBand(
      $this->build($builder_id, $data),
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
    return $this->addOutOfBand(
      $this->build($builder_id, $data),
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
    $data = $this->stateManager->get($builder_id, $instance_id);

    return $this->addOutOfBand(
      $this->build($builder_id, $data),
      '#' . $this->getHtmlId($builder_id),
      'innerHTML'
    );
  }

  /**
   * Helper method to replace a specific instance in the DOM.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param string $instance_id
   *   The instance ID.
   *
   * @return array
   *   Returns a render array with out-of-band commands.
   */
  protected function replaceInstance(string $builder_id, string $instance_id): array {
    $parent_selector = '#' . $this->getHtmlId($builder_id) . ' [data-instance-id="' . $instance_id . '"]';
    $data = $this->stateManager->get($builder_id, $instance_id);

    $build = [];

    if (isset($data['source_id']) && $data['source_id'] === 'component') {
      $build = $this->buildSingleComponent($builder_id, $instance_id, $data);
    }
    else {
      $build = $this->buildSingleBlock($builder_id, $instance_id, $data);
    }

    return $this->makeOutOfBand(
      $build,
      $parent_selector,
      'outerHTML'
    );
  }

  /**
   * Build renderable from state data.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID.
   * @param array $data
   *   The UI Patterns 2 form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  protected function buildSingleComponent(string $builder_id, string $instance_id, array $data, int $index = 0): ?array {
    return [];
  }

  /**
   * Build renderable from state data.
   *
   * @param string $builder_id
   *   Builder ID.
   * @param string $instance_id
   *   Instance ID.
   * @param array $data
   *   The UI Patterns 2 form state data.
   * @param int $index
   *   (Optional) The index of the block. Default to 0.
   *
   * @return array|null
   *   A renderable array.
   */
  protected function buildSingleBlock(string $builder_id, string $instance_id, array $data, int $index = 0): ?array {
    return [];
  }

}
