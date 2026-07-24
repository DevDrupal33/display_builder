<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Controller\ApiPreviewController;
use Drupal\display_builder\Entity\PatternPreset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the ApiPreviewController component-preview path.
 *
 * The controller powers the library hover popup. Without ui_patterns_library
 * it renders the component straight from its definition examples
 * (generateComponent) and appends the component's own description under it,
 * both wrapped in the db-preview__content / db-preview__description containers
 * the popup CSS pins. A component that cannot be resolved must still yield a
 * well-formed (empty) response rather than throw. The class had no coverage.
 *
 * @internal
 */
#[CoversClass(ApiPreviewController::class)]
#[Group('display_builder')]
final class ApiPreviewControllerTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    // Provides plugin.manager.component_story, autowired by the controller.
    'ui_patterns_library',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * The controller under test.
   */
  protected ApiPreviewController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('pattern_preset');
    $this->controller = $this->container->get('class_resolver')
      ->getInstanceFromDefinition(ApiPreviewController::class);
  }

  /**
   * A preset preview renders its stored source, with its description footer.
   */
  public function testPresetPreviewRendersSourceAndDescription(): void {
    PatternPreset::create([
      'id' => 'test_preset_preview',
      'label' => 'Test preset preview',
      'description' => 'A saved arrangement.',
      'sources' => [
        ['source_id' => 'textfield', 'source' => ['value' => 'from the preset']],
      ],
    ])->save();

    $response = $this->controller->getPresetPreview('test_preset_preview');
    $content = (string) $response->getContent();

    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('db-preview__content', $content);
    self::assertStringContainsString('db-preview__description', $content);
    self::assertStringContainsString('A saved arrangement.', $content);
  }

  /**
   * A known component previews with its content and description wrappers.
   */
  public function testComponentPreviewIncludesDescription(): void {
    $response = $this->controller->getComponentPreview('display_builder_test:test_1', '');
    $content = (string) $response->getContent();

    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('db-preview__content', $content);
    // The component declares a description, so the footer wrapper is present.
    self::assertStringContainsString('db-preview__description', $content);
    self::assertStringContainsString('Used for testing Display Builder.', $content);
  }

  /**
   * A block preview wraps its rendered source, with no description footer.
   *
   * RenderSource swallows a source it cannot build and returns empty markup, so
   * the response is always well-formed; getBlockPreview passes no description.
   */
  public function testBlockPreviewWrapsSource(): void {
    $response = $this->controller->getBlockPreview('system_powered_by_block');
    $content = (string) $response->getContent();

    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('db-preview__content', $content);
    self::assertStringNotContainsString('db-preview__description', $content);
  }

}
