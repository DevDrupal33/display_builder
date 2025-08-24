<?php

declare(strict_types=1);

namespace Drupal\display_builder_devel\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\WithDisplayBuilderInterface;
use Drupal\display_builder_devel\FixturesHelpers;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;

/**
 * Defines an add display builder instance form.
 */
final class ImportForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    private readonly StateManagerInterface $stateManager,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL): array {
    $options = FixturesHelpers::getAllFixturesOptions();

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Import type'),
      '#options' => ['fixture' => $this->t('Fixture'), 'input' => $this->t('Input')],
      '#default_value' => 'fixture',
      '#required' => TRUE,
    ];

    $form['fixture_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Replace data'),
      '#description' => $this->t('Enter the fixture to replace in this display builder instance.'),
      '#options' => $options,
      '#default_value' => 'blank',
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'fixture'],
        ],
      ],
    ];

    $form['builder_id'] = [
      '#type' => 'hidden',
      '#value' => $builder_id,
    ];

    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Import data'),
      '#description' => $this->t('Paste Yaml formatted data to import.'),
      '#states' => [
        'visible' => [
          ':input[name="type"]' => ['value' => 'input'],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => new Url('display_builder_devel.collection'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_devel_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $import_type = $form_state->getValue('type', 'fixture');

    $builder_id = $form_state->getValue('builder_id');

    if ($import_type === 'fixture') {
      $fixture_id = $form_state->getValue('fixture_id', 'blank');
      $builder_data = FixturesHelpers::getAllFixturesData($fixture_id);
    }
    else {
      $builder_data = Yaml::decode($form_state->getValue('data'));
    }

    $current = $this->stateManager->load($builder_id);
    if (!$current) {
      $this->messenger()->addError($this->t('The display builder instance %id does not exist.', ['%id' => $builder_id]));
      return;
    }

    $contexts = $this->stateManager->getContexts($builder_id);
    $this->stateManager->delete($builder_id);

    $this->stateManager->create(
      $builder_id,
      $current['entity_config_id'],
      $builder_data,
      $contexts,
    );

    // Save is different for each target: entity, page or views.
    // We copy the DisplayBuilderEvents::ON_SAVE.
    if ($this->stateManager->hasSaveContextsRequirement($builder_id, EntityViewDisplay::getContextRequirement(), $contexts)) {
      $display = $this->getEntityViewDisplayEntity($contexts['entity'], $contexts['view_mode']);
      $display?->saveSources();
    }

    // @see Drupal\display_builder_page_layout\Entity\PageLayout::getInstanceId()
    $prefix = 'page_layout__';

    if (\str_starts_with($builder_id, $prefix) && $this->stateManager->hasSaveContextsRequirement($builder_id, PageLayout::getContextRequirement(), $contexts)) {
      $page_layout_id = \substr($builder_id, \strlen($prefix));
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
      $page_layout = $this->entityTypeManager->getStorage('page_layout')->load($page_layout_id);
      $page_layout->saveSources();
    }

    if ($this->stateManager->hasSaveContextsRequirement($builder_id, DisplayExtender::getContextRequirement(), $contexts)) {
      /** @var \Drupal\views\Entity\View $view */
      $view = $contexts['ui_patterns_views:view_entity']->getContextValue() ?? NULL;

      if (!$view) {
        return;
      }
      $display_id = \explode('__', $builder_id)[2];
      $view->getExecutable()->setDisplay($display_id);
      $extenders = $view->getExecutable()->getDisplay()->getExtenders();

      if (!isset($extenders['display_builder'])) {
        return;
      }
      /** @var \Drupal\display_builder\WithDisplayBuilderInterface $extender */
      $extender = $extenders['display_builder'];
      $extender->saveSources();
    }

    // phpcs:ignore
    \Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();
  }

  /**
   * Get entity view display entity.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface $entity_context
   *   The entity context.
   * @param \Drupal\Core\Plugin\Context\ContextInterface $view_mode_context
   *   The view mode context.
   *
   * @return \Drupal\display_builder\WithDisplayBuilderInterface|null
   *   The entity view display entity or NULL if not found.
   */
  private function getEntityViewDisplayEntity(ContextInterface $entity_context, ContextInterface $view_mode_context): ?WithDisplayBuilderInterface {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $entity_context->getContextValue();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $view_mode = $view_mode_context->getContextValue();
    $display_id = "{$entity_type_id}.{$bundle}.{$view_mode}";

    /** @var \Drupal\display_builder\WithDisplayBuilderInterface|null $display */
    $display = $this->entityTypeManager->getStorage('entity_view_display')
      ->load($display_id);

    return $display;
  }

}
