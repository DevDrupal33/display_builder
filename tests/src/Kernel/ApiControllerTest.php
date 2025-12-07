<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Url;
use Drupal\display_builder\Controller\ApiController;
use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\PatternPreset;
use Drupal\display_builder\InstanceInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Test the ApiController class.
 *
 * @internal
 */
#[CoversClass(ApiController::class)]
#[Group('display_builder')]
final class ApiControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_styles',
    'ui_skins',
    'breakpoint',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * The controller to test.
   */
  protected ApiController $controller;

  /**
   * The builder instance entity.
   */
  protected InstanceInterface $instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
    $this->installEntitySchema('display_builder_instance');
    $this->installConfig(['system', 'display_builder', 'ui_patterns', 'display_builder_test']);

    // Create a real builder entity.
    $this->instance = Instance::create([
      'id' => 'test_instance',
      'label' => 'Test Builder instance',
      'profileId' => 'test',
    ]);
    $this->instance->save();

    // Get the controller from the container.
    $this->controller = $this->container->get('class_resolver')->getInstanceFromDefinition(ApiController::class);
  }

  /**
   * Test the attachToRoot() method.
   */
  public function testAttachToRoot(): void {
    $url = Url::fromRoute('display_builder.api_root_attach', [
      'display_builder_instance' => $this->instance->id(),
    ]);
    $request = Request::create($url->toString(), 'POST', [
      'source_id' => 'token',
      'position' => 0,
    ]);
    $response = $this->controller->attachToRoot($request, $this->instance);
    self::assertIsArray($response['history']);
    self::assertIsArray($response['state']);
    self::assertIsArray($response['logs']);
  }

  /**
   * Tests saveAsPreset() with non ASCII characters in entity label.
   */
  public function testLabelEncoding(): void {
    $node_id = $this->instance->attachToRoot(0, 'token', []);
    // cspell:disable-next-line
    $iso_8859_1_characters = '¡¢£¤¥¦§¨©ª«¬®¯°±²³´µ¶·¸¹º»¼½¾¿ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõö÷øùúûüýþÿ';
    // cspell:disable-next-line
    $other_characters = '€œŒ';
    $label = $iso_8859_1_characters . $other_characters;
    $url = Url::fromRoute('display_builder.api_save_preset', [
      'display_builder_instance' => $this->instance->id(),
      'node_id' => $node_id,
    ]);
    $request = Request::create($url->toString(), 'POST', []);
    $request->headers->add([
      // Browsers send HTTP headers values with the ISO-8859-1 charset.
      'hx-prompt' => \mb_convert_encoding($label, 'ISO-8859-1'),
    ]);
    $this->controller->saveAsPreset($request, $this->instance, $node_id);
    $presets = PatternPreset::loadMultiple();
    $preset = array_first($presets);
    // Non ISO-8859-1 are replaced by a question mark.
    $label = $iso_8859_1_characters . \str_repeat('?', \mb_strlen($other_characters));
    self::assertEquals($preset->label(), $label);
  }

}
