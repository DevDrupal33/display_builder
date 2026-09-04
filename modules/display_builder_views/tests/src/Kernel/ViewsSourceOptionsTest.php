<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_views\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\display_builder_views\Plugin\ViewsBuilderSourceTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ui_patterns\Plugin\Context\RequirementsContext;
use Drupal\ui_patterns\SourceInterface;
use Drupal\ui_patterns\SourcePluginManager;
use Drupal\views\Entity\View;
use Drupal\views_ui\ViewUI;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that view sources expose and save the view's own options forms.
 *
 * @internal
 */
#[CoversTrait(ViewsBuilderSourceTrait::class)]
#[Group('display_builder')]
#[Group('display_builder_views')]
final class ViewsSourceOptionsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * The view used by every test.
   *
   * Its "page_1" display inherits every section from "default", which is what
   * makes it worth testing against.
   */
  private const VIEW_ID = 'test_db_view';

  /**
   * The display the sources are configured from.
   */
  private const DISPLAY_ID = 'page_1';

  /**
   * The UI Patterns source plugin manager.
   */
  protected SourcePluginManager $sourceManager;

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
    'display_builder',
    'display_builder_views',
    'display_builder_views_test',
    'ui_patterns',
    'ui_patterns_field',
    'ui_patterns_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('view');
    $this->installConfig([
      'system',
      'field',
      'filter',
      'node',
      'views',
      'display_builder',
      'display_builder_views',
      'ui_patterns',
      'display_builder_views_test',
    ]);
    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');
  }

  /**
   * The pager options form is exposed and saved back into the view.
   */
  public function testPagerOptions(): void {
    $source = $this->getSource('view_pager');
    $form_state = new FormState();
    $form = $source->settingsForm([], $form_state);

    self::assertArrayHasKey('pager_options', $form);
    self::assertTrue($form['pager_options']['#tree']);
    self::assertArrayHasKey('items_per_page', $form['pager_options']);
    self::assertSame(10, $form['pager_options']['items_per_page']['#default_value']);
    self::assertArrayNotHasKey('notice', $form);

    $options = $this->displayOptions('default')['pager']['options'];
    $options['items_per_page'] = 3;
    $data = $this->submit($source, 'pager_options', $options);

    self::assertSame([], $data, 'The views options do not reach the source tree.');
    self::assertSame(3, $this->displayOptions('default')['pager']['options']['items_per_page']);
    self::assertArrayNotHasKey(
      'pager',
      $this->displayOptions(self::DISPLAY_ID),
      'An inherited section is written to the default display, not overridden.',
    );
  }

  /**
   * The views plugin validates its own options before anything is saved.
   */
  public function testPagerOptionsValidation(): void {
    $source = $this->getSource('view_pager');
    $form_state = new FormState();
    $form = $source->settingsForm([], $form_state);
    $options = $this->displayOptions('default')['pager']['options'];

    $form_state->setValues(['pager_options' => $options]);
    $source->validateFormData($form, $form_state);
    self::assertSame([], $form_state->getErrors(), 'The stored options validate.');

    $options['expose']['items_per_page_options'] = 'ten, twenty';
    $form_state->setValues(['pager_options' => $options]);
    $source->validateFormData($form, $form_state);

    self::assertArrayHasKey(
      'pager_options][expose][items_per_page_options',
      $form_state->getErrors(),
    );
    self::assertSame(10, $this->displayOptions('default')['pager']['options']['items_per_page']);
  }

  /**
   * The style options form is exposed and saved back into the view.
   */
  public function testStyleOptions(): void {
    $source = $this->getSource('view_rows');
    $form = $source->settingsForm([], new FormState());

    self::assertArrayHasKey('style_options', $form);
    self::assertArrayNotHasKey('notice', $form);

    $options = $this->displayOptions('default')['style']['options'] ?? [];
    $options['row_class'] = 'db-test-row';
    $data = $this->submit($source, 'style_options', $options);

    self::assertSame([], $data);
    self::assertSame('db-test-row', $this->displayOptions('default')['style']['options']['row_class']);
  }

  /**
   * The exposed form options form is exposed and saved back into the view.
   */
  public function testExposedFormOptions(): void {
    $source = $this->getSource('view_exposed');
    $form = $source->settingsForm([], new FormState());

    self::assertArrayHasKey('exposed_form_options', $form);
    self::assertArrayHasKey('submit_button', $form['exposed_form_options']);

    $options = $this->displayOptions('default')['exposed_form']['options'];
    $options['submit_button'] = 'Go';
    $data = $this->submit($source, 'exposed_form_options', $options);

    self::assertSame([], $data);
    self::assertSame('Go', $this->displayOptions('default')['exposed_form']['options']['submit_button']);
  }

  /**
   * The more link is three display options, not a views plugin.
   */
  public function testMoreOptions(): void {
    $source = $this->getSource('view_more');
    $form = $source->settingsForm([], new FormState());

    self::assertArrayHasKey('more_options', $form);
    self::assertFalse($form['more_options']['use_more']['#default_value']);

    $data = $this->submit($source, 'more_options', [
      'use_more' => 1,
      'use_more_always' => 0,
      'use_more_text' => 'See all',
    ]);

    self::assertSame([], $data);
    $options = $this->displayOptions('default');
    self::assertTrue($options['use_more']);
    self::assertSame('See all', $options['use_more_text']);
  }

  /**
   * A view held by the Views UI is refused, not silently published.
   */
  public function testViewOpenInViewsUiIsRefused(): void {
    $this->enableModules(['views_ui']);
    $this->container->get('current_user')->setAccount($this->createUser());

    $view_ui = new ViewUI(View::load(self::VIEW_ID));
    $this->container->get('tempstore.shared')->get('views')->set(self::VIEW_ID, $view_ui);

    $source = $this->getSource('view_pager');
    $form_state = new FormState();
    $form = $source->settingsForm([], $form_state);
    $options = $this->displayOptions('default')['pager']['options'];
    $options['items_per_page'] = 3;
    $form_state->setValues(['pager_options' => $options]);

    $source->validateFormData($form, $form_state);

    $errors = $form_state->getErrors();

    self::assertArrayHasKey('pager_options', $errors);
    self::assertStringContainsString('Views UI', (string) $errors['pager_options']);
    self::assertSame(10, $this->displayOptions('default')['pager']['options']['items_per_page']);
  }

  /**
   * Outside a builder, the Views UI dialog, the source form stays bare.
   */
  public function testOutsideBuilderTheFormIsBare(): void {
    $contexts = [
      'ui_patterns_views:view_entity' => EntityContext::fromEntity(View::load(self::VIEW_ID)),
      'ui_patterns_views:display' => new Context(ContextDefinition::create('string'), self::DISPLAY_ID),
    ];
    $contexts = RequirementsContext::addToContext(['views:display'], $contexts);
    foreach (['view_pager', 'view_more', 'view_feed_icons'] as $source_id) {
      $source = $this->sourceManager->getSource('slot', [], ['source_id' => $source_id, 'source' => []], $contexts);
      $form = $source->settingsForm([], new FormState());
      self::assertArrayNotHasKey('pager_options', $form, $source_id);
      self::assertArrayNotHasKey('more_options', $form, $source_id);
      self::assertArrayNotHasKey('notice', $form, $source_id);
    }
  }

  /**
   * A source computed from other displays stays non-configurable.
   */
  public function testNonConfigurableSource(): void {
    $form = $this->getSource('view_feed_icons')->settingsForm([], new FormState());

    self::assertArrayHasKey('notice', $form);
    self::assertStringNotContainsString('<a href', (string) $form['notice']['#value']);

    // With the Views UI around, the notice says where to go instead.
    $this->enableModules(['views_ui']);
    $this->setUpCurrentUser(['uid' => 1]);
    $this->sourceManager = $this->container->get('plugin.manager.ui_patterns_source');
    $form = $this->getSource('view_feed_icons')->settingsForm([], new FormState());

    self::assertStringContainsString(
      '/admin/structure/views/view/' . self::VIEW_ID . '/edit/' . self::DISPLAY_ID,
      (string) $form['notice']['#value'],
    );
  }

  /**
   * Instantiates a view source plugin with the contexts the builder provides.
   *
   * @param string $source_id
   *   The source plugin id.
   * @param \Drupal\views\Entity\View|null $view
   *   The view to work on, the shared fixture by default.
   *
   * @return \Drupal\ui_patterns\SourceInterface
   *   The source plugin.
   */
  private function getSource(string $source_id, ?View $view = NULL): SourceInterface {
    $view ??= View::load(self::VIEW_ID);
    $contexts = [
      'ui_patterns_views:view_entity' => EntityContext::fromEntity($view),
      'ui_patterns_views:display' => new Context(ContextDefinition::create('string'), self::DISPLAY_ID),
    ];
    $contexts = RequirementsContext::addToContext(['views:display', 'display_builder'], $contexts);
    $source = $this->sourceManager->getSource(
      'slot',
      [],
      ['source_id' => $source_id, 'source' => []],
      $contexts,
    );
    self::assertInstanceOf(SourceInterface::class, $source);

    return $source;
  }

  /**
   * Submits values for one options subtree through the source.
   *
   * @param \Drupal\ui_patterns\SourceInterface $source
   *   The source plugin.
   * @param string $key
   *   The options subtree key.
   * @param array $values
   *   The submitted values.
   *
   * @return array
   *   What the source leaves for the source tree.
   */
  private function submit(SourceInterface $source, string $key, array $values): array {
    $form_state = new FormState();
    $form_state->setValues([$key => $values]);

    return $source->processFormData([$key => $values], $form_state);
  }

  /**
   * Reads the stored display options of a display, from a fresh entity.
   *
   * @param string $display_id
   *   The display id.
   *
   * @return array
   *   The display options.
   */
  private function displayOptions(string $display_id): array {
    $this->container->get('entity_type.manager')->getStorage('view')->resetCache();
    $view = View::load(self::VIEW_ID);

    return $view->get('display')[$display_id]['display_options'] ?? [];
  }

}
