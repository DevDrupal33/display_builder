<?php

declare(strict_types=1);

namespace Drupal\Tests\display_builder\Unit;

use Drupal\display_builder\Island\IslandInterface;
use Drupal\display_builder\Island\IslandPluginManagerInterface;
use Drupal\display_builder\SummaryCollector;
use Drupal\display_builder\ThirdPartySettingsInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ui_patterns\SourceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test the summary groups collected for a node.
 *
 * What ends up here is what an editor reads on a Scaffold card without opening
 * a single panel, so an island silently dropped from the groups is an invisible
 * setting. Its one injected dependency is a plugin manager, so it is unit
 * tested.
 *
 * @internal
 */
#[CoversClass(SummaryCollector::class)]
#[Group('display_builder')]
final class SummaryCollectorUnitTest extends UnitTestCase {

  /**
   * The island plugin manager mock.
   */
  private MockObject $islandManager;

  /**
   * The collector under test.
   */
  private SummaryCollector $collector;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->islandManager = $this->createMock(IslandPluginManagerInterface::class);
    $this->collector = new SummaryCollector($this->islandManager);
  }

  /**
   * Test a bare node with no source has nothing to say.
   */
  public function testEmptyNodeHasNoGroup(): void {
    self::assertSame([], $this->collector->collect([]));
  }

  /**
   * Test the source's own settings land in the Config group, with its icon.
   */
  public function testSourceSettingsAreTheConfigGroup(): void {
    $this->islandManager->method('getDefinition')->willReturn([
      'label' => 'Config',
      'icon' => 'sliders',
    ]);

    $groups = $this->collector->collect([], $this->mockSource(['Title: Hello']));

    self::assertSame([
      'contextual_form' => [
        'label' => 'Config',
        'icon' => 'sliders',
        'items' => ['Title: Hello'],
      ],
    ], $groups);
  }

  /**
   * Test a source with nothing configured contributes no group at all.
   *
   * "Has a summary" and "has a group" have to be the same question: the
   * Scaffold shows one affordance per group, and an empty one would be an
   * icon promising settings that are not there.
   */
  public function testEmptySummaryIsNoGroup(): void {
    self::assertSame([], $this->collector->collect([], $this->mockSource([])));
    self::assertSame([], $this->collector->collect([], $this->mockSource([''])));
  }

  /**
   * Test a third party settings provider that is not an island is skipped.
   *
   * Displays imported and converted from Layout Builder store settings under
   * a module name rather than an island plugin ID.
   */
  public function testUnknownProviderIsSkipped(): void {
    $this->islandManager->method('hasDefinition')->willReturn(FALSE);
    $this->islandManager->expects(self::never())->method('createInstance');

    $data = ['third_party_settings' => ['layout_builder' => ['whatever']]];

    self::assertSame([], $this->collector->collect($data));
  }

  /**
   * Test an island that provides no summary at all is skipped.
   */
  public function testIslandWithoutSummaryIsSkipped(): void {
    $this->islandManager->method('hasDefinition')->willReturn(TRUE);
    $this->islandManager->method('createInstance')
      ->willReturn($this->createMock(IslandInterface::class));

    $data = ['third_party_settings' => ['visibility_conditions' => ['whatever']]];

    self::assertSame([], $this->collector->collect($data));
  }

  /**
   * Test the config group comes first, then providers in storage order.
   */
  public function testGroupOrder(): void {
    $this->islandManager->method('hasDefinition')->willReturn(TRUE);
    $this->islandManager->method('createInstance')
      ->willReturn($this->mockIsland(['Small padding']));
    $this->islandManager->method('getDefinition')
      ->willReturnCallback(static fn (string $id): array => ['label' => $id, 'icon' => NULL]);

    $data = ['third_party_settings' => ['styles' => ['whatever']]];
    $groups = $this->collector->collect($data, $this->mockSource(['Title: Hello']));

    self::assertSame(['contextual_form', 'styles'], \array_keys($groups));
    self::assertSame(['Small padding'], $groups['styles']['items']);
  }

  /**
   * A source plugin answering a given summary.
   *
   * @param array $summary
   *   The summary items.
   *
   * @return \Drupal\ui_patterns\SourceInterface
   *   The mocked source.
   */
  private function mockSource(array $summary): SourceInterface {
    $source = $this->createMock(SourceInterface::class);
    $source->method('settingsSummary')->willReturn($summary);

    return $source;
  }

  /**
   * An island plugin answering a given summary.
   *
   * @param array $summary
   *   The summary items.
   *
   * @return \Drupal\display_builder\ThirdPartySettingsInterface
   *   The mocked island.
   */
  private function mockIsland(array $summary): ThirdPartySettingsInterface {
    $island = $this->createMockForIntersectionOfInterfaces([
      IslandInterface::class,
      ThirdPartySettingsInterface::class,
    ]);
    $island->method('getSummary')->willReturn($summary);

    return $island;
  }

}
