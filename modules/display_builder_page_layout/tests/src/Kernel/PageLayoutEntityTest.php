<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder_page_layout\Kernel;

use Drupal\display_builder_page_layout\Entity\PageLayout;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;

/**
 * Kernel test for the PageLayout config entity and its form.
 *
 * @group display_builder
 */
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
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
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
    $this->assertNotNull($loaded, 'PageLayout entity loaded.');
    $this->assertEquals('Test Layout', $loaded->label());

    // Test getInstanceId().
    $this->assertEquals('page_layout__test_layout', $loaded->getInstanceId());

    // Delete the entity.
    $entity->delete();
    $this->assertNull(PageLayout::load('test_layout'), 'Entity deleted.');
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

    $this->assertArrayHasKey('label', $form, 'Form has label field.');
    $this->assertArrayHasKey('id', $form, 'Form has id field.');
    $this->assertArrayHasKey('conditions', $form, 'Form has conditions field.');
    $this->assertArrayHasKey('status', $form, 'Form has status field.');
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
    $this->assertEquals('Original Label', $loaded->label());

    // Change label and weight.
    $loaded->set('label', 'Updated Label');
    $loaded->set('weight', 10);
    $loaded->save();

    // Reload and assert changes.
    $updated = PageLayout::load('edit_layout');
    $this->assertEquals('Updated Label', $updated->label());
    $this->assertEquals(10, $updated->get('weight'));
  }

}
