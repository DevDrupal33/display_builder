<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel test for the PageLayout config entity and its form.
 *
 * @internal
 */
#[Group('display_builder')]
final class PageLayoutEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'display_builder',
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
      'display_builder' => '',
      'sources' => [],
      'conditions' => [],
    ]);
    $entity->save();

    // Load the entity.
    $loaded = PageLayout::load('test_layout');
    self::assertNotNull($loaded, 'PageLayout entity loaded.');
    self::assertSame('Test Layout', $loaded->label());

    // Test getInstanceId().
    self::assertSame('page_layout__test_layout', $loaded->getInstanceId());

    // Delete the entity.
    $entity->delete();
    self::assertNull(PageLayout::load('test_layout'), 'Entity deleted.');
  }

  /**
   * Test the PageLayout form build for expected fields.
   */
  public function testPageLayoutFormBuild(): void {
    $entity = PageLayout::create([
      'id' => 'form_layout',
      'label' => 'Form Layout',
      'weight' => 0,
      'display_builder' => '',
      'sources' => [],
      'conditions' => [],
    ]);
    $entity->save();

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
      'display_builder' => '',
      'sources' => [],
      'conditions' => [],
    ]);
    $entity->save();

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
