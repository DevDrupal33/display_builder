import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['display_builder_dev_tools'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Builder: Drag and move', { tag: ['@display_builder', '@display_builder_dev_tools'] }, async ({ page, drupal, displayBuilder }) => {
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

    const dropzoneRoot = page.locator('.db-dropzone--root').first()

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
      page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
      page.locator(`.db-island-builder [data-slot-id="slot_1"]`).first(),
    )

    await expect(page.locator(`.db-island-builder`)).toMatchAriaSnapshot(`
      - text: Test simple
      - 'heading "label: none" [level=5]'
      - text: Textfield
      - button "Textfield"
      - text: Slot 1
      - button "Click me"
      - text: Test simple
      - 'heading "label: none" [level=5]'
      - text: "Textfield: I am a test textfield... I am a test textfield in a slot! Slot 1"
      - button "Click me"
      - text: Base container
    `)
  })

})
