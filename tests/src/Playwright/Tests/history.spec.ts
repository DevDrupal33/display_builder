import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Undo/redo/clear is Instance-entity core (history is held on the Instance, not
// in the DOM), so it belongs in @base. The exhaustive toolbar/keyboard sweep
// stays in @extra in toolbar.spec.ts.
//
// Uses the "Test full" profile: `history` is status:false everywhere else.
test('History undo redo clear', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  // Click position required to avoid the icon intercepting the click.
  const position = { position: { x: 5, y: 5 } }

  const textfields = page.locator(`.db-island-builder [data-node-type="textfield"]`)
  const undo = page.locator('[data-island-action="undo"]')
  const redo = page.locator('[data-island-action="redo"]')
  const clear = page.locator('[data-island-action="clear"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileMinId)
  })

  await test.step(`Build a minimal instance`, async () => {
    await displayBuilder.highlight()
    await displayBuilder.dragTextfield()
    await expect(textfields).toHaveCount(3)
  })

  await test.step(`Undo removes the last drop`, async () => {
    await undo.click(position)
    await expect(textfields).toHaveCount(2)
  })

  await test.step(`Redo restores it`, async () => {
    await redo.click(position)
    await expect(textfields).toHaveCount(3)
  })
})
