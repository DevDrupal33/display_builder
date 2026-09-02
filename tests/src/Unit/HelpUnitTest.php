<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\display_builder\Hook\Help;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test the Help hook class.
 *
 * @internal
 */
#[CoversClass(Help::class)]
#[Group('display_builder')]
final class HelpUnitTest extends UnitTestCase {

  /**
   * The service under test.
   */
  private Help $help;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->help = new Help($this->getStringTranslationStub());
  }

  /**
   * Test the module's own help page returns a description and a doc link.
   */
  public function testHelpOnModulePage(): void {
    $output = $this->help->help('help.page.display_builder');

    self::assertStringContainsString('Display Builder', $output);
    self::assertStringContainsString('https://display-builder-b6bde3.pages.drupalcode.org', $output);
  }

  /**
   * Test other routes get nothing.
   */
  public function testHelpOnUnrelatedRoute(): void {
    self::assertSame('', $this->help->help('entity.node.canonical'));
  }

}
