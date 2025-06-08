<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;

/**
 * Instance button island plugin implementation.
 */
#[Island(
  id: 'instance',
  label: new TranslatableMarkup('Instance buttons'),
  description: new TranslatableMarkup('Element actions that include the contextual islands to apply for the selected element.'),
  type: IslandType::Button,
  keyboard_shortcuts: [
    'e' => new TranslatableMarkup('Edit element (when selected)'),
    'd' => new TranslatableMarkup('Delete element (when selected)'),
  ],
)]
class InstanceButtons extends IslandPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(string $builder_id, array $data, array $options = []): array {
    return [
      '#type' => 'component',
      '#component' => 'display_builder:button_group',
      '#slots' => [
        'buttons' => [
          $this->buildInstanceButton($builder_id, $data),
          $this->buildRemoveButton($builder_id, $data),
        ],
      ],
    ];
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

  /**
   * Build instance islands trigger buttons.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $data
   *   The component data.
   *
   * @return array
   *   The renderable.
   */
  protected function buildInstanceButton(string $builder_id, array $data): array {
    if (empty($data) || !isset($data['_instance_id'])) {
      return [];
    }

    /** @var \Drupal\display_builder\SlotSourceProxy $proxy */
    $proxy = \Drupal::service('display_builder.slot_sources_proxy');
    $label = $proxy->getLabel($data);

    $title = $this->t('Edit instance @id', ['@id' => $data['_instance_id']]);

    $build = $this->buildButton($label, $title, 'e', FALSE, 'pencil');
    $build['#props']['variant'] = 'primary';
    $build['#attributes']['caret'] = TRUE;
    $build['#attributes']['outline'] = TRUE;
    // @see components/display_builder/modal/modal.js
    $build['#attributes']['data-modal-target'] = $builder_id . '-contextual';
    // @see usage in components/display_builder/display_builder.js
    $build['#attributes']['data-menu-action'] = 'edit';

    return $build;
  }

  /**
   * Build remove button.
   *
   * @param string $builder_id
   *   The builder ID.
   * @param array $data
   *   The component data.
   *
   * @return array
   *   The renderable.
   */
  protected function buildRemoveButton(string $builder_id, array $data): array {
    if (empty($data) || !isset($data['_instance_id'])) {
      return [];
    }

    $build = $this->buildButton('', $this->t('Remove this element'), 'd', FALSE, 'trash');
    $build['#props']['variant'] = 'danger';

    // @see components/display_builder/modal/modal.js
    $build['#attributes']['data-target'] = $builder_id . '-contextual';
    // In the JavaScript we don't want the click to open the modal if closed.
    $build['#attributes']['data-close-only'] = TRUE;

    return $this->htmxEvents->onClickDelete($build, $builder_id, $data['_instance_id']);
  }

}
