<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\display_builder_views\Plugin\UiPatterns\Source\ViewRowsSource;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\display_builder_views_test\Hook\CountViewExecutions;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourceInterface;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the two states of every view area source.
 *
 * A view display's areas come from an executed view. In a builder there is no
 * page and so no view to run, and that is the state these sources used to get
 * wrong in both directions: dereferencing a NULL view, or returning nothing at
 * all and leaving a node the user cannot select behind.
 *
 * @internal
 */
#[CoversTrait(ViewsBuilderSourceTrait::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
#[RunTestsInSeparateProcesses]
final class ViewsSourceRenderTest extends KernelTestBase {

  /**
   * The fixture view and the display carrying the profile.
   */
  private const VIEW_ID = 'test_db_view_render';

  private const DISPLAY_ID = 'page_1';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'views',
    'views_ui',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_views',
    'display_builder',
    'display_builder_views',
    'display_builder_test',
    'display_builder_views_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'field', 'filter', 'node', 'views', 'display_builder', 'display_builder_views', 'display_builder_test', 'display_builder_views_test']);

    // ::getExtender() reads the display's extenders, and views only builds
    // those it is told about. display_builder_views_install() does this on a
    // real site; a kernel test installs config without running it.
    $this->config('views.settings')->set('display_extenders', ['display_builder'])->save();
  }

  /**
   * With no view to run, a slot source names itself instead of rendering.
   *
   * @param string $source_id
   *   The source plugin ID.
   * @param string $label
   *   The name the placeholder should carry.
   * @param string $size
   *   The room the placeholder should reserve.
   */
  #[DataProvider('providerSlotSources')]
  public function testUnresolvedSlotSourceGetsNamedPlaceholder(string $source_id, string $label, string $size): void {
    $build = $this->source($source_id)->getValue();

    self::assertIsArray($build);
    self::assertSame('display_builder:placeholder', $build['#component'] ?? NULL);
    self::assertSame('region', $build['#props']['variant'] ?? NULL);
    self::assertContains('db-placeholder-region--' . $size, $build['#attributes']['class'] ?? []);
    self::assertSame($label, (string) $build['#slots']['content']['title']['#value']);
    self::assertStringContainsString('replaced by the page value', (string) $build['#slots']['content']['help']['#value']);
  }

  /**
   * Cases for ::testUnresolvedSlotSourceGetsNamedPlaceholder().
   *
   * @return array
   *   Test cases, keyed by source ID.
   */
  public static function providerSlotSources(): array {
    return [
      'view_header' => ['view_header', '[View] Header', 'md'],
      'view_footer' => ['view_footer', '[View] Footer', 'md'],
      'view_exposed' => ['view_exposed', '[View] Exposed form', 'md'],
      'view_pager' => ['view_pager', '[View] Pager', 'md'],
      'view_more' => ['view_more', '[View] More', 'md'],
      'view_feed_icons' => ['view_feed_icons', '[View] Feed icons', 'md'],
      'view_attachment_before' => ['view_attachment_before', '[View] Attachment before', 'md'],
      'view_attachment_after' => ['view_attachment_after', '[View] Attachment after', 'md'],
      // The result area, not a strip.
      'view_rows' => ['view_rows', '[View] Rows', 'lg'],
    ];
  }

  /**
   * A string prop has nowhere to put a region, so it gets nothing.
   */
  public function testUnresolvedStringSourceGetsAnEmptyString(): void {
    self::assertSame('', $this->source('view_title', [], 'string')->getValue());
  }

  /**
   * With the view in context, the areas come from the view itself.
   */
  public function testResolvedSourcesRenderFromTheView(): void {
    $contexts = $this->viewContexts();

    self::assertSame(self::VIEW_ID, $this->source('view_title', $contexts, 'string')->getValue());

    $rows = $this->source('view_rows', $contexts)->getValue();
    self::assertIsArray($rows);
    self::assertNotSame('display_builder:placeholder', $rows['#component'] ?? NULL);
  }

  /**
   * In the Component style plugin the swapped view_rows behaves as upstream.
   *
   * The class swap is site-wide, so the style plugin gets the subclass too:
   * the raw rows, the field setting, and nothing from the builder.
   */
  public function testStylePluginGetsTheUpstreamRowsFromTheSwappedSource(): void {
    $view = Views::getView(self::VIEW_ID);
    $view->setDisplay(self::DISPLAY_ID);
    $view->execute();
    $raw_rows = [['#markup' => 'raw-a'], ['#markup' => 'raw-b']];
    $contexts = RequirementsContext::addToContext(['views:style'], [
      'ui_patterns_views:view' => new Context(new ContextDefinition('any'), $view),
      'ui_patterns_views:rows' => new Context(new ContextDefinition('any'), $raw_rows),
      'ui_patterns_views:view_entity' => EntityContext::fromEntity(View::load(self::VIEW_ID)),
    ]);
    $source = $this->source('view_rows', $contexts);
    self::assertInstanceOf(ViewRowsSource::class, $source);

    $rows = $source->getPropValue();
    self::assertSame('raw-a', $rows[0]['#markup'] ?? NULL);
    $form = $source->settingsForm([], new FormState());
    self::assertArrayHasKey('ui_patterns_views_field', $form);
    self::assertArrayNotHasKey('style_options', $form);
    self::assertArrayNotHasKey('notice', $form);
  }

  /**
   * One exposed filter renders as an exposed form.
   */
  public function testExposedFormWithOneFilterIsKept(): void {
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $this->exposeTheStatusFilter();
    $build = $this->source('view_exposed', $this->viewContexts())->getValue();

    self::assertIsArray($build);
    self::assertArrayHasKey('status', $build);
  }

  /**
   * The view runs once for the whole render, not once per source.
   */
  public function testTheViewRunsOnceForEverySource(): void {
    $contexts = $this->viewContexts();

    foreach (\array_keys(self::providerSlotSources()) as $source_id) {
      $this->source($source_id, $contexts)->getValue();
    }
    $counts = $this->container->get('state')->get(CountViewExecutions::STATE_KEY, []);

    self::assertSame(
      [self::VIEW_ID . ':' . self::DISPLAY_ID => 1],
      $counts,
      'Nine sources ran the view once between them.',
    );
  }

  /**
   * Exposes the fixture's one filter, leaving it the only exposed thing.
   */
  private function exposeTheStatusFilter(): void {
    $view = View::load(self::VIEW_ID);
    $display = $view->get('display');
    $filter = &$display['default']['display_options']['filters']['status'];
    $filter['exposed'] = TRUE;
    $filter['expose'] = [
      'operator_id' => 'status_op',
      'label' => 'Published',
      'identifier' => 'status',
      'operator' => 'status_op',
      'multiple' => FALSE,
      'remember' => FALSE,
      'required' => FALSE,
      'description' => '',
      'use_operator' => FALSE,
      'remember_roles' => [],
    ];
    $view->set('display', $display);
    $view->save();
  }

  /**
   * The contexts a view display hands its sources.
   *
   * @return \Drupal\Core\Plugin\Context\ContextInterface[]
   *   The contexts.
   */
  private function viewContexts(): array {
    /** @var \Drupal\display_builder\DisplayBuildableInterface $buildable */
    $buildable = $this->container->get('plugin.manager.display_buildable')->createInstance('view_display', [
      'view_id' => self::VIEW_ID,
      'view_display' => self::DISPLAY_ID,
    ]);

    return $buildable->getRuntimeContexts([]);
  }

  /**
   * Builds a source plugin as a slot in a component would.
   *
   * @param string $source_id
   *   The source plugin ID.
   * @param array $contexts
   *   (Optional) The contexts, empty as in a builder with no view behind it.
   * @param string $prop_type
   *   (Optional) The prop type the source fills. Everything here is a slot
   *   except the title.
   *
   * @return \Drupal\ui_patterns\SourceInterface
   *   The source plugin.
   */
  private function source(string $source_id, array $contexts = [], string $prop_type = 'slot'): SourceInterface {
    $definition = [
      'ui_patterns' => [
        'type_definition' => $this->container->get('plugin.manager.ui_patterns_prop_type')->createInstance($prop_type),
      ],
    ];

    return $this->container->get('plugin.manager.ui_patterns_source')->getSource(
      'content',
      $definition,
      ['source_id' => $source_id],
      $contexts,
    );
  }

}
