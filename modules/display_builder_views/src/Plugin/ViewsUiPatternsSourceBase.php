<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;

/**
 * Plugin implementation of the source for views.
 */
abstract class ViewsUiPatternsSourceBase extends ViewsSourceBase {

  /**
   * Set the views variable id passed to views-view.html.twig.
   *
   * @return string
   *   The views variable id.
   */
  abstract public static function setVariableId(): string;

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    $view = $this->getView();

    // This is for builder and preview, but not working.
    if (!$view) {
      return $this->t('View @label placeholder', ['@label' => $this->label()]);
    }

    $variable = $this->getContextValue('ui_patterns_views:variables')[$this::getVariableId()] ?? NULL;

    if (!$variable) {
      return '';
    }

    return $this->renderOutput($variable);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue(?PropTypeInterface $prop_type = NULL): mixed {
    return $this->getPropValue();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['label_map'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('This element must be configured in the corresponding view.'),
    ];

    return $form;
  }

  /**
   * Get the views variable id.
   *
   * @return string
   *   The views variable id.
   *
   * phpcs:disable DrupalPractice.Objects.UnusedPrivateMethod.UnusedMethod
   */
  private function getVariableId(): string {
    return $this::setVariableId();
  }

}
