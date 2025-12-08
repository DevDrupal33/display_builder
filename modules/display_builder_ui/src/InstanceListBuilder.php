<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\display_builder\DisplayBuilderHelpers;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of display builders instances.
 */
final class InstanceListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'display_builder_instance_list_builder';
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity = parent::load();
    // Sort by most recently updated.
    \usort($entity, static function ($a, $b) {
      return $b->present->time <=> $a->present->time;
    });

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header = [
      'id' => $this->t('Instance'),
      'profile' => $this->t('Profile'),
      'context' => $this->t('Context'),
      'updated' => $this->t('Updated'),
      'log' => $this->t('Last log'),
    ];

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['notice'] = [
      '#markup' => '<p>' . $this->t('Instances are versions of displays (entity views, page layouts, views...) currently under work.') . ' '
      . $this->t('They are created automatically from the displays and must be managed from them.') . '</p>',
      '#weight' => -100,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $instance): array {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance_id = (string) $instance->id();
    $row = [];

    $type = '-';
    $providers = $this->moduleHandler->invokeAll('display_builder_provider_info');

    foreach ($providers as $provider) {
      if (\str_starts_with($instance_id, $provider['prefix'])) {
        $type = $provider['label'];

        break;
      }
    }

    $row['id']['data'] = $instance_id;
    $row['profile']['data'] = $instance->getProfile()->label();
    $row['context']['data'] = $type;

    /** @var \Drupal\display_builder\HistoryStep $present */
    $present = $instance->getCurrent();
    $row['updated']['data'] = $present->time ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $present->time) : '-';
    $row['log']['data'] = $present->log ?? '-';

    return $row + parent::buildRow($instance);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    // To avoid implementing EntityStorageInterface::getQuery()
    return \array_keys($this->getStorage()->loadMultiple());
  }

}
