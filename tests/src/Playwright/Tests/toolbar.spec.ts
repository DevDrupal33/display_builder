import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

let dbName: string
// Click position required to avoid icon to intercept the click.
const position = { position: { x: 5, y: 5 } }

const key = {
  builder: 'b',
  libraries: 'l',
  logs: 'o',
  preview: 'p',
  layers: 'y',
  tree: 't',
  help: 'h',
  fullscreen: config.keyFullscreen,
  highlight: config.keyHighlight,
  undo: 'u',
  redo: 'r',
  clear: 'Shift+C',
  save: 'Shift+S',
  // restore: '',
  // reset: '',
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_dev_tools' ])
  await drupal.setPreprocessing({ css: false, javascript: false })
})

test.afterEach('Clean', async ({ displayBuilder }) => {
  await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
})

test('Toolbar buttons and keyboard', { tag: [ '@display_builder_dev_tools' ] }, async ({ page, drupal, displayBuilder }) => {
  dbName = `test_${utils.createRandomString()}`

  await test.step(`Admin login`, async () => {
    await drupal.loginAsAdmin()
  })

  await test.step(`Create dev instance`, async () => {
    await displayBuilder.createDisplayBuilderFromUi(dbName)
  })

  await test.step(`Minimal build in the instance`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'Blocks',
      'token',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )
    await displayBuilder.dragElementFromLibraryById(
      'Blocks',
      'token',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )
    await displayBuilder.closeDialog()
  })

  await test.step(`Undo / Redo / Clear`, async () => {
    // Test the undo/redo/clear buttons
    const builderToken = page.locator(`.db-island-builder [data-node-title="Token"]`)
    await expect(builderToken).toHaveCount(2)

    // Position required to avoid icon to intercept the click.
    const undo = page.locator('[data-island-action="undo"]')
    await undo.click(position)
    await expect(builderToken).toHaveCount(1)

    const redo = page.locator('[data-island-action="redo"]')
    await redo.click(position)
    await expect(builderToken).toHaveCount(2)

    const clear = page.locator('[data-island-action="clear"]')
    await clear.click(position)
    await expect(builderToken).toHaveCount(2)
    await expect(undo).toBeVisible()
    await expect(redo).toBeVisible()
    await expect(clear).not.toBeVisible()
  })

  // await test.step(`Keyboard Undo / Redo / Clear`, async () => {
    // @todo test is stuck on keyboard.
    // await displayBuilder.dragElementFromLibraryById('Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
    // await displayBuilder.closeDialog()
    // await expect(builderToken).toHaveCount(3)
    // await displayBuilder.keyboardShortcut(key.undo)
    // await displayBuilder.htmxReady()
    // await expect(builderToken).toHaveCount(2)
    // await displayBuilder.keyboardShortcut(key.redo)
    // await displayBuilder.htmxReady()
    // await expect(builderToken).toHaveCount(3)
    // await displayBuilder.keyboardShortcut(key.clear)
    // await displayBuilder.htmxReady()
    // await expect(builderToken).toHaveCount(3)
    // await expect(page.getByRole('button', { name: 'Undo' })).toBeVisible()
    // await expect(page.getByRole('button', { name: 'Redo' })).toBeVisible()
    // await expect(page.getByRole('button', { name: 'Clear' })).not.toBeVisible()
  // })

  await test.step(`Help`, async () => {
    const keyboard = page.locator('[data-island-action="help"]')
    const keyboardHelp = page.getByText('Keyboard help')

    await keyboard.click(position)
    await expect(keyboardHelp).toBeVisible()
    await keyboard.click(position)
    await expect(keyboardHelp).not.toBeVisible()
    await displayBuilder.keyboardShortcut(key.help)
    await expect(keyboardHelp).toBeVisible()
    await displayBuilder.keyboardShortcut(key.help)
    await expect(keyboardHelp).not.toBeVisible()
  })

  await test.step(`Fullscreen`, async () => {
    const fullscreen = page.locator('[data-island-action="fullscreen"]')
    const fullscreenIsOn = page.locator('.display-builder--fullscreen')

    await fullscreen.click(position)
    await expect(fullscreenIsOn).toBeVisible()
    await fullscreen.click(position)
    await expect(fullscreenIsOn).not.toBeVisible()
    await displayBuilder.keyboardShortcut(key.fullscreen)
    await expect(fullscreenIsOn).toBeVisible()
    await displayBuilder.keyboardShortcut(key.fullscreen)
    await expect(fullscreenIsOn).not.toBeVisible()
  })

  await test.step(`Highlight`, async () => {
    const highlight = page.locator('[data-island-action="highlight"]')
    const highlightIsOn = page.locator('.display-builder--highlight')

    await highlight.click(position)
    await expect(highlightIsOn).toBeVisible()
    await highlight.click(position)
    await expect(highlightIsOn).not.toBeVisible()
    await displayBuilder.keyboardShortcut(key.highlight)
    await expect(highlightIsOn).toBeVisible()
    await displayBuilder.keyboardShortcut(key.highlight)
    await expect(highlightIsOn).not.toBeVisible()
  })

  await test.step(`Switch viewport`, async () => {
    const switchViewport = page.locator('[data-island-action="viewport"]')
    const switchViewportList = page.locator('#listbox')

    await switchViewport.click()

    await page.getByRole('menuitem', { name: 'Extra small' }).locator('slot').nth(1).click()
    await expect(switchViewportList).not.toBeVisible()
    await expect(page.locator('.display-builder__main')).toHaveAttribute('style', 'max-width: 575px;')

    await switchViewport.click()
    await page.getByRole('menuitem', { name: 'Fluid' }).locator('slot').nth(1).click()
    await expect(switchViewportList).not.toBeVisible()
    await expect(page.locator('.display-builder__main')).toHaveAttribute('style', 'max-width: 100%;')
  })
})
