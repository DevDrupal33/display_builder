<?php

declare(strict_types=1);

namespace Drupal\display_builder_test\Plugin\UiPatterns\Source;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\SourceWithSlotsInterface;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\PropTypeInterface;
use Drupal\ui_patterns\SourcePluginBase;

/**
 * Test source plugin implementing SourceWithSlotsInterface.
 *
 * Enables testing of PatternPreset::getContextsFromSlots() without requiring
 * a full ComponentSource or LayoutSource setup.
 */
#[Source(
  id: 'test_slot_source',
  label: new TranslatableMarkup('Test Slot Source'),
  description: new TranslatableMarkup('A test source plugin that implements SourceWithSlotsInterface.'),
  prop_types: ['slot'],
)]
class TestSlotSource extends SourcePluginBase implements SourceWithSlotsInterface {

  /**
   * {@inheritdoc}
   */
  public function getValue(?PropTypeInterface $prop_type = NULL): mixed {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPropValue(): mixed {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotDefinitions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSlotPath(string $slot_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValues(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSlotValue(string $slot_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setSlotValue(string $slot_id, array $slot): array {
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setSlotRenderable(array $build, string $slot_id, array $slot): array {
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsFormPropsOnly(array $form, FormStateInterface $form_state): array {
    return [];
  }

}
