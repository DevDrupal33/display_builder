import AxeBuilder from '@axe-core/playwright'
import { expect } from '@playwright/test'
import type { Result } from 'axe-core'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Rule ids allowed to fail, so what is already broken is written down instead of
// pretended away. Empty on purpose - the builder currently has no axe violation
// at all, and this test is what keeps it that way. Adding an id here is
// admitting a regression: do it only with a reason next to it, and prefer
// fixing. The first run of this test found four, all since fixed:
// button-name and link-name (icon-only buttons had no accessible name -
// @see components/shoelace/button/button.twig), color-contrast (the active
// tab's label - @see components/display_builder/css/colors.css) and
// label-title-only (the library search box - @see
// components/library_panel/search.css).
//
// Scoped to .display-builder: this covers what this module renders, not the
// admin theme or Drupal's own chrome around it, which we do not control here.
const KNOWN_VIOLATIONS: string[] = []

/**
 * Compact one-line-per-node summary, so a failure says what and where.
 */
function format (violations: Result[]): string {
  return violations
    .map((v) => `${v.id} (${v.impact}, ${v.nodes.length} node(s)): ${v.help}\n` +
      v.nodes.map((n) => `    ${n.target.join(' ')}`).join('\n'))
    .join('\n')
}

test('Accessibility', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileFullId)
  })

  // A builder with something in it: an empty canvas exercises almost none of
  // the UI that carries the accessibility risk (placed nodes, their controls).
  await test.step(`Drop a component`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-island-builder .db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(page.locator('.db-island-builder [data-test="test_simple"]')).toHaveCount(1)
  })

  await test.step(`No violations outside the known list`, async () => {
    const results = await new AxeBuilder({ page }).include('.display-builder').analyze()

    const unknown = results.violations.filter((v) => !KNOWN_VIOLATIONS.includes(v.id))
    expect(unknown, `Unexpected accessibility violations:\n${format(unknown)}`).toEqual([])

    // A known violation would be tolerated at its own severity, never at
    // critical - the floor this test refuses to let the UI fall below.
    const critical = results.violations.filter((v) => v.impact === 'critical')
    expect(critical, `Critical accessibility violations:\n${format(critical)}`).toEqual([])
  })
})
