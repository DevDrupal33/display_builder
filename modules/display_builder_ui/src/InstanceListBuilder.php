<?php

declare(strict_types=1);

namespace Drupal\display_builder_ui;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder_entity_view\Entity\EntityViewDisplay;
use Drupal\display_builder_entity_view\Field\DisplayBuilderItemList;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\display_builder_views\Plugin\views\display_extender\DisplayExtender;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of display builders instances.
 */
final class InstanceListBuilder extends EntityListBuilder {

  /**
   * Mapping of context classes to human-readable labels.
   *
   * @var array<string, array{class-string, string}>
   *
   * @todo to have from a hook_info in each module.
   */
  protected array $contextClasses = [
    'views' => [DisplayExtender::class, 'Views'],
    'page_layout' => [PageLayout::class, 'Page layout'],
    'entity_view' => [EntityViewDisplay::class, 'Entity view'],
    'entity_view_override' => [DisplayBuilderItemList::class, 'Entity view override'],
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
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
  public function buildHeader(): array {
    $header = [];
    $header['id'] = $this->t('Instance');
    $header['display'] = $this->t('Display');
    $header['profile'] = $this->t('Profile');
    $header['updated'] = $this->t('Updated');
    $header['log'] = $this->t('Last log');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    // Number of items to display per page.
    $this->limit = 20;
    $build = parent::render();
    $build['notice'] = [
      '#markup' => $this->t('List of all Display builder instances.<br>An instance is a saved arrangement of components and styles for a specific display context (a view mode, a page layout or a view).<br>Instances are created directly from display pages like Entity view, Page layout or Views and should be managed directly from each display context.'),
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
    $url = Url::fromRoute('display_builder_dev_tools.view', ['instance_id' => $instance_id]);
    $type = $this->t('Other');

    foreach ($this->contextClasses as [$class, $label]) {
      if (\class_exists($class) && $instance->hasSaveContextsRequirement($class::getContextRequirement())) {
        $url = $class::getUrlFromInstanceId($instance_id);
        $type = new TranslatableMarkup($label); // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString

        break;
      }
    }

    $row['id']['data'] = [
      '#type' => 'link',
      '#title' => $instance_id,
      '#url' => $url,
    ];
    $row['profile']['data'] = $instance->getProfile()->id();
    $row['display']['data'] = $type;

    /** @var \Drupal\display_builder\HistoryStep $present */
    $present = $instance->getCurrent();
    $row['updated']['data'] = $present->time ? DisplayBuilderHelpers::formatTime($this->dateFormatter, (int) $present->time) : '-';
    $row['log']['data'] = $present->log ?? '-';

    return $row + parent::buildRow($instance);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $links = parent::getOperations($entity);
    $context = [
      'instance_id' => $entity->id(),
      'class' => '',
    ];
    $this->moduleHandler()->alter('display_builder_ui_operations_links', $links, $context);

    return $links;
  }

  /**
   * Helper to get builder type string for filtering.
   *
   * @param \Drupal\display_builder\InstanceInterface $instance
   *   Display builder instance.
   *
   * @return string
   *   The type string.
   */
  protected function getBuilderType(InstanceInterface $instance): string {
    foreach ($this->contextClasses as $type => [$class, $label]) {
      if (\class_exists($class) && $instance->hasSaveContextsRequirement($class::getContextRequirement())) {
        return $type;
      }
    }

    return 'other';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds(): array {
    // To avoid implementing EntityStorageInterface::getQuery()
    return \array_keys($this->getStorage()->loadMultiple());
  }

}
