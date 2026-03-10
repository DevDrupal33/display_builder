

import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_page_layout' ])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Layers: Drag and move',
  { tag: [ '@display_builder', '@display_builder_page_layout' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = `test_${utils.createRandomString()}`

    await test.step(`Create Page Layout and login with Drush (fast)`, async () => {
      await displayBuilder.createPageLayoutDisplayBuilder(drupal, id)
      await drupal.loginAsAdminDrush()
      await page.goto(`${config.pageViewUrl.replace('{instance_id}', `test_${id}`)}`)
    })

    await test.step(`Prepare instance layers`, async () => {
      await displayBuilder.shoelaceReady()
      await displayBuilder.fullHighlight()

      await page.getByRole('tab', { name: 'Layers' }).click()
      await displayBuilder.htmxReady()

      await page
        .getByText('Page layout (from active')
        .click({ button: 'right', position: { x: 40, y: 10 } })
      await page.getByRole('menuitemcheckbox', { name: 'Remove Page layout' }).locator('slot').nth(1).click()
    })

    await test.step(`Add components`, async () => {
      await displayBuilder.dragComponentsAndTextfield('Text 1', '.db-island-layers')
      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Text 1"
      `)

      await displayBuilder.dragComponentsAndTextfield('Text 2', '.db-island-layers')
      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Text 2 Test simple Slot 1 Textfield: Text 1"
      `)

      await displayBuilder.dragComponentsAndTextfield('Text 3', '.db-island-layers')
      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Text 3 Test simple Slot 1 Textfield: Text 2 Test simple Slot 1 Textfield: Text 1"
      `)
    })

    await test.step(`Move 1`, async () => {
      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).first(),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).nth(1),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Test simple Slot 1 Textfield: Text 3 Textfield: Text 2 Test simple Slot 1 Textfield: Text 1"
      `)
    })

    await test.step(`Move 2`, async () => {
      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).nth(1),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).first(),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Test simple Slot 1 Textfield: Text 3 Textfield: Text 2 Test simple Slot 1 Textfield: Text 1"
      `)
    })

    await test.step(`Move 3`, async () => {
      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).nth(2),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).nth(0),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Text 1 Test simple Slot 1 Textfield: Text 3 Textfield: Text 2 Test simple Slot 1"
      `)
    })
  },
)
