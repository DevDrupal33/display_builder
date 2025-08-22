<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\display_builder\IslandInterface;
use Drupal\display_builder\IslandPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Display builder plugin form.
 */
final class DisplayBuilderIslandPluginForm extends EntityForm {

  /**
   * The route parameter for the island plugin.
   */
  protected string $islandId;

  /**
   * The island plugin.
   */
  private ?IslandInterface $island = NULL;

  public function __construct(
    protected IslandPluginManagerInterface $islandPluginManager,
  ) {}

  /**
   * Returns the title of the edit plugin form.
   *
   * @param string $island_id
   *   The island ID.
   *
   * @return string
   *   The title of the edit plugin form.
   */
  public static function editFormTitle(string $island_id): string {
    /** @var \Drupal\display_builder\IslandInterface $island */
    $island = \Drupal::service('plugin.manager.db_island')->createInstance($island_id, []);

    return $island->label();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DisplayBuilderIslandPluginForm {
    return new self(
      $container->get('plugin.manager.db_island')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id): EntityInterface {
    $this->islandId = $route_match->getParameter('island_id');

    return parent::getEntityFromRouteMatch($route_match, $entity_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $island = $this->getIslandPlugin();

    if ($island instanceof PluginFormInterface) {
      $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
      $island->validateConfigurationForm($form['configuration'], $subform_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    $island = $this->getIslandPlugin();

    if (!($island instanceof PluginFormInterface)) {
      throw new AccessDeniedHttpException('The island plugin does not support configuration.');
    }
    $form['configuration'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
    $form['configuration'] = $island->buildConfigurationForm($form['configuration'], $subform_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    // Clear the plugin cache so changes are applied on front theme builder.
    /** @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface $pluginCacheClearer */
    $pluginCacheClearer = \Drupal::service('plugin.cache_clearer'); // phpcs:ignore
    $pluginCacheClearer->clearCachedDefinitions();

    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        SAVED_NEW => $this->t('Created new display builder config %label.', $message_args),
        SAVED_UPDATED => $this->t('Updated display builder config %label.', $message_args),
        default => '',
      }
    );

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    unset($actions['delete']);

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    \assert($entity instanceof DisplayBuilder);
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    $island = $this->getIslandPlugin();

    if ($island instanceof PluginFormInterface) {
      $subform_state = SubformState::createForSubform($form['configuration'], $form, $form_state);
      $island->submitConfigurationForm($form['configuration'], $subform_state);
      $entity->setIslandConfiguration($this->islandId, $island->getConfiguration());
    }
  }

  /**
   * Retrieves the configured island plugin.
   */
  private function getIslandPlugin(): IslandInterface {
    if ($this->island !== NULL) {
      return $this->island;
    }
    /** @var \Drupal\display_builder\Entity\DisplayBuilder $entity */
    $entity = $this->entity;
    $island_configuration = $entity->getIslandConfiguration($this->islandId);
    $island = $this->islandPluginManager->createInstance($this->islandId, $island_configuration);
    \assert($island instanceof IslandInterface);
    $this->island = $island;

    return $this->island;
  }

}
