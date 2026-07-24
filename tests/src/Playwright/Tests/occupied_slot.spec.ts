import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Dropping into a slot that already has content must append (before or after
// the existing children), not be refused. Isolated in its own file because the
// Wireframe panel currently no-ops on exactly this case, and keeping it
// separate makes it obvious which panel and which drop kind broke.
//
// Two distinct drop kinds are covered, because they take different code paths:
//   a) a NEW node dragged in from the library,
//   b) an EXISTING node moved from elsewhere in the tree.

test('Occupied slot - drops from the library', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const root = page.locator('.db-island-builder > div.db-dropzone').first()
  const component = page.locator('.db-island-builder [data-test="test_simple"]').first()
  const slotTextfields = component.locator('[data-slot-id="slot_1"] [data-node-type="textfield"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await displayBuilder.highlight()

  await test.step(`Drop a component and fill its slot once`, async () => {
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      component.locator('[data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )
    await expect(slotTextfields).toHaveCount(1)
  })

  await test.step(`A second drop into the now-occupied slot appends`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      component.locator('[data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )
    await expect(slotTextfields).toHaveCount(2)
  })

  await test.step(`A third drop keeps appending`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      component.locator('[data-slot-id="slot_1"]').first(),
      { x: 40, y: 15 },
    )
    await expect(slotTextfields).toHaveCount(3)
  })

  await test.step(`Appended content survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(slotTextfields).toHaveCount(3)
  })
})

test('Occupied slot - moving an existing node in', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const root = page.locator('.db-island-builder > div.db-dropzone').first()
  const component = page.locator('.db-island-builder [data-test="test_simple"]').first()
  const slot = component.locator('[data-slot-id="slot_1"]').first()
  const slotTextfields = component.locator('[data-slot-id="slot_1"] [data-node-type="textfield"]')
  const rootTextfields = page.locator('.db-island-builder > div.db-dropzone > [data-node-type="textfield"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await displayBuilder.highlight()

  await test.step(`Build a component with an occupied slot, plus a textfield at root`, async () => {
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('block', 'textfield', slot, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('block', 'textfield', root, { x: 40, y: 15 })

    await expect(slotTextfields).toHaveCount(1)
    await expect(rootTextfields).toHaveCount(1)
  })

  await test.step(`Move the root textfield into the occupied slot`, async () => {
    await displayBuilder.dragElement(rootTextfields.first(), slot, { x: 40, y: 15 })

    // Appended next to the existing child, and gone from the root.
    await expect(slotTextfields).toHaveCount(2)
    await expect(rootTextfields).toHaveCount(0)
  })

  await test.step(`The move survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(slotTextfields).toHaveCount(2)
    await expect(rootTextfields).toHaveCount(0)
  })
})

// The same two drops as the Builder tests above, done in the Scaffold panel.
//
// Drop position matters here in a way it does not in the Builder. A Scaffold
// row is ~30px tall and its slot dropzone is barely taller, so a drop at y=15
// lands on the dead centre of the existing child - and with SortableJS's
// swapThreshold of 0.65 the centre band is ambiguous (neither "insert before"
// nor "insert after"), so the drop silently does nothing. Builder components
// are ~100px tall, so the same y=15 reads as "near the top" and works.
//
// Drop near the top edge instead. Anything approaching the middle of a compact
// row is unreliable by design, not by bug.
// @see components/dropzone/dropzone.js for the Sortable thresholds.
test('Occupied slot - Scaffold panel', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const scaffold = page.locator('.db-island-scaffold')
  const root = scaffold.locator('.db-dropzone--root').first()
  const component = scaffold.locator('[data-testid^="layer_"]').first()
  const slot = component.locator('[data-slot-id="slot_1"]').first()
  const slotTextfields = component.locator('[data-slot-id="slot_1"] [data-node-type="textfield"]')
  const rootTextfields = scaffold.locator('.db-dropzone--root > [data-node-type="textfield"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileScaffoldId)
    await expect(scaffold).toBeVisible()
  })

  await test.step(`Build a component with an empty slot, plus two textfields at root`, async () => {
    await displayBuilder.dragElementFromLibraryById('component', 'test_simple', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('block', 'textfield', root, { x: 40, y: 15 })
    await displayBuilder.dragElementFromLibraryById('block', 'textfield', root, { x: 40, y: 15 })

    await expect(slotTextfields).toHaveCount(0)
    await expect(rootTextfields).toHaveCount(2)
  })

  // Control: proves moves in the Wireframe panel work at all, so a failure in
  // the next step is specifically about the slot already having content and not
  // about Wireframe moves being dead in general.
  await test.step(`Control: move into the EMPTY slot`, async () => {
    await displayBuilder.dragElement(rootTextfields.first(), slot, { x: 40, y: 4 })

    await expect(slotTextfields).toHaveCount(1)
    await expect(rootTextfields).toHaveCount(1)
  })

  await test.step(`Move into the now-OCCUPIED slot`, async () => {
    await displayBuilder.dragElement(rootTextfields.first(), slot, { x: 40, y: 4 })

    await expect(slotTextfields).toHaveCount(2)
    await expect(rootTextfields).toHaveCount(0)
  })
})
