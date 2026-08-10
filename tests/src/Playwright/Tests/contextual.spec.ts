import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Duplicate and remove are destructive tree operations, so a lean round-trip
// belongs in @base. The exhaustive contextual-menu walk with nested components
// and aria snapshots stays in @extra in secondary.spec.ts.
test('Contextual duplicate and remove', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const components = page.locator('.db-island-builder [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`Drop a component`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(components).toHaveCount(1)
  })

  await test.step(`Duplicate it`, async () => {
    await components.first().click({ button: 'right', position: { x: 5, y: 5 } })
    await page.getByRole('menuitemcheckbox', { name: 'Duplicate' }).locator('slot').nth(1).click()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(2)
  })

  await test.step(`Remove the copy`, async () => {
    await components.nth(1).click({ button: 'right', position: { x: 5, y: 5 } })
    await page.getByRole('menuitemcheckbox', { name: 'Remove' }).locator('slot').nth(1).click()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(1)
  })

  await test.step(`The remaining component survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(1)
  })
})

// Keyboard Delete acts on the selected node, repeatedly - three times, because
// the bug this covers only bit from the *third* delete on.
//
// Clicking a node marks every element carrying its id across all panels
// (@see js/sidebar.js's toggleOpenClassOnClick), a hidden panel is only
// flagged stale rather than re-rendered when the tree changes
// (@see js/deferred_islands.js), and the lookup used to take the first match
// in *document order*. So the id it resolved came from a hidden panel and, once
// that node had been deleted, was a ghost of something the server no longer
// has - every later Delete press re-targeted the same dead id and did nothing.
//
// Three conditions have to hold together or this passes against the broken
// code, which it did twice while being written:
//  - a node-bearing panel other than the canvas exists, and is ordered *before*
//    it, or document order finds the canvas' own live node first. Hence the
//    Tree profile (tree weight -7, builder -6), not the usual builder-only one.
//  - that panel is hidden behind another tab, so it is never re-rendered.
//  - it already held these nodes when it was last rendered, hence the reload
//    below - a panel that stayed hidden while they were dropped never had them.
test('Keyboard delete repeats', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const components = page.locator('.db-island-builder [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileTreeId)
  })

  await test.step(`Drop three components`, async () => {
    for (let i = 0; i < 3; i++) {
      await displayBuilder.dragElementFromLibraryById(
        'component',
        'test_simple',
        page.locator('.db-island-builder .db-dropzone--root').first(),
        { x: 40, y: 15 },
      )
    }
    await expect(components).toHaveCount(3)
  })

  // The hidden panel only holds these nodes if it was rendered while they
  // already existed - which is the ordinary case (open a builder on existing
  // content), but not the case for a panel that stayed hidden while they were
  // dropped one by one. Reload to get the realistic starting point.
  await test.step(`Reload so the hidden Tree panel holds the nodes too`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(3)
  })

  // Deleting the first one each time, so every round selects a node the
  // previous round never touched.
  for (let round = 3; round > 0; round--) {
    await test.step(`Select and delete, ${round} left`, async () => {
      await components.first().click({ position: { x: 5, y: 5 } })
      await displayBuilder.shoelaceReady()
      await page.keyboard.press('Delete')
      await displayBuilder.shoelaceReady()
      await expect(components).toHaveCount(round - 1)
    })
  }

  await test.step(`The deletions survive a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(0)
  })
})

// Duplicate (mod+d), copy (mod+c) and paste (mod+v) act on the selected node
// without opening the right-click menu, resolving its slot context the same way
// the menu does. ControlOrMeta is Playwright's platform key: Meta on macOS,
// Control elsewhere - exactly what the "mod" token resolves to
// (@see components/display_builder/js/keyboard.js).
test('Keyboard copy, paste and duplicate', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const components = page.locator('.db-island-builder [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`Drop a component`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(components).toHaveCount(1)
  })

  await test.step(`Duplicate the selection with the keyboard`, async () => {
    await components.first().click({ position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await page.keyboard.press('ControlOrMeta+d')
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(2)
  })

  await test.step(`Copy the selection and paste it`, async () => {
    await components.first().click({ position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await page.keyboard.press('ControlOrMeta+c')
    await page.keyboard.press('ControlOrMeta+v')
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(3)
  })

  await test.step(`The additions survive a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(3)
  })
})
