<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\Field\FieldType;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\MapFieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\MapItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A single step in the instance log history.
 */
#[FieldType(
  id: 'step',
  label: new TranslatableMarkup('History step'),
  no_ui: TRUE,
  list_class: MapFieldItemList::class,
)]
class HistoryStep extends MapItem {

  /**
   * Get data.
   */
  public function getData(): array {
    return $this->getValue()['data'] ?? [];
  }

  /**
   * Set data.
   */
  public function setData(array $data): void {
    $values = $this->getValue();
    $values['data'] = $data;
    $this->setValue($values);
  }

  /**
   * Get hash.
   */
  public function getHash(): int {
    return $this->getValue()['hash'];
  }

  /**
   * Get log.
   */
  public function getLog(): FormattableMarkup|string|null {
    return $this->getValue()['log'];
  }

  /**
   * Get time.
   */
  public function getTime(): int {
    return $this->getValue()['time'];
  }

  /**
   * Ger user.
   */
  public function getUser(): ?int {
    return $this->getValue()['user'] ?? NULL;
  }

}
