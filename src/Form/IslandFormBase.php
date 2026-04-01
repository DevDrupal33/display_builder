<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\Island\IslandWithFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display builder form for island plugin.
 */
final class IslandFormBase extends FormBase {

  /**
   * Constructs a new IslandFormBase.
   *
   * @param \Drupal\display_builder\Island\IslandPluginManagerInterface $islandManager
   *   The island plugin manager.
   */
  public function __construct(
    private readonly IslandPluginManagerInterface $islandManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('plugin.manager.db_island'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_island';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $island_args = $form_state->getBuildInfo()['args'][0];
    $plugin = $this->getPlugin($island_args);

    if (!$plugin instanceof IslandWithFormInterface) {
      return $form;
    }
    $plugin->setBuilderId($island_args['builder_id']);
    $plugin->buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $island_args = $form_state->getBuildInfo()['args'][0];
    $plugin = $this->getPlugin($island_args);

    if (!$plugin instanceof IslandWithFormInterface) {
      return;
    }
    $plugin->validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $island_args = $form_state->getBuildInfo()['args'][0];
    $plugin = $this->getPlugin($island_args);

    if (!$plugin instanceof IslandWithFormInterface) {
      return;
    }
    $plugin->submitForm($form, $form_state);
  }

  /**
   * Gets an island plugin instance from form args.
   *
   * @param array $args
   *   Arguments from form_state which allow to load plugin.
   *
   * @return \Drupal\display_builder\Island\IslandInterface
   *   The Island plugin.
   */
  protected function getPlugin(array $args): IslandInterface {
    return $this->islandManager->createInstance($args['island_id'], $args['instance']);
  }

}
