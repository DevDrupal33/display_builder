<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\Tests\sdc_devel\Kernel\SdcDevelComponentKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Validate components with SDC Devel.
 *
 * @internal
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
#[Group('display_builder')]
final class ComponentValidatorTest extends SdcDevelComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ui_patterns',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $themes = [
    'display_builder_theme_test',
  ];

}
