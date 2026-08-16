<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\Island\IslandType;
use Drupal\display_builder\Update\ProfileIslandsUpdater;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the island configuration migrations used by post update hooks.
 *
 * The 'test_island_view' island carries the interchangeable 'legacy_setting'
 * and 'setting' keys, so a key can be moved between two names that both have a
 * schema. @see display_builder_test.schema.yml.
 *
 * @internal
 */
#[CoversClass(ProfileIslandsUpdater::class)]
#[Group('display_builder')]
final class ProfileIslandsUpdaterTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('display_builder_profile');
    $this->installConfig(['display_builder', 'display_builder_test']);
  }

  /**
   * An island is renamed with its configuration, unless the new one is there.
   */
  public function testRenameIsland(): void {
    $this->createProfile('rename_old', [
      'layers' => ['status' => TRUE, 'weight' => -6],
    ]);
    // Already migrated: the new island wins, the old one is left as it is.
    $this->createProfile('rename_done', [
      'layers' => ['status' => FALSE, 'weight' => 0],
      'scaffold' => ['status' => TRUE, 'weight' => -6],
    ]);

    ProfileIslandsUpdater::create()->renameIsland('layers', 'scaffold')->save();

    self::assertSame(
      ['scaffold' => ['status' => TRUE, 'weight' => -6]],
      $this->islands('rename_old'),
      'The configuration moves to the new island ID.',
    );
    self::assertSame(
      [
        'layers' => ['status' => FALSE, 'weight' => 0],
        'scaffold' => ['status' => TRUE, 'weight' => -6],
      ],
      $this->islands('rename_done'),
      'A profile already holding the new island is left alone.',
    );
  }

  /**
   * Islands are removed with all of their configuration.
   */
  public function testRemoveIsland(): void {
    $this->createProfile('remove_island', [
      'builder' => ['status' => TRUE, 'weight' => -6],
      'logs' => ['status' => FALSE, 'weight' => -4],
      'tree' => ['status' => TRUE, 'weight' => -9],
    ]);

    ProfileIslandsUpdater::create()->removeIsland('logs', 'tree', 'never_existed')->save();

    self::assertSame(['builder'], \array_keys($this->islands('remove_island')));
  }

  /**
   * A key is removed from the named islands only, or from every island.
   */
  public function testRemoveKey(): void {
    $this->createProfile('remove_key', [
      'builder' => ['status' => TRUE, 'weight' => -6],
      'state' => ['status' => TRUE, 'weight' => 0],
    ]);

    ProfileIslandsUpdater::create()->removeKey('weight', ['builder'])->save();

    self::assertSame(['status' => TRUE], $this->islands('remove_key')['builder']);
    self::assertSame(
      ['status' => TRUE, 'weight' => 0],
      $this->islands('remove_key')['state'],
      'An island outside the list is untouched.',
    );

    // No list at all: the key is gone everywhere.
    ProfileIslandsUpdater::create()->removeKey('weight')->save();

    self::assertSame(['status' => TRUE], $this->islands('remove_key')['state']);
  }

  /**
   * A key is renamed, keeping its value, unless the new one is there.
   */
  public function testRenameKey(): void {
    $this->createProfile('rename_key', [
      'test_island_view' => ['status' => TRUE, 'legacy_setting' => 'moved'],
    ]);
    $this->createProfile('rename_key_done', [
      'test_island_view' => [
        'status' => TRUE,
        'legacy_setting' => 'stale',
        'setting' => 'kept',
      ],
    ]);

    ProfileIslandsUpdater::create()->renameKey('legacy_setting', 'setting')->save();

    self::assertSame(
      ['status' => TRUE, 'setting' => 'moved'],
      $this->islands('rename_key')['test_island_view'],
    );
    self::assertSame(
      ['status' => TRUE, 'legacy_setting' => 'stale', 'setting' => 'kept'],
      $this->islands('rename_key_done')['test_island_view'],
      'An island already holding the new key is left alone.',
    );
  }

  /**
   * A key is added when missing and replaced when it holds another value.
   */
  public function testSetKey(): void {
    $this->createProfile('set_key_add', [
      'test_island_view' => ['status' => TRUE],
    ]);
    $this->createProfile('set_key_replace', [
      'test_island_view' => ['status' => TRUE, 'setting' => 'old'],
    ]);

    ProfileIslandsUpdater::create()->setKey('setting', 'new', ['test_island_view'])->save();

    self::assertSame(
      ['status' => TRUE, 'setting' => 'new'],
      $this->islands('set_key_add')['test_island_view'],
    );
    self::assertSame(
      ['status' => TRUE, 'setting' => 'new'],
      $this->islands('set_key_replace')['test_island_view'],
    );
  }

  /**
   * Only the profiles an operation really changed are saved.
   */
  public function testUntouchedProfilesAreNotSaved(): void {
    $untouched = $this->createProfile('untouched', [
      'builder' => ['status' => TRUE],
    ]);
    $this->createProfile('touched', [
      'builder' => ['status' => TRUE, 'weight' => -6],
    ]);
    $before = $untouched->get('islands');

    ProfileIslandsUpdater::create()->removeKey('weight', ['builder'])->save();

    self::assertSame($before, $this->islands('untouched'));
    self::assertSame(['builder' => ['status' => TRUE]], $this->islands('touched'));
  }

  /**
   * Islands are addressable by type rather than by a hardcoded list of IDs.
   */
  public function testIslandIdsByType(): void {
    $ids = ProfileIslandsUpdater::islandIdsByType(IslandType::View, IslandType::Preview);

    // The View panels and the Preview pane, but nothing from another type.
    self::assertContains('builder', $ids);
    self::assertContains('tree', $ids);
    self::assertContains('preview', $ids);
    self::assertNotContains('state', $ids, 'A Button island is not a View one.');
    self::assertNotContains('highlight', $ids, 'A Floating island is not a View one.');
  }

  /**
   * Operations are cumulative, so a rename can be followed by a key change.
   */
  public function testOperationsApplyInOrder(): void {
    $this->createProfile('chained', [
      'layers' => ['status' => TRUE, 'weight' => -6],
    ]);

    ProfileIslandsUpdater::create()
      ->renameIsland('layers', 'scaffold')
      ->removeKey('weight', ['scaffold'])
      ->save();

    self::assertSame(['scaffold' => ['status' => TRUE]], $this->islands('chained'));
  }

  /**
   * Create a profile holding the given islands.
   *
   * @param string $profile_id
   *   The profile ID.
   * @param array $islands
   *   The island configurations, keyed by island plugin ID.
   *
   * @return \Drupal\display_builder\Entity\ProfileInterface
   *   The created profile.
   */
  private function createProfile(string $profile_id, array $islands): ProfileInterface {
    return self::createDisplayBuilderProfile($profile_id, ['islands' => $islands]);
  }

  /**
   * Reload a profile and return its islands map.
   *
   * @param string $profile_id
   *   The profile ID.
   *
   * @return array
   *   The stored island configurations.
   */
  private function islands(string $profile_id): array {
    \Drupal::entityTypeManager()->getStorage('display_builder_profile')->resetCache([$profile_id]);

    return Profile::load($profile_id)?->get('islands') ?? [];
  }

}
