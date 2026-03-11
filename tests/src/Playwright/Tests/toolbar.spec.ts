import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

// Click position required to avoid icon to intercept the click.
const position = { position: { x: 5, y: 5 } }

const key = {
  builder: 'b',
  libraries: 'l',
  logs: 'o',
  preview: 'p',
  layers: 'y',
  tree: 't',
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
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
  // Breakpoint is required for viewport switcher.
  await drupal.installModules([ 'breakpoint' ])
})

// Buttons in toolbar configuration is based on display_builder.profile.test.yml
// Any change to the profile will be reflected here.
test(
  'Toolbar buttons',
  { tag: [ '@display_builder' ] },
  async ({ page, drupal, displayBuilder }) => {
    await test.step(`Create Page Layout and login`, async () => {
      await displayBuilder.initTestsWithPageLayout(drupal)
    })

    // Test highlight and fullscreen before any further tests to not conflict with
    // highlight or fullscreen switch in the test to make it easier for position
    // and error snapshot.
    await test.step(`Highlight`, async () => {
      const btn = page.locator('[data-island-action="highlight"]')
      await testToggleFeature(page, btn, '.display-builder--highlight')
    })

    await test.step(`Fullscreen`, async () => {
      const btn = page.locator('[data-island-action="fullscreen"]')
      await testToggleFeature(page, btn, '.display-builder--fullscreen')
    })

    await test.step(`Minimal build instance`, async () => {
      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', page.locator('.db-dropzone--root').first())
      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', page.locator('.db-dropzone--root').first())
      await displayBuilder.closeDialog()
    })

    await test.step(`Undo / Redo / Clear`, async () => {
      // Test the undo/redo/clear buttons
      const builderTextfield = page.locator(`.db-island-builder [data-node-type="textfield"]`)
      await expect(builderTextfield).toHaveCount(2)

      // Position required to avoid icon to intercept the click.
      const undo = page.locator('[data-island-action="undo"]')
      await undo.click(position)
      await expect(builderTextfield).toHaveCount(1)

      const redo = page.locator('[data-island-action="redo"]')
      await redo.click(position)
      await expect(builderTextfield).toHaveCount(2)

      const clear = page.locator('[data-island-action="clear"]')
      await clear.click(position)
      await expect(builderTextfield).toHaveCount(2)
      await expect(undo).toBeVisible()
      await expect(redo).toBeVisible()
      await expect(clear).toBeHidden()
    })

    // This is helping next tests.
    await test.step(`Set some values for next tests`, async () => {
      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
        'I am first',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
      )
      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).nth(1),
        'I am second',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
      )

      await page.getByRole('button', { name: 'Close' }).click()
      await displayBuilder.highlight()
    })

    await test.step(`Switch viewport (compact)`, async () => {
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

    // @todo help hover test
    // await test.step(`Help`, async () => {
    // })

    await test.step(`Libraries`, async () => {
      const btn = page.getByRole('button', { name: 'Libraries' })
      await testToggleFeature(page, btn, config.startDrawerID)
    })

    await test.step(`Tree`, async () => {
      const btn = page.getByRole('button', { name: 'Tree' })
      await testToggleFeature(page, btn, '.db-island-tree')
    })

    await test.step(`Layers`, async () => {
      const btn = page.getByRole('tab', { name: 'Layers' })
      await testToggleTab(page, btn, '.db-island-layers', 'layers')
    })

    await test.step(`Logs`, async () => {
      const btn = page.getByRole('tab', { name: 'Logs', exact: true })
      await testToggleTab(page, btn, '.db-island-logs', null)

      // const btn2 = page.getByRole('tab', { name: '[Test] Logs raw', exact: true })
      // await testToggleTab(page, btn2, '.db-island-test_logs_raw', 'logs_raw')
    })

    await test.step(`Preview`, async () => {
      const btn = page.getByRole('tab', { name: 'Preview' })
      await testToggleTab(page, btn, '.db-island-preview', 'preview')
    })

    async function testToggleFeature (page: Page, button: Locator, isOnLocator: string) {
      const isOn = page.locator(isOnLocator)

      await button.click(position)
      await displayBuilder.shoelaceReady()
      await expect(isOn).toBeVisible()
      await button.click(position)
      await displayBuilder.shoelaceReady()
      await expect(isOn).not.toBeVisible()
    }

    async function testToggleTab (page: Page, button: Locator, isOnLocator: string, name: string | null) {
      const builder = page.getByRole('tab', { name: 'Builder' })
      const isOn = page.locator(isOnLocator)

      await button.click(position)
      await displayBuilder.shoelaceReady()
      await expect(isOn).toBeVisible()

      if (name !== null) {
        await expect(page.locator(isOnLocator)).toMatchAriaSnapshot({ name: `toolbar-${name}.aria.yml` })
      }

      await builder.click(position)
      await displayBuilder.shoelaceReady()
      await expect(isOn).not.toBeVisible()
    }
  },
)

test(
  'Toolbar keyboard',
  { tag: [ '@display_builder' ] },
  async ({ page, drupal, displayBuilder }) => {
    await test.step(`Create Page Layout and login`, async () => {
      await displayBuilder.initTestsWithPageLayout(drupal)
    })

    // Test highlight and fullscreen before any further tests to not conflict with
    // highlight or fullscreen switch in the test to make it easier for position
    // and error snapshot.
    await test.step(`Highlight`, async () => {
      await testToggleFeature(page, '.display-builder--highlight', key.highlight)
    })

    await test.step(`Fullscreen`, async () => {
      await testToggleFeature(page, '.display-builder--fullscreen', key.fullscreen)
    })

    await test.step(`Minimal build instance`, async () => {
      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', page.locator('.db-dropzone--root').first())
      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', page.locator('.db-dropzone--root').first())
      await displayBuilder.closeDialog()
    })

    await test.step(`Keyboard Undo / Redo / Clear`, async () => {
      const builderTextfield = page.locator(`.db-island-builder [data-node-type="textfield"]`)

      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', page.locator('.db-dropzone--root').first())
      await displayBuilder.closeDialog()
      await expect(builderTextfield).toHaveCount(3)
      await displayBuilder.keyboardShortcut(key.undo)

      await expect(builderTextfield).toHaveCount(2)
      await displayBuilder.keyboardShortcut(key.redo)

      await expect(builderTextfield).toHaveCount(3)
      await displayBuilder.keyboardShortcut(key.clear)

      await expect(builderTextfield).toHaveCount(3)
      await expect(page.locator('[data-island-action="undo"]')).toBeVisible()
      await expect(page.locator('[data-island-action="redo"]')).toBeVisible()
      await expect(page.locator('[data-island-action="clear"]')).toBeHidden()
    })

    // This is helping next tests.
    await test.step(`Set some values for next tests`, async () => {
      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
        'I am first',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
      )
      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).nth(1),
        'I am second',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
      )

      await page.getByRole('button', { name: 'Close' }).click()
      await displayBuilder.highlight()
    })

    await test.step(`Libraries`, async () => {
      await testToggleFeature(page, config.startDrawerID, key.libraries)
    })

    await test.step(`Tree`, async () => {
      await testToggleFeature(page, '.db-island-tree', key.tree)
    })

    await test.step(`Layers`, async () => {
      await testToggleTab(page, '.db-island-layers', key.layers, 'layers')
    })

    await test.step(`Logs`, async () => {
      // @todo aria snapshot is hard with the table of logs, because of dates.
      await testToggleTab(page, '.db-island-logs', key.logs, null)
    })

    await test.step(`Preview`, async () => {
      await testToggleTab(page, '.db-island-preview', key.preview, 'preview')
    })

    async function testToggleFeature (page: Page, isOnLocator: string, keyShortcut: string) {
      const isOn = page.locator(isOnLocator)

      await displayBuilder.keyboardShortcut(keyShortcut)
      await expect(isOn).toBeVisible()
      await displayBuilder.keyboardShortcut(keyShortcut)
      await expect(isOn).not.toBeVisible()
    }

    async function testToggleTab (page: Page, isOnLocator: string, keyShortcut: string, name: string | null) {
      // const builder = page.getByRole('tab', { name: 'Builder' })
      const isOn = page.locator(isOnLocator)

      await displayBuilder.keyboardShortcut(keyShortcut)
      await expect(isOn).toBeVisible()

      if (name !== null) {
        await expect(page.locator(isOnLocator)).toMatchAriaSnapshot({ name: `keyboard-${name}.aria.yml` })
      }

      // await builder.click(position)
      await displayBuilder.keyboardShortcut(key.builder)
      await expect(isOn).not.toBeVisible()
    }
  },
)
