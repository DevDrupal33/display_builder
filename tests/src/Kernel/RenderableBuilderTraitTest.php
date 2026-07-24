<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\RenderableBuilderTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the RenderableBuilderTrait render-array contracts.
 *
 * The trait is mixed into every island, so the attributes it stamps are the
 * shared vocabulary of the drag-and-drop JavaScript, the contextual menu and
 * the e2e selectors. It has no service dependencies, so it is driven through a
 * tiny anonymous host that exposes its protected methods.
 *
 * @internal
 */
#[CoversClass(RenderableBuilderTrait::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class RenderableBuilderTraitTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * A host object exposing the trait's protected methods.
   */
  private object $host;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder']);

    $this->host = new class() {

      use RenderableBuilderTrait;

      /**
       * Expose buildButton().
       */
      public function button(
        string $label,
        ?string $action,
        ?string $icon = NULL,
        ?string $tooltip = NULL,
        ?array $keyboard = NULL,
      ): array {
        return $this->buildButton($label, $action, $icon, $tooltip, $keyboard);
      }

      /**
       * Expose buildPlaceholder().
       */
      public function placeholder(string $label, string $title = '', array $vals = [], ?string $keywords = NULL): array {
        return $this->buildPlaceholder($label, $title, $vals, $keywords);
      }

      /**
       * Expose buildMenuDivider().
       */
      public function divider(): array {
        return $this->buildMenuDivider();
      }

    };
  }

  /**
   * Test a button exposes its action as both hook attributes.
   *
   * Attributes data-island-action and data-testid are both derived from the
   * action, and both are used by tests - they must not drift apart.
   */
  public function testButtonExposesItsActionTwice(): void {
    $build = $this->host->button('Undo', 'undo', 'arrow-left');

    self::assertSame('undo', $build['#attributes']['data-island-action']);
    self::assertSame('undo', $build['#attributes']['data-testid']);
  }

  /**
   * Test a button without an action stamps no hooks at all.
   *
   * This is the HighlightToggle case: it passes a NULL action, which is why
   * [data-island-action="highlight"] never existed and the e2e tests had to
   * select the floating island wrapper instead.
   */
  public function testButtonWithoutActionHasNoHooks(): void {
    $build = $this->host->button('', NULL, 'border');

    self::assertArrayNotHasKey('data-island-action', $build['#attributes'] ?? []);
    self::assertArrayNotHasKey('data-testid', $build['#attributes'] ?? []);
  }

  /**
   * Test the keyboard shortcut attributes are mirrored to ARIA.
   */
  public function testButtonKeyboardShortcutIsAnnounced(): void {
    $build = $this->host->button('Expand', 'expand', NULL, NULL, ['E' => 'Toggle expand']);

    self::assertSame('E', $build['#attributes']['data-keyboard-key']);
    self::assertSame('E', $build['#attributes']['aria-keyshortcuts']);
    self::assertSame('Toggle expand', $build['#attributes']['data-keyboard-help']);
  }

  /**
   * Test the placeholder test ID prefers the component ID.
   *
   * The colon of a component ID is not valid in the attribute selectors the
   * e2e tests use, so it becomes a dash.
   */
  public function testPlaceholderTestIdPrefersComponentId(): void {
    $build = $this->host->placeholder('Test simple', '', [
      'source_id' => 'component',
      'source' => ['component' => ['component_id' => 'display_builder_test:test_1']],
    ]);

    self::assertSame('placeholder-display_builder_test-test_1', $build['#attributes']['data-testid']);
  }

  /**
   * Test the placeholder test ID falls back to the plugin ID, then source ID.
   */
  public function testPlaceholderTestIdFallsBack(): void {
    $from_plugin = $this->host->placeholder('A block', '', [
      'source_id' => 'block',
      'source' => ['plugin_id' => 'system_messages_block'],
    ]);
    self::assertSame('placeholder-system_messages_block', $from_plugin['#attributes']['data-testid']);

    // No component or plugin: the source ID is used. This is the one the
    // library drag helpers select on, e.g. placeholder-textfield.
    $from_source = $this->host->placeholder('Textfield', '', ['source_id' => 'textfield']);
    self::assertSame('placeholder-textfield', $from_source['#attributes']['data-testid']);
    self::assertContains('db-placeholder-textfield', $from_source['#attributes']['class']);
  }

  /**
   * Test placeholder keywords are normalized for searching.
   */
  public function testPlaceholderKeywordsAreNormalized(): void {
    $build = $this->host->placeholder('Textfield', '', ['source_id' => 'textfield'], '  TextField One-Line  ');

    self::assertSame('textfield one-line', $build['#attributes']['data-keywords']);
  }

  /**
   * Test a global error is appended out-of-band to the builder toast stack.
   *
   * The swap style must stay an append: an outerHTML swap would replace the
   * stack itself, so a burst of errors would only ever show the last one.
   * A non-global error is rendered in place and must not be swapped at all.
   */
  public function testGlobalErrorIsAppendedToToastStack(): void {
    $global = $this->host->buildError('instance_1', 'Boom', TRUE);
    self::assertSame('beforeend:#message-instance_1', $global['#attributes']['data-hx-swap-oob']);
    self::assertSame('db-message', $global['message']['#attributes']['class']);

    $local = $this->host->buildError('instance_1', 'Boom');
    self::assertArrayNotHasKey('id', $local['#props']);
    self::assertArrayNotHasKey('data-hx-swap-oob', $local['#attributes'] ?? []);
  }

  /**
   * Test an error can carry an auto-dismiss duration.
   */
  public function testErrorDurationIsOptional(): void {
    self::assertArrayNotHasKey('duration', $this->host->buildError('instance_1', 'Boom')['#props']);
    self::assertSame(5000, $this->host->buildError('instance_1', 'Boom', FALSE, 5000)['#props']['duration']);
  }

  /**
   * Test the menu divider is a divider and nothing else.
   */
  public function testMenuDividerIsInert(): void {
    $build = $this->host->divider();

    self::assertNotEmpty($build);
    self::assertArrayNotHasKey('data-island-action', $build['#attributes'] ?? []);
  }

}
