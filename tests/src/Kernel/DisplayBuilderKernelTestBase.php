<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\Entity\ProfileInterface;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\Island\IslandInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base common methods for the DisplayBuilder Kernel tests.
 *
 * @internal
 */
abstract class DisplayBuilderKernelTestBase extends KernelTestBase {

  /**
   * Init a test instance with optional id and profile.
   *
   * @param ?string $profile_id
   *   Display builder profile entity ID, if NULL will create one.
   * @param ?string $instance_id
   *   Instance entity ID, if NULL set random.
   *
   * @return \Drupal\display_builder\InstanceInterface
   *   The instance for which to check access.
   */
  protected function createDisplayBuilderInstance(?string $profile_id = NULL, ?string $instance_id = NULL): InstanceInterface {
    $instance_id = $instance_id ?? $this->randomMachineName();
    $profile_id = $profile_id ?? $this->randomMachineName();
    $this->createDisplayBuilderProfile($profile_id);
    $instance = Instance::create([
      'id' => $instance_id,
      // Because there is no proper Drupal integration to rely on, we set the
      // instance ID and the profile entity themselves as plugin configuration.
      'buildable' => [
        'plugin_id' => 'test',
        'configuration' => [
          'instance_id' => $instance_id,
          'profile_id' => $profile_id,
        ],
      ],
    ]);

    return $instance;
  }

  /**
   * Create a Display Builder profile.
   *
   * @param string $profile_id
   *   The profile ID.
   * @param array $values
   *   Extra values to set to the profile.
   *
   * @return \Drupal\display_builder\Entity\ProfileInterface
   *   The created profile.
   */
  protected static function createDisplayBuilderProfile(string $profile_id, array $values = []): ProfileInterface {
    if ($profile = Profile::load($profile_id)) {
      return $profile;
    }
    $data = [
      'id' => $profile_id,
      'label' => 'Test Profile',
      'description' => 'Test Description',
    ];
    $profile = Profile::create(\array_merge($data, $values));
    $profile->save();

    return $profile;
  }

  /**
   * Reload a display_builder_instance from storage by ID.
   *
   * @param string $id
   *   The instance entity ID.
   *
   * @return \Drupal\display_builder\InstanceInterface
   *   The freshly loaded instance.
   */
  protected function loadInstance(string $id): InstanceInterface {
    /** @var \Drupal\display_builder\InstanceInterface $instance */
    $instance = $this->container->get('entity_type.manager')
      ->getStorage('display_builder_instance')
      ->load($id);

    return $instance;
  }

  /**
   * Create an island plugin instance via the island plugin manager.
   *
   * @param string $id
   *   The island plugin ID.
   * @param array $configuration
   *   Optional plugin configuration.
   *
   * @return \Drupal\display_builder\Island\IslandInterface
   *   The island plugin instance.
   */
  protected function createIslandPlugin(string $id, array $configuration = []): IslandInterface {
    return $this->container->get('plugin.manager.db_island')->createInstance($id, $configuration);
  }

}
