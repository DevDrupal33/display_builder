<?php

declare(strict_types=1);

namespace Drupal\display_builder\Form;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a display builder form.
 */
final class VisibilityConditionsForm extends FormBase {

  public function __construct(protected ConditionManager $manager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new self(
      $container->get('plugin.manager.condition')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#tree'] = TRUE;
    $data = $form_state->getBuildInfo()['args'][0];
    $conditions = $this->manager->getDefinitions();
    unset($conditions['response_status']);

    foreach ($conditions as $condition_id => $definition) {
      if (str_starts_with($condition_id, 'entity_bundle:')) {
        continue;
      }
      /** @var \Drupal\Core\Condition\ConditionInterface $condition */
      $condition = $this->manager->createInstance($condition_id, $data[$condition_id] ?? []);
      $form_state->set(['conditions', $condition_id], $condition);
      $condition_form = $condition->buildConfigurationForm([], $form_state);
      $condition_form['#type'] = 'details';
      $condition_form['#title'] = (\is_array($definition) && isset($definition['label'])) ? $definition['label'] : '';
      $form[$condition_id] = $condition_form;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_block_visibility';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Those two lines are necessary to prevent the form from being rebuilt.
    // if rebuilt, the form state values will have both the computed ones
    // and the raw ones (wrapper key and values).
    $form_state->setRebuild(FALSE);
    $form_state->setExecuted();
  }

}
