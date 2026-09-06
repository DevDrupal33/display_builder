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

  // Base-tier smoke check for the logs dropdown merged onto this same island
  // (@see https://www.drupal.org/project/display_builder/issues/3620417) -
  // the exhaustive open/close sweep stays in @extra in toolbar.spec.ts.
  const logsPanel = page.locator('.db-logs-dropdown')

  await test.step(`Logs dropdown lists the steps just built`, async () => {
    await page.getByTestId('logs').click(position)
    await expect(logsPanel).toBeVisible()
    // At least the present step and the drag that built the instance.
    expect(await logsPanel.locator('tbody tr').count()).toBeGreaterThanOrEqual(2)
  })

  await test.step(`Logs dropdown stays open across an undo`, async () => {
    // Undo sits outside the dropdown and rebuilds the whole island, either
    // of which would close a stock sl-dropdown: this one is pinned open so
    // the log can be watched while stepping through it.
    await undo.click(position)
    await expect(textfields).toHaveCount(2)
    await expect(logsPanel).toBeVisible()
  })

  await test.step(`Close button closes the logs dropdown`, async () => {
    await page.getByTestId('logs_close').click()
    await expect(logsPanel).not.toBeVisible()
  })
})
