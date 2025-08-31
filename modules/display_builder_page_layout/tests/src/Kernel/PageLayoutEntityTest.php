<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\display_builder\ConfigFormBuilderInterface;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for the PageLayout config entity and its form.
 *
 * @internal
 */
#[CoversClass('\Drupal\display_builder_page_layout\Entity\PageLayout')]
#[Group('display_builder')]
final class PageLayoutEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'display_builder',
    'display_builder_test',
    'display_builder_page_layout',
    'ui_patterns',
    'path_alias',
  ];

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('theme_installer')
      ->install([
        'display_builder_theme_test',
      ]);
    $this->config('system.theme')->set('default', 'display_builder_theme_test')->save();

    $this->installConfig(['display_builder']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test basic CRUD operations for the PageLayout config entity.
   */
  public function testPageLayoutEntityCrud(): void {
    // Create a PageLayout entity.
    /** @var \Drupal\display_builder_page_layout\PageLayoutInterface $entity */
    $entity = PageLayout::create([
      'id' => 'test_layout',
      'label' => 'Test Layout',
      'weight' => 1,
      ConfigFormBuilderInterface::PROFILE_PROPERTY => 'test',
      ConfigFormBuilderInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();
    $entity->initInstanceIfMissing();

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($entity->getInstanceId());
    self::assertNotNull($instance, 'PageLayout instance loaded.');

    // Load the entity.
    $loaded = PageLayout::load('test_layout');
    self::assertNotNull($loaded, 'PageLayout entity loaded.');
    self::assertSame('Test Layout', $loaded->label());

    // Test getInstanceId().
    self::assertSame('page_layout__test_layout', $loaded->getInstanceId());

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($loaded->getInstanceId());
    self::assertNotNull($instance);

    // Delete the entity.
    $entity->delete();
    self::assertNull(PageLayout::load('test_layout'), 'Entity deleted.');

    $instance = $this->entityTypeManager->getStorage('display_builder_instance')->load($loaded->getInstanceId());
    self::assertNull($instance);
  }

  /**
   * Test the PageLayout form build for expected fields.
   */
  public function testPageLayoutFormBuild(): void {
    $entity = PageLayout::create([
      'id' => 'form_layout',
      'label' => 'Form Layout',
      'weight' => 0,
      ConfigFormBuilderInterface::PROFILE_PROPERTY => 'test',
      ConfigFormBuilderInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();
    $entity->initInstanceIfMissing();

    // Get the form object.
    $form_object = $this->entityTypeManager
      ->getFormObject('page_layout', 'edit');
    $form_object->setEntity($entity);

    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);

    self::assertArrayHasKey('label', $form, 'Form has label field.');
    self::assertArrayHasKey('id', $form, 'Form has id field.');
    self::assertArrayHasKey('conditions', $form, 'Form has conditions field.');
    self::assertArrayHasKey('status', $form, 'Form has status field.');
  }

  /**
   * Test editing and updating a PageLayout config entity.
   */
  public function testPageLayoutEntityEdit(): void {
    // Create and save the entity.
    $entity = PageLayout::create([
      'id' => 'edit_layout',
      'label' => 'Original Label',
      'weight' => 5,
      ConfigFormBuilderInterface::PROFILE_PROPERTY => 'test',
      ConfigFormBuilderInterface::SOURCES_PROPERTY => [],
      'conditions' => [],
    ]);
    $entity->setStatus(TRUE)->save();
    $entity->initInstanceIfMissing();

    // Load and edit the entity.
    $loaded = PageLayout::load('edit_layout');
    self::assertSame('Original Label', $loaded->label());

    // Change label and weight.
    $loaded->set('label', 'Updated Label');
    $loaded->set('weight', 10);
    $loaded->save();

    // Reload and assert changes.
    $updated = PageLayout::load('edit_layout');
    self::assertSame('Updated Label', $updated->label());
    self::assertSame(10, $updated->get('weight'));
  }

}
