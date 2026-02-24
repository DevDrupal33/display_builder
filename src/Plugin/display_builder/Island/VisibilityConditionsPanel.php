<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Condition\ConditionPluginCollection;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\IslandWithFormInterface;
use Drupal\display_builder\IslandWithFormTrait;
use Drupal\display_builder\RenderableAltererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Visibility conditions island plugin implementation.
 */
#[Island(
  id: 'visibility_conditions',
  label: new TranslatableMarkup('Visibility'),
  description: new TranslatableMarkup('Set visibility conditions for the active component or block.'),
  type: IslandType::Contextual,
)]
class VisibilityConditionsPanel extends IslandPluginBase implements IslandWithFormInterface, RenderableAltererInterface {

  use IslandWithFormTrait;

  /**
   * The condition manager.
   */
  protected ExecutableManagerInterface $conditionManager;

  /**
   * The context repository.
   */
  protected ContextRepositoryInterface $contextRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->conditionManager = $container->get('plugin.manager.condition');
    $instance->contextRepository = $container->get('context.repository');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $form['#tree'] = TRUE;

    $data = $form_state->getBuildInfo()['args'][0];
    $instance = $data['instance'] ?? [];
    $conditions = $this->conditionManager->getDefinitions();
    unset($conditions['response_status']);

    // Gather all available contexts.
    $gathered_contexts = $form_state->getTemporaryValue('gathered_contexts') ?? [];
    $form_state->setTemporaryValue('gathered_contexts', $gathered_contexts + $this->contextRepository->getAvailableContexts());

    foreach ($conditions as $condition_id => $definition) {
      if ($condition_id === 'current_theme') {
        continue;
      }

      if (\str_starts_with($condition_id, 'entity_bundle:')) {
        continue;
      }

      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->conditionManager->createInstance($condition_id, $instance[$condition_id] ?? []);
      $form_state->set(['conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = (\is_array($definition) && isset($definition['label'])) ? $definition['label'] : '';
      $form[$condition_id] = $condition_form;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Remove configuration if it matches the defaults.
    $visibility = new ConditionPluginCollection($this->conditionManager);
    $conditions = $form_state->get('conditions');

    foreach ($conditions as $condition_id => $condition) {
      $condition->submitConfigurationForm($form[$condition_id], SubformState::createForSubform($form[$condition_id], $form, $form_state));
      $visibility->set($condition_id, $condition);
      $form_state->unsetValue($condition_id);
    }

    foreach ($visibility->getConfiguration() as $condition_id => $configuration) {
      $form_state->setValue($condition_id, $configuration);
    }

    // Those two lines are necessary to prevent the form from being rebuilt.
    // if rebuilt, the form state values will have both the computed ones
    // and the raw ones (wrapper key and values).
    $form_state->setRebuild(FALSE);
    $form_state->setExecuted();
  }

  /**
   * {@inheritdoc}
   */
  public function alterElement(array $element, array $data = []): array {
    $available_contexts = $this->contextRepository->getAvailableContexts();

    foreach (\array_keys($data) as $condition_id) {
      /** @var \Drupal\Component\Plugin\ContextAwarePluginInterface $condition */
      $condition = $this->conditionManager->createInstance($condition_id, $data[$condition_id] ?? []);

      // Apply context mapping.
      $context_mapping = $condition->getContextMapping();

      foreach ($context_mapping as $key => $value) {
        if (isset($available_contexts[$value])) {
          $condition->setContextValue($key, $available_contexts[$value]->getContextValue());
        }
      }

      /** @var \Drupal\Core\Executable\ExecutableInterface $condition */
      if (!$this->conditionManager->execute($condition)) {
        return [];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToRoot(string $builder_id, string $instance_id): array {
    return $this->reloadWithInstanceData($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onAttachToSlot(string $builder_id, string $instance_id, string $parent_id): array {
    return $this->reloadWithInstanceData($builder_id, $instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function onActive(string $builder_id, array $data): array {
    return $this->reloadWithLocalData($builder_id, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function onDelete(string $builder_id, string $parent_id): array {
    return $this->reloadWithLocalData($builder_id, []);
  }

}
