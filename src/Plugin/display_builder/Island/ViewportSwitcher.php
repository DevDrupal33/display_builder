<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\breakpoint\BreakpointManager;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\IslandConfigurationFormInterface;
use Drupal\display_builder\IslandConfigurationFormTrait;
use Drupal\display_builder\IslandPluginBase;
use Drupal\display_builder\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Island plugin implementation.
 */
#[Island(
  id: 'viewport',
  label: new TranslatableMarkup('Viewport switcher'),
  description: new TranslatableMarkup('Change main region width according to breakpoints.'),
  type: IslandType::Button,
  modules: ['breakpoint'],
)]
class ViewportSwitcher extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  private const HIDE_PROVIDER = ['toolbar'];

  /**
   * The module list extension service.
   */
  protected ThemeExtensionList $themeList;

  /**
   * The module list extension service.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * The breakpoint manager.
   */
  protected BreakpointManager $breakpointManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeList = $container->get('extension.list.theme');
    $instance->moduleList = $container->get('extension.list.module');
    $instance->breakpointManager = $container->get('breakpoint.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'exclude' => [],
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

    $form['exclude'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude providers'),
      '#options' => $this->getProvidersOptions($this->t('breakpoint'), $this->t('breakpoints')),
      '#default_value' => $configuration['exclude'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function configurationSummary(): array {
    $configuration = $this->getConfiguration();
    $summary = [];

    $exclude = \array_filter($configuration['exclude'] ?? []);

    $summary[] = $this->t('Excluded providers: @exclude', [
      '@exclude' => ($exclude = \array_filter($configuration['exclude'] ?? [])) ? \implode(', ', $exclude) : $this->t('None'),
    ]);

    $summary[] = $this->t('Format: @format', [
      '@format' => $configuration['format'] ?? 'default',
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $configuration = $this->getConfiguration();
    $definitions = $this->getDefinitions();

    $groups = [];

    foreach ($definitions as $definition) {
      if (!isset($definition['group'])) {
        continue;
      }
      $groups[$definition['group']] = $definition['group'];
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
   * Get the definitions list which is used by a few methods.
   *
   * @return array
   *   Breakpoints plugin definitions as associative arrays.
   */
  public function getDefinitions(): array {
    $definitions = $this->breakpointManager->getDefinitions();

    foreach ($definitions as $definition_id => $definition) {
      if (isset($definition['provider']) && \in_array($definition['provider'], self::HIDE_PROVIDER, TRUE)) {
        unset($definitions[$definition_id]);
      }

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

  /**
   * Get providers options for select input.
   *
   * @param string|TranslatableMarkup $singular
   *   Singular label of the plugins.
   * @param string|TranslatableMarkup $plural
   *   Plural label of the plugins.
   *
   * @return array
   *   An associative array with extension ID as key and extension description
   *   as value.
   */
  protected function getProvidersOptions(string|TranslatableMarkup $singular = 'definition', string|TranslatableMarkup $plural = 'definitions'): array {
    $options = [];

    foreach ($this->getProviders($this->getDefinitions()) as $provider_id => $provider) {
      $params = [
        '@name' => $provider['name'],
        '@type' => $provider['type'],
        '@count' => $provider['count'],
        '@singular' => $singular,
        '@plural' => $plural,
      ];
      $options[$provider_id] = $this->formatPlural($provider['count'], '@name (@type, @count @singular)', '@name (@type, @count @plural)', $params);
    }

    return $options;
  }

  /**
   * Get all providers.
   *
   * @param array $definitions
   *   Plugin definitions.
   *
   * @return array
   *   Drupal extension definitions, keyed by extension ID
   */
  protected function getProviders(array $definitions): array {
    $themes = $this->themeList->getAllInstalledInfo();
    $modules = $this->moduleList->getAllInstalledInfo();
    $providers = [];

    foreach ($definitions as $definition) {
      $provider_id = $definition['provider'];
      $provider = $themes[$provider_id] ?? $modules[$provider_id] ?? NULL;

      if (!$provider) {
        continue;
      }
      $provider['count'] = isset($providers[$provider_id]) ? ($providers[$provider_id]['count']) + 1 : 1;
      $providers[$provider_id] = $provider;
    }

    return $providers;
  }

}
