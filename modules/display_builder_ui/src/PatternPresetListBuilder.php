<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Pattern presets.
 */
final class PatternPresetListBuilder extends DraggableListBuilder {

  /**
   * The theme extension list.
   */
  protected ThemeExtensionList $themeExtensionList;

  /**
   * Constructs a new PatternPresetListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension list.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigEntityStorageInterface $storage, ThemeExtensionList $theme_extension_list) {
    parent::__construct($entity_type, $storage);
    $this->themeExtensionList = $theme_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage */
    $storage = $container->get('entity_type.manager')->getStorage($entity_type->id());

    return new self(
      $entity_type,
      $storage,
      $container->get('extension.list.theme'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pattern_preset_list_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['group'] = $this->t('Group');
    $header['description'] = $this->t('Description');
    $header['contexts'] = $this->t('Contexts');
    $header['theme'] = $this->t('Theme');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\display_builder\Entity\PatternPresetInterface $entity */
    $row = [];
    $row['label'] = $entity->label();

    $row['group']['data']['#plain_text'] = $entity->getGroup() ?? $this->t('Others');
    $row['description']['data']['#plain_text'] = $entity->get('description') ?: $entity->getSummary();

    if ($entity->getContextDefinitions()) {
      $row['contexts']['data']['#plain_text'] = self::prettyPrintContexts($entity->getContextDefinitions());
    }
    else {
      $row['contexts']['data']['#plain_text'] = '';
    }
    $dependencies = $entity->getDependencies();

    if (empty($dependencies['theme'] ?? [])) {
      $row['themes']['data']['#plain_text'] = $this->t('Any');
    }
    else {
      $row['theme']['data']['#plain_text'] = \implode(', ', $this->getThemeName($dependencies['theme']));
    }

    return $row + parent::buildRow($entity);
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
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['notice'] = [
      '#markup' => $this->t('Pattern presets are reusable arrangements of components and blocks.'),
      '#prefix' => '<div class="description">',
      '#suffix' => '</div>',
      '#weight' => -100,
    ];

    return $build;
  }

  /**
   * Pretty print contexts.
   *
   * @param array<string, \Drupal\Core\Plugin\Context\ContextDefinitionInterface> $contexts
   *   The contexts to pretty print.
   *
   * @return string
   *   A nicely printed list of contexts.
   */
  private static function prettyPrintContexts(array $contexts): string {
    $list = [];

    foreach ($contexts as $context => $definition) {
      if ($context === 'context_requirements') {
        foreach ($definition->getConstraint('RequiredArrayValues') as $constraint) {
          $constraint = \str_replace('views:', '', $constraint);
          $list[] = \ucwords($constraint);
        }

        continue;
      }
      $context = \str_replace('ui_patterns_views:view_entity', 'View', $context);
      $list[] = \ucwords($context);
    }

    return \implode(', ', $list);
  }

  /**
   * Get preset's themes names.
   *
   * @param array $themes
   *   The list of theme names.
   *
   * @return array
   *   A list of themes extension names.
   */
  private function getThemeName(array $themes): array {
    $names = [];

    foreach ($themes as $theme) {
      $names[] = $this->themeExtensionList->getName($theme);
    }

    return $names;
  }

}
