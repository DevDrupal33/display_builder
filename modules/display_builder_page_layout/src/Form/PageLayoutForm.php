<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Form;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_page_layout\StartingPointType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Page layout form.
 */
final class PageLayoutForm extends EntityForm {

  use AutowireTrait;

  /**
   * The ID of the default page layout.
   *
   * The site holds at most one, so it needs no name to tell it apart from
   * anything, and both its ID and label are set for the user.
   */
  public const DEFAULT_ID = 'default';

  /**
   * The label of the default page layout.
   */
  public const DEFAULT_LABEL = 'Default';

  /**
   * Whether the layout being created or edited is the default one.
   */
  protected bool $isDefaultLayout;

  public function __construct(
    protected ContextRepositoryInterface $contextRepository,
    #[Autowire(service: 'plugin.manager.condition')]
    protected ExecutableManagerInterface $conditionManager,
    protected LanguageManagerInterface $languageManager,
    #[Autowire(service: 'plugin.manager.display_buildable')]
    protected DisplayBuildablePluginManager $displayBuildableManager,
    #[Autowire(service: 'extension.list.theme')]
    protected ThemeExtensionList $themeList,
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

    $is_default = $this->isDefaultLayout();

    if ($is_default) {
      // A name tells one thing apart from another, and the site holds a single
      // default layout, so there is nothing to tell it apart from.
      $form['label'] = [
        '#type' => 'value',
        '#value' => $entity->isNew() ? self::DEFAULT_LABEL : $entity->label(),
      ];
      $form['id'] = [
        '#type' => 'value',
        '#value' => $entity->isNew() ? self::DEFAULT_ID : $entity->id(),
      ];
    }
    else {
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
    }

    // A layout is seeded only once. The duplicate form also builds a new
    // entity, but it already carries the sources it was duplicated from.
    if ($entity->isNew() && empty($entity->getSources())) {
      // The theme import belongs on the layout that catches every page, never
      // on a conditional one, which would carry a second copy of the whole
      // site block layout and reintroduce the theme page shell.
      $form['starting_point'] = $this->buildStartingPointForm($is_default);
    }

    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $form = \array_merge($form, $buildable->buildInstanceForm());

    // The default layout is the one with no condition: giving it any would
    // stop it catching the pages no other layout matches.
    if ($is_default) {
      $form['default_notice'] = [
        '#type' => 'item',
        '#title' => $this->t('Default page layout'),
        '#markup' => $this->t('This layout has no condition, so it applies to every page no other layout matches.<br>It is checked last, which leaves conditional layouts free to take over the pages they target.'),
        '#weight' => -100,
      ];
    }
    else {
      $form['conditions'] = $this->buildConditionsForm([], $form_state);
    }

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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if ($this->isDefaultLayout()) {
      // The ID is set for the user, so nothing else is left to catch a layout
      // that already took it. Only a hand written ID can get here.
      if ($this->entity->isNew() && PageLayout::load(self::DEFAULT_ID) !== NULL) {
        $form_state->setErrorByName('id', $this->t('The page layout %id already exists. Rename it from the page layouts list, then create the default page layout again.', ['%id' => self::DEFAULT_ID]));
      }

      return;
    }

    // The conditions are gathered here rather than on submit so a layout that
    // configures none can be refused before it is saved. Left unconditional it
    // would become a second default and shadow every layout below it.
    $conditions = $this->gatherConditions($form, $form_state);
    $form_state->setTemporaryValue('page_layout_conditions', $conditions);

    if (!$this->hasConfiguredCondition($conditions)) {
      $form_state->setErrorByName('conditions', $this->t('Configure at least one condition. A page layout with none applies to every page, which is what the default page layout is for.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // The starting point is not a property of the entity, it only decides the
    // sources the entity is created with. The radios only ever hand back one
    // of their own option keys, so anything else is a tampered post.
    $starting_point = StartingPointType::tryFrom((string) $form_state->getValue('starting_point'));
    $form_state->unsetValue('starting_point');

    parent::submitForm($form, $form_state);
    $this->submitConditions($form_state);

    if ($starting_point === NULL) {
      return;
    }

    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = $this->entity;
    /** @var \Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout $buildable */
    $buildable = $this->displayBuildableManager->createInstance('page_layout', ['entity' => $entity]);
    $entity->setSources($buildable->getInitialSources($starting_point));
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

    if ($page_layout->isNew() && !$buildable->isAllowed()) {
      $form['submit']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * Builds the starting point selection.
   *
   * The help text lives here, on the creation form, because this is the one
   * moment the user is thinking about precisely this question.
   *
   * @param bool $import_available
   *   Whether the Block Layout import is offered.
   *
   * @return array
   *   A renderable form array.
   */
  private function buildStartingPointForm(bool $import_available): array {
    $element = [
      '#type' => 'radios',
      '#title' => $this->t('Starting point'),
      '#options' => [],
      // The import is only offered on the layout that catches every page,
      // which is the one place continuity with the current site is wanted.
      '#default_value' => $import_available ? StartingPointType::Theme->value : StartingPointType::Minimal->value,
      '#required' => TRUE,
    ];

    if ($import_available) {
      $arguments = ['@theme' => $this->getThemeLabel()];
      $element['#options'][StartingPointType::Theme->value] = $this->t('Start from your current site');
      $element[StartingPointType::Theme->value]['#description'] = $this->t('Copies the blocks currently placed in @theme into their matching regions, still rendered by the @theme page template, so your pages should look the same as they do now. This is a one-time copy: later changes in Block Layout will not show up here. Replace the contents of each region with components as you go, then remove the theme page shell to take full control of the page.', $arguments);
    }
    else {
      $element['#description'] = $this->t('To start from a layout you already built, duplicate it from the page layouts list instead.');
    }

    $element['#options'][StartingPointType::Minimal->value] = $this->t('Minimal Drupal page');
    $element[StartingPointType::Minimal->value]['#description'] = $this->t('Places only what a Drupal page needs to keep working: page title, status messages, help, tabs, primary actions, breadcrumbs and the main content. The page template of your theme is not used, so the page is unstyled until you add layout components.');

    $element['#options'][StartingPointType::Blank->value] = $this->t('Blank');
    $element[StartingPointType::Blank->value]['#description'] = $this->t('Nothing at all. For building the page entirely from components.');

    return $element;
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
   * Gets the human readable name of the front-end theme.
   *
   * Not the active one: this form runs in the admin theme, and the layout is
   * built for the theme the site is seen through.
   *
   * @return string
   *   The theme name.
   */
  private function getThemeLabel(): string {
    $theme_name = (string) $this->config('system.theme')->get('default');

    return $this->themeList->getName($theme_name);
  }

  /**
   * Is this form building or editing the default page layout?
   *
   * For a new layout the answer is the route: nothing on a blank entity can
   * tell the two apart, since both start with no condition. For an existing
   * one it is the stored entity, loaded unchanged because #after_build has
   * already overwritten the in-memory conditions with raw form values.
   *
   * @return bool
   *   TRUE when the layout must carry no condition.
   */
  private function isDefaultLayout(): bool {
    if (isset($this->isDefaultLayout)) {
      return $this->isDefaultLayout;
    }

    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = $this->entity;

    if ($entity->isNew()) {
      $this->isDefaultLayout = (bool) $this->getRouteMatch()->getRouteObject()?->getDefault('_page_layout_default');

      return $this->isDefaultLayout;
    }

    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface|null $stored */
    $stored = $this->entityTypeManager->getStorage('page_layout')->loadUnchanged((string) $entity->id());
    $this->isDefaultLayout = $stored?->isDefault() ?? FALSE;

    return $this->isDefaultLayout;
  }

  /**
   * Runs the conditions submit handlers and collects their configuration.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The configuration of every condition plugin, keyed by condition ID.
   */
  private function gatherConditions(array $form, FormStateInterface $form_state): array {
    $configuration = [];

    foreach (\array_keys($form_state->getValue('conditions', [])) as $condition_id) {
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $form_state->get(['conditions', $condition_id]);
      $condition->submitConfigurationForm($form['conditions'][$condition_id], SubformState::createForSubform($form['conditions'][$condition_id], $form, $form_state));
      $configuration[$condition_id] = $condition->getConfiguration();
    }

    return $configuration;
  }

  /**
   * Is any condition configured rather than left at its defaults?
   *
   * The question is asked of a throwaway collection rather than answered here,
   * because dropping the conditions that match their defaults is exactly what
   * the collection does on save. The form then refuses what would have been
   * stored as empty, and the two can never disagree.
   *
   * @param array $configuration
   *   The configuration of every condition plugin, keyed by condition ID.
   *
   * @return bool
   *   TRUE when at least one condition carries a non default configuration.
   *
   * @see \Drupal\Core\Condition\ConditionPluginCollection::getConfiguration()
   */
  private function hasConfiguredCondition(array $configuration): bool {
    $collection = new ConditionPluginCollection($this->conditionManager, $configuration);

    return $collection->getConfiguration() !== [];
  }

  /**
   * Helper function to independently submit the conditions UI.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  private function submitConditions(FormStateInterface $form_state): void {
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = $this->entity;

    foreach ($form_state->getTemporaryValue('page_layout_conditions') ?? [] as $condition_id => $condition_configuration) {
      $entity->getConditions()->addInstanceId((string) $condition_id, $condition_configuration);
    }
  }

}
