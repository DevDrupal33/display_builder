import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Preset round-trip: MenuPreset adds "Save as preset" to the right-click menu
// (an hx-prompt asks for a name and posts to api_save_preset, creating a
// PatternPreset entity); PresetLibraryPanel then lists saved presets as
// draggable placeholders in the Presets library tab, and dropping one back
// re-materialises its components. Proves save -> appears-in-library -> drop-back.
// @see \Drupal\display_builder\Plugin\display_builder\Island\MenuPreset
// @see \Drupal\display_builder\Plugin\display_builder\Island\PresetLibraryPanel
test('Save a preset and drop it back', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const components = page.locator('.db-island-builder [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileExtraId)
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

  await test.step(`Save it as a preset`, async () => {
    // The hx-prompt fires a native window.prompt(); answer it with the name.
    page.once('dialog', (dialog) => dialog.accept('My preset'))
    await components.first().click({ button: 'right', position: { x: 5, y: 5 } })
    await page.getByRole('menuitemcheckbox', { name: 'Save as preset' }).locator('slot').nth(1).click()
    await displayBuilder.htmxReady()
  })

  await test.step(`It appears in the Presets library`, async () => {
    await displayBuilder.openLibrariesTab('preset')
    await expect(page.locator('.db-island-preset_library [data-hx-vals]')).toHaveCount(1)
  })

  await test.step(`Dropping it back re-materialises its component`, async () => {
    await displayBuilder.dragElement(
      page.locator('.db-island-preset_library [data-hx-vals]').first(),
      page.locator('.db-island-builder > div.db-dropzone').first(),
      { x: 40, y: 15 },
    )
    await expect(components).toHaveCount(2)
  })

  await test.step(`The dropped preset survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(components).toHaveCount(2)
  })
})
