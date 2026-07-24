import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Covers SourceTree::moveToSlot(), flagged CRITICAL in CLAUDE.md: moving a node
// removes it first and recomputes the target parent's path from the updated
// tree, and an isDescendant() guard rejects moving a node into its own subtree.
// Neither had any green e2e coverage before this test.
//
// Deliberately moves into an EMPTY slot: dropping into an already-occupied slot
// is currently a no-op in the Wireframe panel, and this test is meant to guard
// the tree engine, not to be blocked by that.
//
// Structural locators throughout, never node IDs: an HTMX swap after a drop can
// hand back a different data-node-id than the one read a moment earlier.
test('Move and circular guard', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const root = page.locator('.db-island-builder > div.db-dropzone').first()
  const components = page.locator('.db-island-builder [data-test="test_simple"]')
  const rootComponents = page.locator('.db-island-builder > div.db-dropzone > [data-test="test_simple"]')
  // The component nested one level down, inside the root component's slot.
  const nested = rootComponents.first().locator('[data-slot-id="slot_1"] [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await displayBuilder.highlight()

  await test.step(`Drop two components at root`, async () => {
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await expect(rootComponents).toHaveCount(2)
  })

  await test.step(`Move the second component into the first one's slot`, async () => {
    await displayBuilder.dragElement(
      rootComponents.nth(1),
      rootComponents.first().locator('[data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )

    // Re-parented: one component left at root, the other nested inside it.
    await expect(rootComponents).toHaveCount(1)
    await expect(nested).toHaveCount(1)
    await expect(components).toHaveCount(2)
  })

  await test.step(`Reject moving a component into its own descendant`, async () => {
    // The root component now contains the nested one, so the nested one's slot
    // sits inside the root component's own subtree.
    await displayBuilder.dragElement(
      rootComponents.first(),
      nested.locator('[data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )

    // Structure must be untouched: nothing lost, nothing re-parented.
    await expect(components).toHaveCount(2)
    await expect(rootComponents).toHaveCount(1)
    await expect(nested).toHaveCount(1)
  })

  // Without this, the previous step would also pass if the drag had silently
  // done nothing - a rejected move and a dead drag look identical. Moving the
  // nested component back out proves dragging still works at this point, so
  // the circular move really was refused rather than never attempted.
  await test.step(`Control: the nested component can still be moved back to root`, async () => {
    await displayBuilder.dragElement(nested, root, { x: 40, y: 15 })

    await expect(rootComponents).toHaveCount(2)
    await expect(components).toHaveCount(2)
  })

  await test.step(`Structure survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(rootComponents).toHaveCount(2)
  })
})
