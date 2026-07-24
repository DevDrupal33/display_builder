<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Display builder plugin form.
 */
final class ProfileIslandPluginForm extends EntityForm {

  use AutowireTrait;

  /**
   * The route parameter for the island plugin.
   */
  protected string $islandId;

  /**
   * The island plugin.
   */
  protected ?IslandInterface $island = NULL;

  public function __construct(
    protected IslandPluginManagerInterface $islandPluginManager,
    protected CachedDiscoveryClearerInterface $pluginCacheClearer,
  ) {}

  /**
   * Returns the title of the edit plugin form.
   *
   * Used as a static route title callback by the routing system, which
   * does not instantiate the form class. \Drupal::service() is the
   * correct pattern here.
   *
   * @param string $island_id
   *   The island ID.
   *
   * @return string
   *   The title of the edit plugin form.
   */
  public static function editFormTitle(string $island_id): string {
    // phpcs:ignore Drupal.Classes.FullyQualifiedNamespace -- static route callback, DI unavailable.
    /** @var \Drupal\display_builder\Island\IslandPluginManagerInterface $manager */
    $manager = \Drupal::service('plugin.manager.db_island');
    $island = $manager->createInstance($island_id, []);

    return (string) $island->label();
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
    $this->pluginCacheClearer->clearCachedDefinitions();

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
    \assert($entity instanceof Profile);
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
    /** @var \Drupal\display_builder\Entity\Profile $entity */
    $entity = $this->entity;
    $island_configuration = $entity->getIslandConfiguration($this->islandId);
    $this->island = $this->islandPluginManager->createInstance($this->islandId, $island_configuration);

    return $this->island;
  }

}
