<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\Entity\Instance;
use Drupal\display_builder\Entity\Profile;
use Drupal\display_builder\InstanceInterface;
use Drupal\display_builder\ProfileInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Base common methods dor the DisplayBuilder Kernel tests.
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
    if ($profile_id === NULL) {
      $profile = $this->createDisplayBuilderProfile($this->randomMachineName());
      $profile_id = $profile->id();
    }
    $instance = Instance::create([
      'id' => $instance_id ?? $this->randomMachineName(),
      'label' => 'Test Instance',
      'profileId' => $profile_id,
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
   * @return \Drupal\display_builder\ProfileInterface
   *   The created profile.
   */
  protected static function createDisplayBuilderProfile(string $profile_id, array $values = []): ProfileInterface {
    $data = [
      'id' => $profile_id,
      'label' => 'Test Profile',
      'description' => 'Test Description',
    ];
    $profile = Profile::create(\array_merge($data, $values));
    $profile->save();

    return $profile;
  }

}
