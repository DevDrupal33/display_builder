import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'

import * as utils from '../utilities/utils'

import config from '../playwright.config.loader'

let dbName: string
// Click position required to avoid icon to intercept the click.
const position = { position: { x: 5, y: 5 } }

test.afterEach('Clean', async ({ displayBuilder }) => {
  await displayBuilder.deleteDisplayBuilderFromUi(dbName)
})

test(
  'Toolbar buttons',
  { tag: [ '@display_builder', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    dbName = `test_${utils.createRandomString(6)}`

    await drupal.loginAsAdmin()
    await displayBuilder.createDisplayBuilderFromUi(dbName)

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

    // Test the undo/redo/clear buttons
    const builderToken = page.locator(`.db-island-builder [data-instance-title="Token"]`)
    await expect(builderToken).toHaveCount(2)
    // Position required to avoid icon to intercept the click.
    const undo = page.getByRole('button', { name: '2' })
    await undo.click(position)
    await expect(builderToken).toHaveCount(1)

    const redo = page.locator('#button-1--2').getByRole('button', { name: '1' })
    await redo.click(position)
    await expect(builderToken).toHaveCount(2)

    const clear = page.getByRole('button', { name: 'Clear' })
    await clear.click(position)
    await expect(builderToken).toHaveCount(2)
    await expect(page.getByRole('button', { name: 'Undo' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Redo' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Clear' })).not.toBeVisible()

    // @todo test is stuck on keyboard.
    // await displayBuilder.dragElementFromLibraryById('Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
    // await displayBuilder.closeDialog()
    // await expect(builderToken).toHaveCount(3)
    // await displayBuilder.keyboardShortcut('u')
    // await displayBuilder.htmxReady()
    // await expect(builderToken).toHaveCount(2)
    // await displayBuilder.keyboardShortcut('r')
    // await displayBuilder.htmxReady()
    // await expect(builderToken).toHaveCount(3)
    // await displayBuilder.keyboardShortcut('Shift+C')
    // await displayBuilder.htmxReady()
    // await expect(builderToken).toHaveCount(3)
    // await expect(page.getByRole('button', { name: 'Undo' })).toBeVisible()
    // await expect(page.getByRole('button', { name: 'Redo' })).toBeVisible()
    // await expect(page.getByRole('button', { name: 'Clear' })).not.toBeVisible()

    const keyboard = page.getByRole('button', { name: 'Keyboard help' })
    const keyboardHelp = page.getByText('Keyboard help')

    await keyboard.click(position)
    await expect(keyboardHelp).toBeVisible()
    await keyboard.click(position)
    await expect(keyboardHelp).not.toBeVisible()
    await displayBuilder.keyboardShortcut('h')
    await expect(keyboardHelp).toBeVisible()
    await displayBuilder.keyboardShortcut('h')
    await expect(keyboardHelp).not.toBeVisible()

    const fullscreen = page.getByRole('button', { name: 'Display the builder as fullscreen.' })
    const fullscreenIsOn = page.locator('.display-builder--fullscreen')

    await fullscreen.click(position)
    await expect(fullscreenIsOn).toBeVisible()
    await fullscreen.click(position)
    await expect(fullscreenIsOn).not.toBeVisible()
    await displayBuilder.keyboardShortcut('Shift+M')
    await expect(fullscreenIsOn).toBeVisible()
    await displayBuilder.keyboardShortcut('Shift+M')
    await expect(fullscreenIsOn).not.toBeVisible()

    const highlight = page.getByRole('button', { name: 'Highlight components, blocks and slots.' })
    const highlightIsOn = page.locator('.display-builder--highlight')

    await highlight.click(position)
    await expect(highlightIsOn).toBeVisible()
    await highlight.click(position)
    await expect(highlightIsOn).not.toBeVisible()
    await displayBuilder.keyboardShortcut('Shift+H')
    await expect(highlightIsOn).toBeVisible()
    await displayBuilder.keyboardShortcut('Shift+H')
    await expect(highlightIsOn).not.toBeVisible()

    const switchViewport = page.locator(`#island-${dbName}-viewport`)
    const switchViewportList = page.locator('#listbox')

    await switchViewport.click()

    await page.getByRole('option', { name: 'Extra small' }).locator('slot').nth(1).click()
    await expect(switchViewportList).not.toBeVisible()
    await expect(page.locator('.display-builder__main')).toHaveAttribute('style', 'max-width: 575px;')

    await switchViewport.click()
    await page.getByRole('option', { name: 'Fluid' }).locator('slot').nth(1).click()
    await expect(switchViewportList).not.toBeVisible()
    await expect(page.locator('.display-builder__main')).toHaveAttribute('style', 'max-width: 100%;')
  }
)
