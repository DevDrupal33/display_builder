

import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_dev_tools' ])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Layers: Drag and move',
  { tag: [ '@display_builder', '@display_builder_dev_tools' ] },
  async ({ page, drupal, displayBuilder }) => {
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

      await page.getByRole('tab', { name: 'Layers' }).click()
      await displayBuilder.htmxReady()

      await displayBuilder.dragSimpleComponentsWithTextfield('First textfield', '.db-island-layers')
      await displayBuilder.dragSimpleComponentsWithTextfield('Second textfield', '.db-island-layers')

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
      - text: "Test simple Slot 1 Textfield: Second textfield Test simple Slot 1 Textfield: First textfield"
    `)

      await displayBuilder.dragSimpleComponentsWithTextfield('Third textfield', '.db-island-layers')

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).first(),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).first(),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
      - text: "Test simple Slot 1 Textfield: Third textfield Test simple Slot 1 Textfield: Second textfield Test simple Slot 1 Textfield: First textfield"
    `)

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).first(),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).nth(1),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
      - text: "Test simple Slot 1 Test simple Slot 1 Textfield: Third textfield Textfield: Second textfield Test simple Slot 1 Textfield: First textfield"
    `)

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).nth(1),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).first(),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
      - text: "Test simple Slot 1 Textfield: Second textfield Test simple Slot 1 Textfield: Third textfield Test simple Slot 1 Textfield: First textfield"
    `)

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).nth(2),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).nth(0),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
      - text: "Test simple Slot 1 Textfield: First textfield Textfield: Second textfield Test simple Slot 1 Textfield: Third textfield Test simple Slot 1"
    `)
    })
  },
)
