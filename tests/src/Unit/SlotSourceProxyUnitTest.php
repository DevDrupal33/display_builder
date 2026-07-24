<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\display_builder\SlotSourceProxy;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\SourcePluginBase;
use Drupal\ui_patterns\SourcePluginManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test the SlotSourceProxy label and summary resolution.
 *
 * The proxy resolves the label and summary shown on every Wireframe and Builder
 * panel row, so its output is what an editor reads to tell two otherwise
 * identical components apart. It has a single injected plugin manager, so it
 * is unit tested.
 *
 * @internal
 */
#[CoversClass(SlotSourceProxy::class)]
#[Group('display_builder')]
final class SlotSourceProxyUnitTest extends UnitTestCase {

  /**
   * The source plugin manager mock.
   */
  private MockObject $sourceManager;

  /**
   * The proxy under test.
   */
  private SlotSourceProxy $proxy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->sourceManager = $this->createMock(SourcePluginManager::class);
    $this->proxy = new SlotSourceProxy($this->sourceManager);
  }

  /**
   * Test an unresolvable source degrades to empty strings.
   *
   * A panel row is built from this return value, so a NULL here would fatal
   * the whole panel rather than render one nameless row.
   */
  public function testUnresolvableSourceReturnsEmptyStrings(): void {
    $this->sourceManager->method('getSource')->willReturn(NULL);

    self::assertSame(['label' => '', 'summary' => ''], $this->proxy->getLabelWithSummary([]));
  }

  /**
   * Test the summary is appended to the label, comma separated.
   */
  public function testSummaryIsAppendedToTheLabel(): void {
    $this->sourceReturns('Text', ['Bold', 'Centered']);

    self::assertSame([
      'label' => 'Text',
      'summary' => 'Text: Bold, Centered',
    ], $this->proxy->getLabelWithSummary(['node_id' => 'node_1']));
  }

  /**
   * Test label_only skips the summary.
   *
   * This is the mode the drag ghost and the drawer title use, where the
   * settings summary would be noise.
   */
  public function testLabelOnlySkipsTheSummary(): void {
    $this->sourceReturns('Text', ['Bold']);

    self::assertSame([
      'label' => 'Text',
      'summary' => '',
    ], $this->proxy->getLabelWithSummary(['node_id' => 'node_1'], [], TRUE));
  }

  /**
   * Test an empty summary leaves no dangling separator.
   *
   * A source with settings that are all unset returns entries that are empty
   * or whitespace. Appending them anyway would render "Text: , " on the row.
   */
  public function testEmptySummaryAddsNoSeparator(): void {
    $this->sourceReturns('Text', ['', '   ']);

    self::assertSame([
      'label' => 'Text',
      'summary' => 'Text',
    ], $this->proxy->getLabelWithSummary(['node_id' => 'node_1']));
  }

  /**
   * Test summary entries are trimmed before being joined.
   */
  public function testSummaryEntriesAreTrimmed(): void {
    $this->sourceReturns('Text', ['  Bold  ', "\nCentered\t"]);

    self::assertSame('Text: Bold, Centered', $this->proxy->getLabelWithSummary(['node_id' => 'node_1'])['summary']);
  }

  /**
   * Test the summary is escaped but the label is not.
   *
   * A block token can carry markup, and the summary is printed as markup on
   * the row - hence the escape. The label is deliberately left raw here; it is
   * escaped by whoever renders it, and double-escaping would surface as
   * "&amp;amp;" on the panel.
   */
  public function testSummaryIsEscapedAndLabelIsNot(): void {
    $this->sourceReturns('Block <em>token</em>', ['<script>alert(1)</script>']);

    $result = $this->proxy->getLabelWithSummary(['node_id' => 'node_1']);

    self::assertSame('Block <em>token</em>', $result['label']);
    self::assertSame(
      'Block &lt;em&gt;token&lt;/em&gt;: &lt;script&gt;alert(1)&lt;/script&gt;',
      $result['summary'],
    );
  }

  /**
   * Test the label is requested with its context.
   *
   * Label(TRUE) is what disambiguates two rows built from the same source but
   * different contexts; label(FALSE) would render them identically.
   */
  public function testLabelIsRequestedWithContext(): void {
    $source = $this->createMock(SourcePluginBase::class);
    $source->expects(self::once())->method('label')->with(TRUE)->willReturn('Text');
    $source->method('settingsSummary')->willReturn([]);
    $this->sourceManager->method('getSource')->willReturn($source);

    $this->proxy->getLabelWithSummary(['node_id' => 'node_1']);
  }

  /**
   * Test the node ID and contexts are forwarded to the source manager.
   *
   * The data array is passed as the source configuration, and a missing
   * node_id degrades to an empty string rather than an undefined index.
   */
  public function testLookupForwardsNodeIdDataAndContexts(): void {
    $contexts = ['entity' => 'a context'];
    $data = ['node_id' => 'node_1', 'source_id' => 'textfield'];

    $this->sourceManager->expects(self::once())
      ->method('getSource')
      ->with('node_1', [], $data, $contexts)
      ->willReturn(NULL);

    $this->proxy->getLabelWithSummary($data, $contexts);
  }

  /**
   * Test a missing node ID is looked up as an empty string.
   */
  public function testMissingNodeIdIsLookedUpAsEmptyString(): void {
    $this->sourceManager->expects(self::once())
      ->method('getSource')
      ->with('', [], [], [])
      ->willReturn(NULL);

    $this->proxy->getLabelWithSummary([]);
  }

  /**
   * Makes the manager resolve to a source with the given label and summary.
   */
  private function sourceReturns(string $label, array $summary): void {
    $source = $this->createMock(SourcePluginBase::class);
    $source->method('label')->willReturn($label);
    $source->method('settingsSummary')->willReturn($summary);

    $this->sourceManager->method('getSource')->willReturn($source);
  }

}
