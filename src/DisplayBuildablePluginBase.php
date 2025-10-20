<?php

declare(strict_types=1);

namespace Drupal\display_builder;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for display_buildable plugins.
 */
abstract class DisplayBuildablePluginBase extends PluginBase implements ContainerFactoryPluginInterface, DisplayBuildableInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The loaded display builder instance.
   */
  protected ?InstanceInterface $instance;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->instance = NULL;

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    $definition = $this->pluginDefinition;

    return (string) ($definition instanceof PluginDefinitionInterface ? $definition->id() : ($definition['label'] ?? ''));
  }

  /**
   * {@inheritdoc}
   */
  public function initInstanceIfMissing(): void {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->getInstance();

    if (!$instance) {
      /** @var \Drupal\display_builder\InstanceStorage $storage */
      $storage = $this->entityTypeManager->getStorage('display_builder_instance');
      $instance = $storage->createFromImplementation($this);
      $instance->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(): ?InstanceInterface {
    if (isset($this->instance)) {
      return $this->instance;
    }

    if ($this->getInstanceId() === NULL) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('display_builder_instance');
    /** @var \Drupal\display_builder\InstanceInterface|null $instance */
    $instance = $storage->load($this->getInstanceId());
    $this->instance = $instance;

    return $this->instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    // Plugins will override this method.
    return NULL;
  }

}
