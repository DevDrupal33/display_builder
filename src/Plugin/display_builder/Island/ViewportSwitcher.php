<?php

declare(strict_types=1);

namespace Drupal\display_builder\Plugin\display_builder\Island;

use Drupal\breakpoint\BreakpointManager;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\display_builder\Attribute\Island;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandConfigurationFormInterface;
use Drupal\display_builder\Island\IslandConfigurationFormTrait;
use Drupal\display_builder\Island\IslandPluginBase;
use Drupal\display_builder\Island\IslandType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Island plugin implementation.
 */
#[Island(
  id: 'viewport',
  label: new TranslatableMarkup('Responsive width'),
  description: new TranslatableMarkup('Change main region width according to breakpoints for canvas and preview panels.'),
  type: IslandType::Floating,
  modules: ['breakpoint'],
  attach_to: ['builder', 'preview'],
)]
class ViewportSwitcher extends IslandPluginBase implements IslandConfigurationFormInterface {

  use IslandConfigurationFormTrait;

  private const HIDE_PROVIDER = ['toolbar', 'stark'];

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
      'format' => 'compact',
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
        'title' => $this->t('Fluid (Current viewport)'),
        'class' => 'active',
      ],
      [
        'divider' => TRUE,
      ],
    ];

    foreach ($this->getViewports($groups) as $viewport) {
      $point_id = $viewport['id'];
      $options[$viewport['group']][$point_id] = $viewport['label'];
      $items[] = [
        'title' => $viewport['label'],
        'value' => $point_id,
      ];
      $data[$point_id] = $viewport['width'];
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
        'empty_option' => $this->t('Fluid (Current viewport)'),
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

    // A plain title attribute, not the dropdown's own #props.tooltip (which
    // wraps the trigger in a Shoelace <sl-tooltip>): Floating UI computes a
    // permanently broken position for a tooltip that's part of the
    // server-rendered HTML inside a floating controls cluster.
    $button = $this->buildButton('', NULL, 'display');
    $button['#attributes']['class'] = ['switch-viewport-btn'];
    $button['#attributes']['size'] = 'small';
    $button['#attributes']['title'] = $this->t('Switch viewport of this display');

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
      '#attributes' => [
        // db-background: visual surface now that this floats over the
        // pane's own content, instead of sitting in the toolbar's chrome.
        'class' => ['switch-viewport', 'db-background'],
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
      if (!$this->getWidthValueFromMediaQuery($definition['mediaQuery'])) {
        unset($definitions[$definition_id]);
      }
    }

    return $definitions;
  }

  /**
   * Collect the de-duplicated viewport widths from the breakpoint groups.
   *
   * Breakpoints are read as boundaries on the width axis regardless of whether
   * they are expressed as min-width or max-width: mobile-first design systems
   * (Bootstrap's own SCSS, most Drupal core themes) declare only min-width, so
   * keying the switcher on max-width alone would leave it empty for them.
   * Design systems that ship both directions (e.g. UI Suite Bootstrap) describe
   * each boundary twice, one pixel apart (max-width: 575px vs min-width: 576px)
   * so near-duplicate edges are collapsed to a single entry.
   *
   * @param array $groups
   *   Breakpoint group IDs to read, keyed by group ID.
   *
   * @return array
   *   Viewport descriptors sorted by width, each with 'id', 'group', 'width'
   *   (a CSS length string) and 'label' (a synthesized device label).
   */
  protected function getViewports(array $groups): array {
    $candidates = [];

    foreach ($groups as $group_id => $group_label) {
      $points = $this->breakpointManager->getBreakpointsByGroup($group_id);

      foreach ($points as $point_id => $point) {
        $width = $this->getWidthValueFromMediaQuery($point->getMediaQuery());

        if ($width === NULL) {
          continue;
        }
        [$value, $unit] = $width;
        $candidates[] = [
          'id' => $point_id,
          'group' => $group_label,
          'value' => $value,
          'unit' => $unit,
        ];
      }
    }

    // Sort by unit then numeric value so mirror-pair edges (which share a unit
    // and sit one pixel apart) become neighbors for de-duplication below.
    \usort($candidates, static fn (array $a, array $b): int => [$a['unit'], $a['value']] <=> [$b['unit'], $b['value']]);

    $viewports = [];
    $previous = NULL;

    foreach ($candidates as $candidate) {
      // Collapse an edge within 2px of the previous kept one - mirror pairs
      // differ by exactly 1px. Different units never collapse.
      if ($previous !== NULL
        && $previous['unit'] === $candidate['unit']
        && \abs($previous['value'] - $candidate['value']) <= 2) {
        continue;
      }
      $viewports[] = [
        'id' => $candidate['id'],
        'group' => $candidate['group'],
        'width' => $candidate['value'] . $candidate['unit'],
        'label' => $this->getViewportLabel($candidate['value'], $candidate['unit']),
      ];
      $previous = $candidate;
    }

    return $viewports;
  }

  /**
   * Extract a width boundary from a media query.
   *
   * Reads the numeric width from a min-width, max-width, `width >=` or
   * `width <=` expression: the direction is irrelevant here, the value is the
   * boundary at which the layout changes and therefore a usable target width.
   *
   * @param string $query
   *   The media query from the breakpoint definition.
   *
   * @return array{int, string}|null
   *   The width value and its unit (e.g. [575, 'px']), or NULL when the query
   *   carries no supported width expression.
   */
  protected function getWidthValueFromMediaQuery(string $query): ?array {
    if (\str_contains($query, 'not ')) {
      // Queries with negated expression(s) are not supported.
      return NULL;
    }

    // min-width: 1250px or max-width: 1250 px (optional space before the unit).
    if (\preg_match('/(?:min|max)-width:\s*([0-9]+)\s*([A-Za-z]+)/', $query, $matches)) {
      return [(int) $matches[1], $matches[2]];
    }

    // Range syntax: width <= 1250px or width >= 1250px.
    if (\preg_match('/width\s*[<>]=\s*([0-9]+)\s*([A-Za-z]+)/', $query, $matches)) {
      return [(int) $matches[1], $matches[2]];
    }

    // @todo Currently only supports px, em, vh units. Does not support
    // percentage units or complex/combined media queries.
    return NULL;
  }

  /**
   * Build a friendly device label for a viewport width.
   *
   * A breakpoint's own label ("Large and smaller") reads awkwardly in a device
   * picker, so a width-bucketed device name is synthesized instead and the
   * exact width shown alongside it. Non-pixel units keep their raw value.
   *
   * @param int $value
   *   The numeric width.
   * @param string $unit
   *   The CSS length unit (px, em, ...).
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label, e.g. "Mobile (575px)".
   */
  protected function getViewportLabel(int $value, string $unit): TranslatableMarkup {
    if ($unit !== 'px') {
      return $this->t('@width', ['@width' => $value . $unit]);
    }

    $device = match (TRUE) {
      $value < 576 => $this->t('Mobile'),
      $value < 992 => $this->t('Tablet'),
      $value < 1400 => $this->t('Desktop'),
      default => $this->t('Large desktop'),
    };

    return $this->t('@device (@width)', [
      '@device' => $device,
      '@width' => $value . $unit,
    ]);
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
