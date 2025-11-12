<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\IslandPluginManagerInterface;
use Drupal\display_builder\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Display Builder profiles.
 */
final class ProfileListBuilder extends DraggableListBuilder {

  /**
   * The island plugin manager.
   */
  protected IslandPluginManagerInterface $islandManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, IslandPluginManagerInterface $island_manager) {
    parent::__construct($entity_type, $storage);
    $this->islandManager = $island_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('plugin.manager.db_island'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_list_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['description'] = $this->t('Description');
    $header['roles'] = $this->t('Roles');
    $header['status'] = $this->t('Status');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row = [];
    /** @var \Drupal\display_builder\ProfileInterface $entity */
    $row['label'] = $entity->label();
    // List enabled view panels instead of showing an empty description.
    $description = $entity->get('description') ?: $this->listViewPanels($entity);
    $row['description']['data']['#plain_text'] = $description;
    $row['roles']['data'] = [
      '#theme' => 'item_list',
      '#items' => $entity->getRoles(),
      '#empty' => $this->t('No roles may use this display builder'),
      '#context' => ['list_style' => 'comma-list'],
    ];
    $row['status']['data']['#plain_text'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['notice'] = [
      '#markup' => $this->t('Profiles define the display building experience (available features, layout arrangement) for specific user roles.'),
      '#weight' => -100,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $rows = Element::children($form['entities']);

    if (\count($rows) < 2) {
      unset($form['actions']['submit']);
    }

    return $form;
  }

  /**
   * List enabled view panels as a description fallback.
   *
   * @param \Drupal\display_builder\ProfileInterface $entity
   *   The entity.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The description listing the panels.
   */
  protected function listViewPanels(ProfileInterface $entity): TranslatableMarkup {
    $view_panels = $this->islandManager->getIslandsByTypes()['view'];
    $view_panels = \array_intersect_key($view_panels, $entity->getEnabledIslands());
    $view_panels = \array_map(static fn ($island) => $island->label(), $view_panels);

    return $this->t('With: @panels', ['@panels' => \implode(', ', $view_panels)]);
  }

}
