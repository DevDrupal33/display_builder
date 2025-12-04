import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['display_builder_dev_tools'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Drag and move', { tag: ['@display_builder_dev_tools'] }, async ({ page, drupal, displayBuilder }) => {
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

    await displayBuilder.dragSimpleComponentsWithToken('I am a test token in a slot in a Page Layout!')

    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )

    await displayBuilder.dragElementFromLibraryById(
      'Blocks',
      'token',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )

    await displayBuilder.dragElement(
      page.locator(`.db-island-builder [data-node-title^="Token"]`).first(),
      page.locator(`.db-island-builder [data-slot-id="slot_1"]`).first(),
    )

    await expect(page.locator(`.db-island-builder`)).toMatchAriaSnapshot(`
      - text: Test simple
      - 'heading \"label: none\" [level=5]'
      - text: Token
      - button \"Token\"
      - text: Slot 1
      - button \"Click me\"
      - text: Test simple
      - 'heading \"label: none\" [level=5]'
      - text: \"Token: I am a test token in a slot in a Page Layout! I am a test token in a slot in a Page Layout! Slot 1\"
      - button \"Click me\"
    `)
  })

})
