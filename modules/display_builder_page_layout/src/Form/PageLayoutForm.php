<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Page layout form.
 */
final class PageLayoutForm extends EntityForm {

  use AutowireTrait;

  public function __construct(
    private readonly ConfigFormBuilderInterface $configFormBuilder,
    private readonly ContextRepositoryInterface $contextRepository,
    #[Autowire(service: 'plugin.manager.condition')]
    private readonly ExecutableManagerInterface $conditionManager,
    private readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'plugin.manager.display_buildable')]
    private readonly DisplayBuildablePluginManager $displayBuildableManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = $this->entity;

    // Store the gathered contexts in the form state for other objects to use
    // during form building.
    $form_state->setTemporaryValue('gathered_contexts', $this->contextRepository->getAvailableContexts());

    // Because of $form['conditions'].
    $form['#tree'] = TRUE;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [PageLayout::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $form = \array_merge($form, $this->configFormBuilder->build($buildable));

    $form['conditions'] = $this->buildConditionsForm([], $form_state);
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        SAVED_NEW => $this->t('Created new page layout %label.', $message_args),
        default => $this->t('Updated page layout %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $this->submitConditions($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state): array {
    $form = parent::actionsElement($form, $form_state);
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $page_layout */
    $page_layout = $this->entity;

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $page_layout]);

    if ($page_layout->isNew() && !$this->configFormBuilder->isAllowed($buildable)) {
      $form['submit']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * Helper function for building the conditions UI form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form array with the conditions UI added in.
   */
  private function buildConditionsForm(array $form, FormStateInterface $form_state) {
    $form['visibility_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Conditions'),
      '#parents' => ['visibility_tabs'],
    ];

    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = $this->entity;
    // @todo \PluginNotFoundException:
    $conditions = $entity->getConditions()->getConfiguration();

    // Important to filter with contexts to have ContextAware working.
    $definitions = $this->conditionManager->getFilteredDefinitions('page_layout', $form_state->getTemporaryValue('gathered_contexts'), ['page_layout' => $entity]);

    foreach ($definitions as $condition_id => $definition) {
      // Don't display the current theme condition.
      if ($condition_id === 'current_theme') {
        continue;
      }

      // Don't display the language condition until we have multiple languages.
      if ($condition_id === 'language' && !$this->languageManager->isMultilingual()) {
        continue;
      }

      if (\str_starts_with($condition_id, 'entity_bundle:')) {
        $entity_type_id = \str_replace('entity_bundle:', '', $condition_id);
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        $url = $entity_type->getLinkTemplate('canonical');

        if (!$url || \str_starts_with((string) $url, '/admin')) {
          continue;
        }
      }

      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($condition_id, $conditions[$condition_id] ?? []);
      $form_state->set(['conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = $definition['label'];
      $condition_form['#group'] = 'visibility_tabs';
      $form[$condition_id] = $condition_form;
    }

    return $this->alterConditionsForm($form);
  }

  /**
   * Alter conditions form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   */
  private function alterConditionsForm(array $form): array {
    if (isset($form['user_role'])) {
      $form['user_role']['#title'] = $this->t('User roles');
    }

    if (isset($form['request_path'])) {
      $form['request_path']['#title'] = $this->t('Pages');
      $form['request_path']['negate']['#type'] = 'radios';
      $form['request_path']['negate']['#default_value'] = (int) $form['request_path']['negate']['#default_value'];
      $form['request_path']['negate']['#title_display'] = 'invisible';
      $form['request_path']['negate']['#options'] = [
        $this->t('Activate on the listed pages'),
        $this->t('Skip for the listed pages'),
      ];
    }

    return $form;
  }

  /**
   * Helper function to independently submit the conditions UI.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function submitConditions(array $form, FormStateInterface $form_state): void {
    foreach (\array_keys($form_state->getValue('conditions')) as $condition_id) {
      // Allow the condition to submit the form.
      $condition = $form_state->get(['conditions', $condition_id]);
      $condition->submitConfigurationForm($form['conditions'][$condition_id], SubformState::createForSubform($form['conditions'][$condition_id], $form, $form_state));

      $condition_configuration = $condition->getConfiguration();
      // Update the visibility conditions on the block.
      /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
      $entity = $this->entity;
      $entity->getConditions()->addInstanceId((string) $condition_id, $condition_configuration);
    }
  }

}
