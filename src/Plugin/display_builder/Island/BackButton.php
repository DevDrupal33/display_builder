<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\DisplayBuildablePluginManager;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandPluginToolbarButtonConfigurationBase;
use Drupal\display_builder\Island\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Help parent link island plugin implementation.
 */
#[Island(
  id: 'back',
  enabled_by_default: TRUE,
  label: new TranslatableMarkup('Back'),
  description: new TranslatableMarkup('Exit the display builder and go back to admin UI.'),
  type: IslandType::Button,
  default_region: 'end',
)]
class BackButton extends IslandPluginToolbarButtonConfigurationBase {

  /**
   * The display buildable plugin manager.
   */
  protected DisplayBuildablePluginManager $displayBuildableManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->displayBuildableManager = $container->get('plugin.manager.display_buildable');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $url = $this->findParentDisplayFromId((string) $builder->id());

    if (!$url || !$this->isButtonEnabled('back')) {
      return [];
    }

    $button = $this->buildButton(
      ($this->showLabel('back')) ? $this->t('Back') : '',
      'back',
      $this->showIcon('back') ? 'box-arrow-up-right' : '',
      $this->t('Exit without losing any data.'),
    );
    $button['#attributes']['href'] = $url->toString();

    return $button;
  }

  /**
   * {@inheritdoc}
   */
  protected function hasButtons(): array {
    return [
      'back' => [
        'title' => $this->t('Back'),
        'default' => 'icon',
      ],
    ];
  }

  /**
   * Determine the parent display URL from the instance ID.
   *
   * @param string $instance_id
   *   The builder instance ID.
   *
   * @return \Drupal\Core\Url|null
   *   The URL of the parent display, or NULL if not found.
   */
  private function findParentDisplayFromId(string $instance_id): ?Url {
    foreach ($this->displayBuildableManager->getDefinitions() as $provider) {
      if (\str_starts_with($instance_id, $provider['instance_prefix'])) {
        return $provider['class']::getDisplayUrlFromInstanceId($instance_id);
      }
    }

    return NULL;
  }

}
