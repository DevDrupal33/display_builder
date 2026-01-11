import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['display_builder_dev_tools'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Layers: Drag and move', { tag: ['@display_builder', '@display_builder_dev_tools'] }, async ({ page, drupal, displayBuilder }) => {
  const dbName = `test_${utils.createRandomString()}`

  await test.step(`Admin login`, async () => {
    await drupal.loginAsAdmin()
  })

  await test.step(`Create dev instance`, async () => {
    await displayBuilder.createDisplayBuilderFromUi(dbName)
  })

  await test.step(`Build instance`, async () => {
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    const dropzoneRoot = displayBuilder.getBuildLocator('.db-dropzone--root')

    // Perform drag operations in Builder tab first
    await displayBuilder.dragSimpleComponentsWithTextfield('I am a test textfield in a slot!')

    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      dropzoneRoot,
    )

    await displayBuilder.dragElementFromLibraryById(
      'Blocks',
      'textfield',
      dropzoneRoot,
    )

    await displayBuilder.dragElement(
      displayBuilder.getBuildLocator(`[data-node-type="textfield"]`).first(),
      displayBuilder.getBuildLocator(`[data-slot-id="slot_1"]`).first(),
    )

    // Switch to Layers tab to verify the layer hierarchy
    await page.getByRole('tab', { name: 'Layers' }).click()
    await displayBuilder.htmxReady()

    await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
      - text: Test simple Slot 1 Textfield Test simple Slot 1 Textfield
    `)
  })

})
