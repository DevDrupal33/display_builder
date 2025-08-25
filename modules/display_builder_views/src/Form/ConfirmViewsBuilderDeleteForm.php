<?php

declare(strict_types=1);

namespace Drupal\display_builder_views\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;

/**
 * Confirmation form to confirm deletion of display builder instance.
 */
class ConfirmViewsBuilderDeleteForm extends ConfirmFormBase {

  use AutowireTrait;

  /**
   * Display builder id to delete.
   */
  private ?string $builderId;

  public function __construct(
    private readonly StateManagerInterface $stateManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $builder_id = NULL): array {
    $this->builderId = $builder_id;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('display_builder_views.views.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'confirm_display_builder_views_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you want to delete %id?', ['%id' => $this->builderId]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->stateManager->delete($this->builderId);
    $this->unsetDisplayBuilder();
    $form_state->setRedirectUrl(new Url('display_builder_views.views.collection'));
  }

  /**
   * Unset display builder.
   */
  protected function unsetDisplayBuilder(): void {
    $view_id = DisplayExtender::checkInstanceId($this->builderId)['view'];
    $display_id = DisplayExtender::checkInstanceId($this->builderId)['display'];
    $view = $this->entityTypeManager->getStorage('view')->load($view_id);
    // It is risky to alter a View like that. We need to be careful to not
    // break the storage integrity, but we didn't find a better way.
    $displays = $view->get('display');
    $displays[$display_id]['display_options']['display_extenders']['display_builder']['display_builder'] = '';
    $view->set('display', $displays);
    $view->save();
  }

}
