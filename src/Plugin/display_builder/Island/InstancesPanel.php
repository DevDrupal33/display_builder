<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Instances island plugin implementation.
 *
 * Sidebar panel listing every instance the user may build, grouped by
 * buildable provider (entity view, page layout, view display...). It is the
 * instance collection of Display Builder UI, without leaving the builder.
 *
 * @see \Drupal\display_builder_ui\InstanceListBuilder
 */
#[Island(
  id: 'instances',
  label: new TranslatableMarkup('Instances'),
  description: new TranslatableMarkup('List all displays available to build and jump to any of them.'),
  type: IslandType::View,
  region: 'sidebar',
  icon: 'files',
)]
class InstancesPanel extends IslandPluginBase {

  /**
   * The display buildable plugin manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $groups = [];

    foreach ($this->displayBuildableManager->getDefinitions() as $definition) {
      $items = $this->buildProviderItems($definition['class'], (string) $builder->id());

      if (empty($items)) {
        continue;
      }

      $groups[] = [
        '#theme' => 'item_list',
        '#title' => $definition['label'],
        '#items' => $items,
        '#attributes' => ['class' => ['db-instances__list']],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['db-instances']],
      '#attached' => ['library' => ['display_builder/instances']],
      'groups' => $groups,
    ];
  }

  /**
   * Build the list items of a single buildable provider.
   *
   * @param class-string<\Drupal\display_builder\DisplayBuildableInterface> $class
   *   The buildable plugin class, used for its static collection helpers.
   * @param string $current_id
   *   The ID of the instance currently being built, rendered without a link.
   *
   * @return array
   *   Renderable list items, sorted by label.
   */
  protected function buildProviderItems(string $class, string $current_id): array {
    $items = [];

    foreach ($class::collectInstances() as $instance_id => $instance) {
      $instance_id = (string) $instance_id;

      if (!$class::checkAccess($instance_id, $this->currentUser)->isAllowed()) {
        continue;
      }

      $label = (string) $instance->label();

      if ($instance_id === $current_id) {
        $items[] = [
          'label' => $label,
          'build' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $label,
            '#attributes' => ['class' => ['db-instances__current']],
          ],
        ];

        continue;
      }

      $items[] = [
        'label' => $label,
        'build' => [
          '#type' => 'link',
          '#title' => $label,
          '#url' => $class::getUrlFromInstanceId($instance_id),
          '#attributes' => [
            'class' => ['db-instances__link'],
            'title' => $instance->getProfile()?->label() ?? '',
          ],
        ],
      ];
    }

    \usort($items, static fn (array $a, array $b): int => \strnatcasecmp($a['label'], $b['label']));

    return \array_column($items, 'build');
  }

}
