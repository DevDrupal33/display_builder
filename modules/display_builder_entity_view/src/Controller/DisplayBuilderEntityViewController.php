<?php

declare(strict_types=1);

namespace Drupal\display_builder_entity_view\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder_entity_view\EventSubscriber\DisplayBuilderSubscriber;
use Drupal\display_builder\StateManager\StateManagerInterface;
use Drupal\display_builder\Entity\DisplayBuilder;
use Drupal\ui_patterns\Entity\SampleEntityGeneratorInterface;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Defines a controller to get access the Display Builder admin UI.
 *
 * @internal
 *   Controller classes are internal.
 */
final class DisplayBuilderEntityViewController extends ControllerBase {

  public function __construct(
    protected StateManagerInterface $stateManager,
    #[Autowire(service: 'ui_patterns.sample_entity_generator')]
    protected SampleEntityGeneratorInterface $sampleEntityGenerator,
  ) {}

  /**
   * Get the display builder ID for the entity view display.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param string $view_mode_name
   *   The view mode name.
   *
   * @return string
   *   The display builder ID.
   */
  public static function getDisplayBuilderId($entity_type_id, $bundle, $view_mode_name): string {
    return "display_builder_entity_view__{$entity_type_id}" . '__' . ($bundle ? $bundle . '__' : '') . $view_mode_name;
  }

  /**
   * Get the entity view display parameters from the route.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return array<string, string>
   *   The entity view display parameters.
   */
  public static function getEntityViewDisplayParameters(RouteMatchInterface $route_match): array {
    $entity_type_id = $route_match->getParameter('entity_type_id');
    $bundle_key = $route_match->getParameter('bundle_key');
    $bundle = $bundle_key ? $route_match->getParameter($bundle_key) : '';

    if (\is_object($bundle)) {
      /** @var \Drupal\Core\Entity\EntityInterface $bundle */
      // @phpstan-ignore-next-line
      $bundle = $bundle->id();
    }
    $view_mode_name = $route_match->getParameter('view_mode_name');

    return [
      'entity_type_id' => $entity_type_id,
      'bundle' => $bundle,
      'view_mode_name' => $view_mode_name,
    ];
  }

  /**
   * Provides a generic title callback for a display used in entities.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the display page.
   */
  public function title(RouteMatchInterface $route_match): TranslatableMarkup {
    $entity_view_display_parameters = self::getEntityViewDisplayParameters($route_match);
    $param = [
      '@bundle' => ucfirst($entity_view_display_parameters['bundle']),
      '@view_mode_name' => $entity_view_display_parameters['view_mode_name'],
    ];

    return $this->t('Display builder for @bundle, @view_mode_name', $param);
  }

  /**
   * Renders the Layout UI.
   *
   * @return array
   *   A render array.
   */
  public function show(RouteMatchInterface $route_match): array {
    // Disable cache page.
    \Drupal::service('page_cache_kill_switch')->trigger(); // phpcs:ignore

    $entity_view_display_parameters = self::getEntityViewDisplayParameters($route_match);
    $entity_type_id = $entity_view_display_parameters['entity_type_id'];
    $bundle = $entity_view_display_parameters['bundle'];
    $builder_id = self::getDisplayBuilderId(
      $entity_type_id,
      $bundle,
      $entity_view_display_parameters['view_mode_name']
    );

    // Load the config used by this display builder.
    $current = $this->stateManager->load($builder_id);

    if (!isset($current['entity_config_id'])) {
      $current['entity_config_id'] = DisplayBuilder::DISPLAY_BUILDER_CONFIG;
    }

    $display_builder_id = $current['entity_config_id'];

    $storage = $this->entityTypeManager()->getStorage('display_builder');
    /** @var \Drupal\display_builder\DisplayBuilderInterface $displayBuilderConfig */
    $displayBuilderConfig = $storage->load($display_builder_id);

    // We build the rendered page.
    $build = [];
    // We add the display builder.
    $contexts = $this->stateManager->getContexts($builder_id);

    if ($contexts === NULL) {
      $sampleEntity = $this->sampleEntityGenerator->get($entity_type_id, $bundle);
      $stringContextDefinition = ContextDefinition::create('string');
      $contexts = [
        'entity' => EntityContext::fromEntity($sampleEntity),
        'bundle' => new Context($stringContextDefinition, $bundle ?? ''),
        'view_mode' => new Context($stringContextDefinition, $entity_view_display_parameters['view_mode_name']),
      ];
      $contexts = RequirementsContext::addToContext([DisplayBuilderSubscriber::CONTEXT_REQUIREMENT], $contexts);
      $this->stateManager->create(
        $builder_id,
        $current['entity_config_id'],
        [],
        $contexts,
      );
    }
    $build[] = $displayBuilderConfig->build($builder_id, $contexts);

    return $build;
  }

}
