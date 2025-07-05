<?php

declare(strict_types=1);

namespace Drupal\display_builder_page_layout;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\display_builder\DisplayBuilderHelpers;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\page_manager\PageVariantInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;

/**
 * Manage Display Builder Page Layout configuration.
 */
final class DisplayBuilderPageLayout implements ContainerInjectionInterface, DisplayBuilderPageLayoutInterface {

  use AutowireTrait;
  use LoggerChannelTrait;

  public const PAGE_LAYOUT_CONFIG = 'display_builder.page_display.page_layout';

  public const PAGE_LAYOUT_CONTEXT_REQUIREMENT = 'is_display_builder_page_layout';

  public const PAGE_MANAGER_CONTEXT_REQUIREMENT = 'is_display_builder_page_manager';

  private const PAGE_LAYOUT_PREFIX = 'page_layout_';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    private readonly StateManagerInterface $stateManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function delete(): void {
    $page_layout_config = $this->configFactory->getEditable(self::PAGE_LAYOUT_CONFIG);
    $builder_id = $page_layout_config->get('builder_id');

    $this->stateManager->delete($builder_id);
    $page_layout_config->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function newPageLayout(): string {
    // Generate a uniq display builder instance id.
    $builder_id = \sprintf('%s%s', self::PAGE_LAYOUT_PREFIX, uniqid());

    $builder_data = DisplayBuilderHelpers::getFixtureDataFromExtension('display_builder_page_layout', 'page_layout');

    $this->newInstance($builder_data, $builder_id);
    $this->savePageLayout($builder_data, $builder_id);

    return $builder_id;
  }

  /**
   * {@inheritdoc}
   */
  public function savePageLayoutCurrentState(string $builder_id): void {
    $this->savePageLayout(NULL, $builder_id);
  }

  /**
   * {@inheritdoc}
   */
  public function savePageManagerCurrentState(string $builder_id, array $contexts): void {
    $page_manager_variant_uuid = $contexts['page_manager_variant_uuid']->getContextValue() ?? NULL;

    if ($page_manager_variant_uuid === NULL || empty($page_manager_variant_uuid)) {
      throw new \LogicException('Missing or invalid page_manager_variant_uuid value context in this builder.');
    }

    $pageVariant = $this->findPageByVariantUuid($page_manager_variant_uuid);

    $variant_settings = $pageVariant->get('variant_settings');
    $variant_settings['display_builder_sources'] = $this->stateManager->getCurrentState($builder_id);
    $pageVariant->set('variant_settings', $variant_settings);

    $pageVariant->save();
  }

  /**
   * Create a new display builder instance for page layout.
   *
   * @param array $builder_data
   *   The display builder data.
   * @param string $builder_id
   *   The display builder instance id.
   */
  private function newInstance(array $builder_data, string $builder_id): void {
    $contexts = [];
    $contexts = RequirementsContext::addToContext([DisplayBuilderPageLayout::PAGE_LAYOUT_CONTEXT_REQUIREMENT], $contexts);
    $this->stateManager->create(
      $builder_id,
      DisplayBuilder::DISPLAY_BUILDER_CONFIG,
      $builder_data,
      $contexts,
    );
  }

  /**
   * Save the page layout configuration.
   *
   * @param array|null $builder_data
   *   (Optional) The builder data of sources, default to the current state.
   * @param string|null $builder_id
   *   (Optional) The builder id, default to the id in config.
   * @param string|null $builder_config_id
   *   (Optional) The builder config id, default to the default config.
   */
  private function savePageLayout(?array $builder_data = NULL, ?string $builder_id = NULL, ?string $builder_config_id = NULL): void {
    $page_layout_config = $this->configFactory->getEditable(self::PAGE_LAYOUT_CONFIG);
    $builder_data ?? $builder_data = $this->stateManager->getCurrentState($builder_id);

    $page_layout_config
      ->set('builder_config_id', $builder_config_id ?? DisplayBuilder::DISPLAY_BUILDER_CONFIG)
      ->set('builder_id', $builder_id ?? $page_layout_config->get('builder_id'))
      ->set('sources', $builder_data)
      ->save();
  }

  /**
   * Find page by variant id.
   *
   * @param string $variant_uuid
   *   The variant id.
   *
   * @return \Drupal\page_manager\PageVariantInterface
   *   The page variant.
   *
   * @throw \LogicException
   *   If no variant found.
   */
  private function findPageByVariantUuid(string $variant_uuid): PageVariantInterface {
    $pages = $this->entityTypeManager->getStorage('page');

    /** @var \Drupal\page_manager\PageInterface $page */
    foreach ($pages->loadMultiple() as $page) {
      foreach ($page->getVariants() as $variant) {
        if ($variant_uuid === $variant->get('variant_settings')['uuid']) {
          return $variant;
        }
      }
    }

    throw new \LogicException('No variant found to save to!');
  }

}
