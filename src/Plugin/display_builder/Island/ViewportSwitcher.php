<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\breakpoint\BreakpointManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\HtmxEvents;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandPluginConfigurationFormTrait;
use Drupal\display_builder\IslandType;
use Drupal\display_builder\PluginProvidersTrait;
use Drupal\ui_patterns\SourcePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Island plugin implementation.
 */
#[Island(
  id: 'viewport',
  label: new TranslatableMarkup('Viewport switcher'),
  description: new TranslatableMarkup('Change main region width according to breakpoints.'),
  type: IslandType::Button,
)]
class ViewportSwitcher extends IslandPluginBase implements PluginFormInterface {

  use IslandPluginConfigurationFormTrait;
  use PluginProvidersTrait;

  /**
   * Breakpoint plugin definitions.
   *
   * @var array
   *   The definitions.
   */
  private array $definitions = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ComponentPluginManager $sdcManager,
    protected HtmxEvents $htmxEvents,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EventSubscriberInterface $eventSubscriber,
    protected SourcePluginManager $sourceManager,
    protected ThemeManagerInterface $themeManager,
    protected ModuleExtensionList $modules,
    protected ThemeExtensionList $themes,
    protected BreakpointManager $breakpointManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $sdcManager, $htmxEvents, $entityTypeManager, $eventSubscriber, $sourceManager);
    $this->definitions = $this->initDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.sdc'),
      $container->get('display_builder.htmx_events'),
      $container->get('entity_type.manager'),
      $container->get('display_builder.event_subscriber'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('theme.manager'),
      $container->get('extension.list.module'),
      $container->get('extension.list.theme'),
      $container->get('breakpoint.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'providers' => $this->getDefaultProviders($this->definitions),
      'format' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Selector format'),
      '#description' => $this->t('Choose the appearance of the selector. Normal select or a compact dropdown menu.'),
      '#options' => [
        'default' => $this->t('Default'),
        'compact' => $this->t('Compact'),
      ],
      '#default_value' => $configuration['format'],
    ];

    $form['providers'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed providers'),
      '#options' => $this->getProvidersOptions($this->definitions, $this->t('breakpoint'), $this->t('breakpoints')),
      '#default_value' => $configuration['providers'],
    ];

    $skipped_providers = \array_diff_key($this->breakpointManager->getGroups(), $this->getProviders($this->definitions));

    if (!empty($skipped_providers)) {
      $params = ['@providers' => \implode(', ', $skipped_providers)];
      $form['providers']['#description'] = $this->t('Skipped breakpoints providers: @providers', $params);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();
    $summary = [];

    $providers = \array_filter($configuration['providers'] ?? []);

    $summary[] = empty($providers) ? $this->t('This island will be hidden.') : '';

    $summary[] = $this->t('Allowed providers: @providers', [
      '@providers' => ($providers = \array_filter($configuration['providers'] ?? [])) ? \implode(', ', $providers) : $this->t('None'),
    ]);

    $summary[] = $this->t('Format: @format', [
      '@format' => $configuration['format'] ?? 'default',
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data, array $options = []): array {
    $configuration = $this->getConfiguration();
    $groups = \array_filter($configuration['providers']);

    if (empty($groups)) {
      return [];
    }

    $options = $data = [];
    $items = [
      [
        'title' => $this->t('Fluid'),
        'class' => 'active',
      ],
      [
        'divider' => TRUE,
      ],
    ];

    foreach ($groups as $group_id => $label) {
      $points = $this->breakpointManager->getBreakpointsByGroup($group_id);

      foreach ($points as $point_id => $point) {
        $query = $point->getMediaQuery();
        $width = $this->getMaxWidthValueFromMediaQuery($query);

        if (!$width) {
          continue;
        }
        $point_label = $point->getLabel();
        $options[$label][$point_id] = $point_label;
        $items[] = [
          'title' => $point_label,
          'value' => $point_id,
        ];
        $data[$point_id] = $width;
      }
    }

    $select = [
      '#type' => 'component',
      '#component' => 'display_builder:select',
      '#attached' => [
        'library' => ['display_builder/viewport_switcher'],
      ],
      '#props' => [
        'options' => $options,
        'icon' => 'window',
        'empty_option' => $this->t('Fluid'),
      ],
      '#attributes' => [
        'style' => 'display: inline-block;',
        'data-points' => \json_encode($data),
        'data-island-action' => 'viewport',
      ],
    ];

    if ($configuration['format'] !== 'compact') {
      return $select;
    }

    unset($select['#attributes']['style']);
    $select['#props']['icon'] = NULL;

    $button = $this->buildButton('', NULL, 'display', $this->t('Switch viewport of this display'));
    $button['#attributes']['class'] = ['switch-viewport-btn'];

    return [
      '#type' => 'component',
      '#component' => 'display_builder:dropdown',
      '#slots' => [
        'button' => $button,
        'content' => [
          '#type' => 'component',
          '#component' => 'display_builder:menu',
          '#props' => [
            'items' => $items,
          ],
          '#attributes' => [
            'class' => ['viewport-menu', 'db-background'],
            'data-points' => \json_encode($data),
          ],
        ],
      ],
      '#props' => [
        'tooltip' => $this->t('Switch viewport'),
      ],
      '#attributes' => [
        'class' => ['switch-viewport'],
        'data-island-action' => 'viewport',
      ],
      '#attached' => [
        'library' => ['display_builder/viewport_switcher'],
      ],
    ];
  }

  /**
   * Init the definitions list which is used by a few methods.
   *
   * @return array
   *   Breakpoints plugin definitions as associative arrays.
   */
  public function initDefinitions(): array {
    $definitions = $this->breakpointManager->getDefinitions();

    foreach ($definitions as $definition_id => $definition) {
      // Exclude definitions with not supported media queries.
      if (!$this->getMaxWidthValueFromMediaQuery($definition['mediaQuery'])) {
        unset($definitions[$definition_id]);
      }
    }

    return $definitions;
  }

  /**
   * Get width and max width from media query.
   *
   * @param string $query
   *   The media query from the breakpoint definition.
   *
   * @return ?string
   *   The width with its unit (120px, 13em, 100vh...). Null is no width found.
   */
  protected function getMaxWidthValueFromMediaQuery(string $query): ?string {
    if (\str_contains($query, 'not ')) {
      // Queries with negated expression(s) are not supported.
      return NULL;
    }
    // Look for max-width: 1250px or max-width: 1250 px.
    \preg_match('/max-width:\s*([0-9]+)\s*([A-Za-z]+)/', $query, $matches);

    if (\count($matches) > 2) {
      return $matches[1] . $matches[2];
    }
    // Looking for width <= 1250px or <= 1250 px.
    \preg_match('/width\s*<=\s*([0-9]+)\s*([A-Za-z]+)/', $query, $matches);

    if (\count($matches) > 1) {
      return $matches[1];
    }

    // @todo Currently only supports queries with max-width or width <= using
    // px, em, vh units.
    // Does not support min-width, percentage units, or complex/combined media
    // queries.
    return NULL;
  }

}
