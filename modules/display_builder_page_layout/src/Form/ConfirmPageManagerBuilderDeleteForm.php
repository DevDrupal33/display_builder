<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\display_builder\StorageProperties;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\StateManager\StateManagerInterface;

/**
 * Confirmation form to confirm deletion of display builder instance.
 */
class ConfirmPageManagerBuilderDeleteForm extends ConfirmFormBase {

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

    try {
      $pages = $this->entityTypeManager->getStorage('page');

      /** @var \Drupal\page_manager\PageInterface $page */
      foreach ($pages->loadMultiple() as $page) {
        foreach ($page->getVariants() as $variant) {
          if (!isset($variant->get('variant_settings')[StorageProperties::InstanceId->value])) {
            continue;
          }

          if ($variant->get('variant_settings')[StorageProperties::InstanceId->value] === $builder_id) {
            $form = parent::buildForm($form, $form_state);
            unset($form['confirm'], $form['actions']['submit']);
            $form['#title'] = $this->t('This Display Builder can not be deleted.');
            $form['description']['#markup'] = $this->t('This Display Builder is used as a variant in page %id, you must delete this variant to be able to delete this Display builder.', ['%id' => $page->id()]);

            return $form;
          }
        }
      }
    }
    catch (\Throwable $th) {
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('display_builder_page_layout.page_manager.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'confirm_display_builder_delete_form';
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
    $form_state->setRedirectUrl(new Url('display_builder_page_layout.page_manager.collection'));
  }

}
