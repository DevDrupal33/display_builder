<?php

declare(strict_types=1);

namespace Drupal\display_builder\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityChangesDetectionTrait;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\SynchronizableEntityTrait;
use Drupal\Core\Field\FieldDefinition;
use Drupal\Core\Field\FieldItemList;

/**
 * A fake, temporary, implementation of ContentEntityInterface.
 */
abstract class ContentEntityBase extends EntityBase implements \IteratorAggregate, ContentEntityInterface {

  use EntityChangesDetectionTrait {
    getFieldsToSkipFromTranslationChangesCheck as traitGetFieldsToSkipFromTranslationChangesCheck;
  }
  use SynchronizableEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, mixed $entity_type, mixed $bundle = FALSE, mixed $translations = []) {
    unset($bundle, $translations);

    parent::__construct($values, $entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \ArrayIterator {
    return new \ArrayIterator($this->getFields());
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function setNewRevision(mixed $value = TRUE): void {}

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function getLoadedRevisionId(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function updateLoadedRevisionId() {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function isNewRevision(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function isDefaultRevision(mixed $new_value = NULL): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function wasDefaultRevision(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function isLatestRevision(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableRevisionableInterface.
   */
  public function isLatestTranslationAffectedRevision(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableRevisionableInterface.
   */
  public function isRevisionTranslationAffected(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableRevisionableInterface.
   */
  public function setRevisionTranslationAffected(mixed $affected) {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableRevisionableInterface.
   */
  public function isRevisionTranslationAffectedEnforced() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableRevisionableInterface.
   */
  public function setRevisionTranslationAffectedEnforced(mixed $enforced) {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function isDefaultTranslation() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function getRevisionId() {
    return '';
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function isTranslatable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From RevisionableInterface.
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record): void {}

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function validate() {
    $violations = $this->getTypedData()->validate();

    return new EntityConstraintViolationList($this, $violations);
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function isValidationRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function setValidationRequired(mixed $required) {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From ContentEntityInterface.
   */
  public function getBundleEntity(): ?EntityInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function get(mixed $field_name) {
    $definition = new FieldDefinition([]);

    return FieldItemList::createInstance($definition);
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function set(mixed $name, mixed $value, mixed $notify = TRUE) {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function getFields(mixed $include_computed = TRUE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function getTranslatableFields(mixed $include_computed = TRUE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function getFieldDefinition(mixed $name) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function getFieldDefinitions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function onChange(mixed $name): void {}

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function getTranslation(mixed $langcode) {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function getUntranslated() {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function hasTranslation(mixed $langcode): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function isNewTranslation(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public function hasField(mixed $field_name) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function addTranslation(mixed $langcode, array $values = []) {
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function removeTranslation(mixed $langcode): void {}

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function getTranslationLanguages(mixed $include_default = TRUE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * From FieldableEntityInterface.
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, mixed $bundle, array $base_field_definitions) {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableInterface.
   */
  public function hasTranslationChanges(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * From TranslatableRevisionableInterface.
   */
  public function isDefaultTranslationAffectedOnly(): bool {
    return FALSE;
  }

}
