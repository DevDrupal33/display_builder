<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\Core\GeneratedUrl;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\display_builder\RenderableBuilderTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the RenderableBuilderTrait builders that need no Drupal kernel.
 *
 * The trait is pure render-array assembly: its only collaborators are the
 * container-free \Drupal\Core\Htmx\Htmx value object, a Url it merely
 * stringifies, and a RendererInterface passed in as a parameter. All three
 * mock, so these contracts are pinned as unit tests rather than kernel ones.
 *
 * @see \Drupal\Tests\display_builder\Kernel\RenderableBuilderTraitTest
 *
 * @internal
 */
#[CoversClass(RenderableBuilderTrait::class)]
#[Group('display_builder')]
final class RenderableBuilderTraitUnitTest extends UnitTestCase {

  /**
   * A host object exposing the trait's protected methods.
   */
  private object $host;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->host = new class() {

      use RenderableBuilderTrait {
        buildPlaceholder as public;
        buildPlaceholderButton as public;
        buildPlaceholderList as public;
        buildPlaceholderListWithPreview as public;
        buildPlaceholderCard as public;
        buildPlaceholderCardWithPreview as public;
        buildMenuItem as public;
        buildDraggables as public;
        buildTabs as public;
        buildInput as public;
        wrapContent as public;
        isRenderEmptyOrFailing as public;
      }

    };
  }

  /**
   * Test the button and list placeholders carry a draggable title.
   *
   * The variant drives the rendering, but data-node-title is the load-bearing
   * one: the drag-and-drop JavaScript reads it to identify the node in flight
   * and to title the settings drawer once dropped. A placeholder without it
   * drags as an untitled ghost.
   */
  public function testPlaceholderVariantsCarryDraggableTitle(): void {
    $button = $this->host->buildPlaceholderButton('Add block', ['source_id' => 'block']);
    self::assertSame('button', $button['#props']['variant']);
    self::assertSame('Add block', $button['#attributes']['data-node-title']);

    $list = $this->host->buildPlaceholderList('Textfield', ['source_id' => 'textfield']);
    self::assertSame('list', $list['#props']['variant']);
    self::assertSame('Textfield', $list['#attributes']['data-node-title']);
  }

  /**
   * Test a translated label is flattened for the title attribute.
   *
   * Islands pass TranslatableMarkup, but an attribute value has to be a
   * string - leaving the object in place yields "Object" once rendered.
   */
  public function testPlaceholderTitleFlattensTranslatedLabels(): void {
    $label = new TranslatableMarkup('Add block', [], [], $this->getStringTranslationStub());

    $build = $this->host->buildPlaceholderButton($label, []);

    self::assertSame('Add block', $build['#attributes']['data-node-title']);
  }

  /**
   * Test the test ID falls back to the first val, and the title is optional.
   *
   * The last-resort fallback is what gives presets and other single-val
   * placeholders a selector at all; without it they would be addressable only
   * by label, which is translated.
   */
  public function testPlaceholderFallsBackToTheFirstValAndTitleIsOptional(): void {
    $build = $this->host->buildPlaceholder('My preset', 'Insert this preset', ['preset_id' => 'my_preset']);

    self::assertSame('placeholder-my_preset', $build['#attributes']['data-testid']);
    self::assertSame('Insert this preset', $build['#attributes']['title']);

    self::assertArrayNotHasKey('title', $this->host->buildPlaceholder('My preset')['#attributes'] ?? []);
  }

  /**
   * Test list placeholders normalize their search keywords.
   */
  public function testPlaceholderListNormalizesKeywords(): void {
    $build = $this->host->buildPlaceholderList('Textfield', [], '  TextField One-Line  ');

    self::assertSame('textfield one-line', $build['#attributes']['data-keywords']);
  }

  /**
   * Test the hover preview is wired to the builder's own preview pane.
   *
   * The target is derived from the builder ID, so a mistyped selector would
   * silently paint one builder's preview into another's pane.
   */
  public function testListPreviewTargetsItsOwnBuilderPane(): void {
    $build = $this->host->buildPlaceholderListWithPreview(
      'instance_1',
      'Test simple',
      ['source_id' => 'component'],
      $this->url('/db/preview/test_1'),
    );

    self::assertSame('/db/preview/test_1', (string) $build['#attributes']['data-hx-get']);
    self::assertSame('#preview-instance_1', (string) $build['#attributes']['data-hx-target']);
    self::assertSame('mouseenter delay:250ms, focusin delay:250ms', (string) $build['#attributes']['data-hx-trigger']);
    self::assertTrue($build['#attributes']['data-preview']);
  }

  /**
   * Test keyboard users reach the preview too.
   *
   * Placeholders are focusable, so the preview must not be pointer-only.
   * focusin, not focus: only the former bubbles to the delegated listener in
   * js/preview.js that decides which trigger the response belongs to, so a
   * focus-triggered request would otherwise be cancelled as stale.
   */
  public function testPreviewIsReachableByKeyboard(): void {
    $build = $this->host->buildPlaceholderListWithPreview(
      'instance_1',
      'Test simple',
      ['source_id' => 'component'],
      $this->url('/db/preview/test_1'),
    );

    self::assertStringContainsString('focusin', (string) $build['#attributes']['data-hx-trigger']);
  }

  /**
   * Test the popup is shown by JavaScript, never by inline handlers.
   *
   * Showing an empty popup on mouseenter and filling it afterwards makes
   * Floating UI measure the wrong box, so js/preview.js owns show/hide and
   * acts on htmx:afterSwap instead.
   *
   * @see components/display_builder/js/preview.js
   */
  public function testPreviewCarriesNoInlineHandlers(): void {
    $build = $this->host->buildPlaceholderListWithPreview(
      'instance_1',
      'Test simple',
      ['source_id' => 'component'],
      $this->url('/db/preview/test_1'),
    );

    foreach (\array_keys($build['#attributes']) as $attribute) {
      self::assertStringStartsNotWith('data-hx-on', (string) $attribute);
    }
  }

  /**
   * Test entity sources opt out of the hover preview entirely.
   *
   * A sample entity has no generated value, so previewing one renders an empty
   * or misleading pane. Both source IDs must stay excluded - and excluded
   * means no HTMX wiring at all, not merely an unused URL.
   */
  public function testEntitySourcesGetNoPreview(): void {
    foreach (['entity_field', 'entity_reference'] as $source_id) {
      $build = $this->host->buildPlaceholderListWithPreview(
        'instance_1',
        'Body',
        ['source_id' => $source_id],
        $this->url('/db/preview/body'),
      );

      self::assertArrayNotHasKey('data-hx-get', $build['#attributes'], $source_id);
      self::assertArrayNotHasKey('data-hx-target', $build['#attributes'], $source_id);
    }
  }

  /**
   * Test the card thumbnail is optional and root-relative.
   */
  public function testCardThumbnailIsOptional(): void {
    $without = $this->host->buildPlaceholderCard('Card', [], NULL, NULL);
    self::assertArrayNotHasKey('image', $without['#slots']);

    $with = $this->host->buildPlaceholderCard('Card', [], NULL, 'themes/custom/preview.png');
    self::assertSame('/themes/custom/preview.png', $with['#slots']['image']['#attributes']['src']);
  }

  /**
   * Test card placeholders normalize their search keywords.
   *
   * The card library is searchable like the list one, so the two must
   * normalize identically or the same query would match different sets.
   */
  public function testCardNormalizesKeywords(): void {
    $build = $this->host->buildPlaceholderCard('Card', [], '  Card Media  ');

    self::assertSame('card media', $build['#attributes']['data-keywords']);
  }

  /**
   * Test the card variant is wired exactly like the list one.
   *
   * The mosaic library used to only carry a URL prop that nothing read, so
   * its previews never fired. Both variants must go through the same wiring.
   */
  public function testCardWithPreviewIsWiredLikeTheList(): void {
    $build = $this->host->buildPlaceholderCardWithPreview(
      'instance_1',
      'Card',
      [],
      $this->url('/db/preview/test_1'),
    );

    self::assertSame('/db/preview/test_1', (string) $build['#attributes']['data-hx-get']);
    self::assertSame('#preview-instance_1', (string) $build['#attributes']['data-hx-target']);
    self::assertTrue($build['#attributes']['data-preview']);
  }

  /**
   * Test every menu item is tagged for the contextual menu JavaScript.
   *
   * Contextual_menu.js maps clicks by this attribute, so an item missing it is
   * rendered but inert.
   */
  public function testMenuItemIsTaggedForJavaScript(): void {
    $build = $this->host->buildMenuItem('Duplicate', 'duplicate', 'copy');

    self::assertTrue($build['#attributes']['data-contextual-menu']);
    self::assertSame('duplicate', $build['#props']['value']);
    self::assertSame('prefix', $build['#props']['icon_position']);
    self::assertFalse($build['#props']['disabled']);
  }

  /**
   * Test a submenu slot is added only when there are children.
   *
   * An empty submenu slot renders the disclosure affordance of a menu that
   * opens onto nothing.
   */
  public function testMenuItemSubmenuIsOmittedWhenEmpty(): void {
    $flat = $this->host->buildMenuItem('Styles', 'styles');
    self::assertArrayNotHasKey('#slots', $flat);

    $nested = $this->host->buildMenuItem('Styles', 'styles', NULL, 'prefix', FALSE, [
      $this->host->buildMenuItem('Bold', 'bold'),
    ]);
    self::assertCount(1, $nested['#slots']['submenu']);
  }

  /**
   * Test draggables expose the builder ID required by their JavaScript.
   *
   * Draggables.js scopes its drag sources with this attribute; without it a
   * library panel initializes against no builder.
   */
  public function testDraggablesExposeTheBuilderId(): void {
    $build = $this->host->buildDraggables('instance_1', ['some' => 'placeholder']);

    self::assertSame('instance_1', $build['#attributes']['data-db-id']);
    self::assertArrayNotHasKey('variant', $build['#props'] ?? []);

    $variant = $this->host->buildDraggables('instance_1', [], 'card');
    self::assertSame('card', $variant['#props']['variant']);
  }

  /**
   * Test the tabs ID is optional.
   *
   * The ID keys the active-tab local storage entry, so an empty one must not
   * be stamped - all ID-less tab sets would then share a single entry.
   */
  public function testTabsIdIsOptional(): void {
    $tabs = [['title' => 'Testing']];

    $identified = $this->host->buildTabs('sidebar', $tabs, TRUE);
    self::assertSame('sidebar', $identified['#props']['id']);
    self::assertTrue($identified['#props']['contextual']);

    $anonymous = $this->host->buildTabs('', $tabs);
    self::assertArrayNotHasKey('id', $anonymous['#props']);
    self::assertFalse($anonymous['#props']['contextual']);
  }

  /**
   * Test the input keeps its required props and drops the unset optional ones.
   *
   * Passing a NULL prop through to an SDC is a validation error, hence the
   * omissions rather than explicit nulls.
   */
  public function testInputOmitsUnsetOptionalProps(): void {
    $minimal = $this->host->buildInput('search', 'Search', 'search');
    self::assertSame(['label' => 'Search', 'variant' => 'search', 'size' => 'medium', 'id' => 'search'], $minimal['#props']);

    $full = $this->host->buildInput('search', 'Search', 'search', 'small', 'off', 'Filter...', TRUE, 'magnifier');
    self::assertSame('small', $full['#props']['size']);
    self::assertSame('off', $full['#props']['autocomplete']);
    self::assertSame('Filter...', $full['#props']['placeholder']);
    self::assertTrue($full['#props']['clearable']);
    self::assertSame('magnifier', $full['#props']['icon']);
  }

  /**
   * Test wrapped content is nested rather than replaced.
   */
  public function testWrapContentNestsAndIdentifies(): void {
    $content = ['#markup' => 'Panel'];

    $wrapped = $this->host->wrapContent($content, 'panel-1');
    self::assertSame('div', $wrapped['#tag']);
    self::assertSame($content, $wrapped['content']);
    self::assertSame('panel-1', $wrapped['#attributes']['id']);

    self::assertArrayNotHasKey('#attributes', $this->host->wrapContent($content));
  }

  /**
   * Test a renderable that throws is reported as empty.
   *
   * This is the whole point of the check: a bad #lazy_builder must be caught
   * here, synchronously, rather than surviving into the caller's render array
   * to fatal later inside BigPipe, well outside any try/catch.
   */
  public function testFailingRenderableIsTreatedAsEmpty(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderInIsolation')->willThrowException(new \RuntimeException('No entity ID.'));

    self::assertTrue($this->host->isRenderEmptyOrFailing($renderer, ['#markup' => 'ignored']));
  }

  /**
   * Test whitespace-only output counts as empty, and real markup does not.
   */
  public function testWhitespaceOnlyRenderableIsEmpty(): void {
    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderInIsolation')->willReturnOnConsecutiveCalls("  \n\t ", '<p>Body</p>');

    self::assertTrue($this->host->isRenderEmptyOrFailing($renderer, []));
    self::assertFalse($this->host->isRenderEmptyOrFailing($renderer, []));
  }

  /**
   * Builds a Url to string to the given path.
   */
  private function url(string $path): Url {
    $generated = (new GeneratedUrl())->setGeneratedUrl($path);
    $url = $this->createMock(Url::class);
    $url->method('toString')->with(TRUE)->willReturn($generated);

    return $url;
  }

}
