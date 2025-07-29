<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns_views\Plugin\UiPatterns\Source\ViewsSourceBase;

/**
 * Plugin implementation of the source for views.
 */
abstract class ViewsUiPatternsSourceBase extends ViewsSourceBase {

  use RenderableBuilderTrait;

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

    if (!$view) {
      return [];
    }

    $name = self::getVariableId();

    try {
      $variable = $this->getContextValue('ui_patterns_views:variables')[$name] ?? NULL;

      if ($variable) {
        return $this->renderOutput($variable);
      }

      return [];
    }
    catch (\Throwable $th) {
      // If no context will fail with:
      // "The ui_patterns_views:variables context is not a valid context."
      // then we are mostly in preview mode.
      // @todo have a real preview of the view area content.
      $build = $this->buildPlaceholder($this->t('[View] @name placeholder', ['@name' => \ucfirst($name)]));
      $build['#props']['variant'] = 'button';

      return $build;
    }
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
