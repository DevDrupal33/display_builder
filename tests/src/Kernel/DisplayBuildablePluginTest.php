<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\display_builder\DisplayBuildableInterface;
use Drupal\display_builder\DisplayBuildablePluginBase;
use Drupal\display_builder\InstanceStorageInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the form builder of DisplayBuildablePluginBase class.
 *
 * @internal
 */
#[CoversClass(DisplayBuildablePluginBase::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class DisplayBuildablePluginTest extends DisplayBuilderKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'display_builder',
    'display_builder_test',
    'display_builder_ui',
  ];

  /**
   * The use Display Builder profile permission pattern for sprintf.
   */
  protected string $useDisplayBuilderPermission = 'use display builder %s';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder', 'ui_patterns']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('display_builder_profile');
  }

  /**
   * Test the buildInstanceForm() method.
   *
   * @param array $data
   *   The data setup for the test.
   * @param array $expect
   *   The expected results.
   */
  #[DataProvider('buildInstanceFormProvider')]
  public function testBuildInstanceForm(array $data, array $expect): void {
    // Create profiles.
    $profiles = [];

    foreach ($data['profiles'] as $id) {
      $profiles[$id] = self::createDisplayBuilderProfile($id, ['label' => $id]);
    }

    // Create user with permissions.
    $permissions = [];

    if ($data['admin_permission']) {
      $permissions[] = 'administer display builder profile';
    }

    foreach ($data['profile_permissions'] as $id) {
      $permissions[] = \sprintf($this->useDisplayBuilderPermission, $id);
    }

    $this->setUpCurrentUser([], $permissions);

    // Setup plugin.
    $plugin = TestDisplayBuildablePlugin::create($this->container, [], 'test', []);

    if (isset($data['current_profile'])) {
      $plugin->profile = $profiles[$data['current_profile']];
    }
    $plugin->instanceId = $data['instance_id'] ?? NULL;

    // Build form.
    $form = $plugin->buildInstanceForm();
    $key = DisplayBuildableInterface::PROFILE_PROPERTY;

    // Assertions.
    if (isset($expect['markup'])) {
      self::assertArrayHasKey($key, $form);
      self::assertArrayHasKey('#markup', $form[$key]);
      self::assertEquals($expect['markup'], (string) $form[$key]['#markup']);
    }

    if (isset($expect['select'])) {
      $element = $form[$key];
      self::assertEquals('select', $element['#type']);

      if (isset($expect['disabled'])) {
        self::assertTrue($element['#disabled']);
      }
      else {
        self::assertArrayNotHasKey('#disabled', $element);
      }

      if (isset($expect['options'])) {
        self::assertEquals($expect['options'], $element['#options']);
      }

      if (isset($expect['default_value'])) {
        self::assertEquals($expect['default_value'], $element['#default_value']);
      }

      if (isset($expect['description_contains'])) {
        // Description can be string or array (if admin link is added).
        $desc = $element['#description'];

        if (\is_array($desc)) {
          $desc_text = '';

          foreach ($desc as $part) {
            if (isset($part['#markup'])) {
              $desc_text .= $part['#markup'];
            }

            if (isset($part['#title'])) {
              $desc_text .= $part['#title'];
            }
          }
          self::assertStringContainsString($expect['description_contains'], $desc_text);
        }
        else {
          self::assertStringContainsString($expect['description_contains'], (string) $desc);
        }
      }
    }

    if (isset($expect['link'])) {
      self::assertArrayHasKey('link', $form);
    }
    else {
      self::assertArrayNotHasKey('link', $form);
    }
  }

  /**
   * Data provider for testBuildInstanceForm().
   *
   * @return array
   *   The data to test and expected.
   */
  public static function buildInstanceFormProvider(): array {
    return [
      'not allowed, no profile' => [
        'data' => [
          'profiles' => ['p1'],
          'admin_permission' => FALSE,
          'profile_permissions' => [],
          'current_profile' => NULL,
        ],
        'expect' => [
          'markup' => 'You are not allowed to use Display Builder.',
        ],
      ],
      'not allowed, with profile' => [
        'data' => [
          'profiles' => ['p1'],
          'admin_permission' => FALSE,
          'profile_permissions' => [],
          'current_profile' => 'p1',
        ],
        'expect' => [
          'select' => TRUE,
          'disabled' => TRUE,
          'options' => ['p1' => 'p1'],
        ],
      ],
      'allowed, no profile' => [
        'data' => [
          'profiles' => ['p1', 'p2'],
          'admin_permission' => FALSE,
          'profile_permissions' => ['p1'],
          'current_profile' => NULL,
        ],
        'expect' => [
          'select' => TRUE,
          'options' => ['p1' => 'p1'],
        ],
      ],
      'allowed, with profile' => [
        'data' => [
          'profiles' => ['p1'],
          'admin_permission' => FALSE,
          'profile_permissions' => ['p1'],
          'current_profile' => 'p1',
        ],
        'expect' => [
          'select' => TRUE,
          'default_value' => 'p1',
        ],
      ],
      // Global admin permission is not enough for profiles selection.
      // @see DisplayBuildablePluginBase::getAllowedProfiles()
      'admin allowed' => [
        'data' => [
          'profiles' => ['p1', 'p2'],
          'admin_permission' => TRUE,
          'profile_permissions' => ['p1', 'p2'],
          'current_profile' => NULL,
        ],
        'expect' => [
          'select' => TRUE,
          'options' => ['p1' => 'p1', 'p2' => 'p2'],
          'description_contains' => 'Add and configure display builder profiles',
        ],
      ],
      'with instance id' => [
        'data' => [
          'profiles' => ['p1'],
          'admin_permission' => FALSE,
          'profile_permissions' => ['p1'],
          'current_profile' => 'p1',
          'instance_id' => 'inst1',
        ],
        'expect' => [
          'select' => TRUE,
          'link' => TRUE,
        ],
      ],
    ];
  }

}

/**
 * Test class for DisplayBuildablePluginBase.
 */
class TestDisplayBuildablePlugin extends DisplayBuildablePluginBase {

  /**
   * {@inheritdoc}
   */
  public ?ProfileInterface $profile = NULL;

  /**
   * {@inheritdoc}
   */
  public ?string $instanceId = NULL;

  /**
   * {@inheritdoc}
   */
  public static function getPrefix(): string {
    return 'test_prefix';
  }

  /**
   * {@inheritdoc}
   */
  public function getProfile(): ?ProfileInterface {
    return $this->profile;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceId(): ?string {
    return $this->instanceId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBuilderUrl(): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getContextRequirement(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public static function getDisplayUrlFromInstanceId(string $instance_id): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialContext(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getInitialSources(): array {
    return $this->getSources();
  }

  /**
   * {@inheritdoc}
   */
  public function saveSources(): void {}

  /**
   * {@inheritdoc}
   */
  public static function getUrlFromInstanceId(string $instance_id): Url {
    return Url::fromRoute('entity.display_builder_instance.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getSources(): array {
    return $this->instance->getCurrentState();
  }

  /**
   * {@inheritdoc}
   *
   * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceAfterLastUsed
   */
  public static function checkAccess(string $instance_id, AccountInterface $account): AccessResultInterface {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public static function checkInstanceId(string $instance_id): ?array {
    return [
      'id' => $instance_id,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function collectInstances(InstanceStorageInterface $instanceStorage, ?EntityTypeManagerInterface $entityTypeManager = NULL): array {
    return [];
  }

}
