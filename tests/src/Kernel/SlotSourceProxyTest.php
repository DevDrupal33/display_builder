<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Kernel;

use Drupal\display_builder\SlotSourceProxy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test SlotSourceProxy against the real ui_patterns source manager.
 *
 * The unit test pins the proxy's own branch logic - escaping, trimming, the
 * empty and label-only cases - over a mocked manager. That mock encodes an
 * assumption about ui_patterns rather than verifying it: it cannot tell
 * whether a real manager resolves a source from real node data at all, and it
 * would keep passing through a signature or behavior change upstream. This
 * covers that seam, and nothing else.
 *
 * @see \Drupal\Tests\display_builder\Unit\SlotSourceProxyUnitTest
 *
 * @internal
 */
#[CoversClass(SlotSourceProxy::class)]
#[Group('display_builder')]
#[RunTestsInSeparateProcesses]
final class SlotSourceProxyTest extends DisplayBuilderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'ui_patterns',
    // Required: the entity field source deriver resolves field types eagerly
    // during plugin discovery, so omitting this fails every lookup with
    // "field_item:ui_patterns_source does not exist".
    'ui_patterns_field',
    'display_builder',
    'display_builder_test',
  ];

  /**
   * The proxy under test, with the real source plugin manager.
   */
  private SlotSourceProxy $proxy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'display_builder']);
    // Required: SourcePluginBase resolves the current-user context when a
    // source is instantiated, which queries the users table.
    $this->installEntitySchema('user');

    $this->proxy = $this->container->get('display_builder.slot_sources_proxy');
  }

  /**
   * Test a real block source resolves to its label and summary.
   *
   * This is the seam the unit test mocks away: that node data as the tree
   * actually stores it resolves to a real source through the real manager.
   */
  public function testRealBlockSourceResolves(): void {
    $result = $this->proxy->getLabelWithSummary([
      'source_id' => 'textfield',
      'source' => ['value' => 'I am in a slot'],
    ]);

    self::assertNotSame('', $result['label']);
    self::assertStringContainsString($result['label'], $result['summary']);
  }

  /**
   * Test a real component source resolves to its label.
   */
  public function testRealComponentSourceResolves(): void {
    $result = $this->proxy->getLabelWithSummary([
      'source_id' => 'component',
      'source' => ['component' => ['component_id' => 'display_builder_test:test_1']],
    ]);

    self::assertNotSame('', $result['label']);
  }

  /**
   * Test the source is selected by source_id, not by node_id.
   *
   * The proxy passes $data['node_id'] as the manager's $prop_or_slot_id, but
   * the plugin is chosen from $configuration['source_id']. Node data as
   * SourceTree::normalize() stores it has had node_id unset, so most callers
   * pass none at all - and that must not change which source is resolved, or
   * the same node would label differently depending on whether it arrived via
   * getNodeData() or getNode().
   */
  public function testNodeIdDoesNotAffectResolution(): void {
    $data = [
      'source_id' => 'textfield',
      'source' => ['value' => 'Some text'],
    ];

    $without = $this->proxy->getLabelWithSummary($data);
    $with = $this->proxy->getLabelWithSummary(['node_id' => 'block_1'] + $data);

    self::assertSame($without, $with);
    self::assertNotSame('', $without['label']);
  }

  /**
   * Test data with no source_id falls back to the slot default source.
   *
   * Not what the mocked unit test implies. The proxy always passes an empty
   * $definition, so the manager substitutes the slot prop type and resolves
   * that prop type's *default* source rather than returning NULL - which is
   * the 'component' source, labelled "Component".
   *
   * The practical consequence is that the proxy's `if (!$source)` guard is
   * close to unreachable for slot data: a node with no source_id is labelled
   * "Component" on the panel rather than falling back to the caller's own
   * handling (Instance::nodeLabel() would otherwise show the source_id).
   * Pinned here because it is surprising, not because it is necessarily
   * wrong.
   */
  public function testMissingSourceIdFallsBackToSlotDefault(): void {
    $result = $this->proxy->getLabelWithSummary(['source' => ['value' => 'orphan']]);

    self::assertSame('Component', $result['label']);
  }

  /**
   * Test label_only skips the summary for a real source.
   */
  public function testLabelOnlySkipsSummaryForRealSource(): void {
    $data = [
      'source_id' => 'textfield',
      'source' => ['value' => 'I am in a slot'],
    ];

    $full = $this->proxy->getLabelWithSummary($data);
    $label_only = $this->proxy->getLabelWithSummary($data, [], TRUE);

    self::assertSame($full['label'], $label_only['label']);
    self::assertSame('', $label_only['summary']);
  }

}
