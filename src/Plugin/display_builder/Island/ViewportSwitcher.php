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
  label: new TranslatableMarkup('Responsive width and Zoom'),
  description: new TranslatableMarkup('Switch the preview between breakpoint widths to check responsive behavior.'),
  type: IslandType::Floating,
  modules: ['breakpoint'],
  attach_to: ['preview'],
  pane_header: TRUE,
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $configuration = $this->getConfiguration();

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

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function build(InstanceInterface $builder, array $data = [], array $options = []): array {
    $groups = [];

    foreach ($this->getDefinitions() as $definition) {
      if (isset($definition['group'])) {
        $groups[$definition['group']] = $definition['group'];
      }
    }

    $points = [];

    // The default: no fixed width, so the preview fills the available pane. The
    // empty value clears any applied width. Active on first render.
    $buttons = [
      $this->buildViewportButton('', $this->t('Responsive (fills the available width)'), 'aspect-ratio', TRUE),
    ];

    foreach ($this->getViewports($groups) as $viewport) {
      $points[$viewport['id']] = $viewport['width'];
      $buttons[] = $this->buildViewportButton($viewport['id'], $viewport['label'], $viewport['icon'], FALSE);
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        // Sits in the Preview pane's header bar (pane_header), which provides
        // the surface, so no db-background of its own.
        'class' => ['db-viewport-controls'],
      ],
      'viewport' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['switch-viewport'],
          'data-island-action' => 'viewport',
          'data-points' => \json_encode($points),
        ],
        'buttons' => $buttons,
      ],
      'zoom' => $this->buildZoomControl(),
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
          'label' => (string) $point->getLabel(),
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
        'label' => \sprintf('%s (%s %s)', $candidate['label'], $candidate['value'], $candidate['unit']),
        'icon' => $this->getViewportIcon($candidate['value'], $candidate['unit']),
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
   * Pick a device icon for a viewport width.
   *
   * Mirrors the width tiers of getViewportLabel() so icon and name agree.
   *
   * @param int $value
   *   The numeric width.
   * @param string $unit
   *   The CSS length unit (px, em, ...).
   *
   * @return string
   *   A Bootstrap icon name.
   */
  protected function getViewportIcon(int $value, string $unit): string {
    if ($unit !== 'px') {
      return 'aspect-ratio';
    }

    return match (TRUE) {
      $value < 641 => 'phone',
      $value < 1024 => 'tablet',
      $value < 1400 => 'laptop',
      default => 'display',
    };
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

  /**
   * Build the zoom control: scale the Preview render to 25/50/75/100%.
   *
   * A companion to the width switcher: pick a device width, then zoom out to
   * see it whole in a narrow split. The select acts on the iframe only (@see
   * assets/js/viewport_switcher.js), 100% is the initial state.
   *
   * @return array
   *   The zoom control render array.
   */
  private function buildZoomControl(): array {
    $levels = [
      '0.25' => '25%',
      '0.5' => '50%',
      '0.75' => '75%',
      '1' => '100%',
    ];

    $options = [];

    foreach ($levels as $value => $label) {
      $options[] = [
        '#type' => 'html_tag',
        '#tag' => 'sl-option',
        '#value' => $label,
        '#attributes' => ['value' => $value],
      ];
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['switch-zoom'],
      ],
      'select' => [
        '#type' => 'html_tag',
        '#tag' => 'sl-select',
        '#attributes' => [
          'class' => ['switch-zoom-select'],
          'data-island-action' => 'zoom',
          // The initially selected level; 100% is full size.
          'value' => '1',
          'size' => 'small',
          // Real accessible name for the control (a placeholder is not a name);
          // hidden visually in the header bar.
          // @see assets/css/viewport_switcher.css.
          'label' => $this->t('Zoom the preview'),
        ],
        'options' => $options,
      ],
    ];
  }

  /**
   * Build one device button of the viewport switcher's segmented control.
   *
   * Icon-only: the hover title carries the human name and exact width, and -
   * because no `tooltip` prop is passed - it doubles as the button's accessible
   * name (@see components/shoelace/button/button.twig). A plain title rather
   * than a Shoelace <sl-tooltip> keeps it robust wherever the switcher renders.
   *
   * @param string $value
   *   The breakpoint ID to apply, or '' for the responsive (no-width) default.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The hover and accessible label, e.g. "Mobile (575px)".
   * @param string $icon
   *   The device icon name.
   * @param bool $active
   *   Whether this button is the initially selected one.
   *
   * @return array
   *   The button render array.
   */
  private function buildViewportButton(string $value, string|TranslatableMarkup $title, string $icon, bool $active): array {
    $button = $this->buildButton('', NULL, $icon);
    $button['#attributes']['class'] = ['switch-viewport-btn'];
    $button['#attributes']['size'] = 'small';
    $button['#attributes']['title'] = $title;
    $button['#attributes']['data-viewport-value'] = $value;

    if ($active) {
      $button['#attributes']['variant'] = 'primary';
    }

    return $button;
  }

}
