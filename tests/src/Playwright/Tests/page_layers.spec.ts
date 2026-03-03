

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
  { tag: [ '@display_builder', '@display_builder_page_layout', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = `test_${utils.createRandomString()}`

    await test.step(`Create Page Layout and login with Drush (fast)`, async () => {
      await displayBuilder.createPageLayoutDisplayBuilder(drupal, id)
      await drupal.loginAsAdminDrush()
      await page.goto(`${config.pageViewUrl.replace('{instance_id}', `test_${id}`)}`)
    })

    await test.step(`Manipulate instance layers`, async () => {
      await displayBuilder.shoelaceReady()
      await displayBuilder.fullHighlight()

      await page.getByRole('tab', { name: 'Layers' }).click()
      await displayBuilder.htmxReady()

      await displayBuilder.dragSimpleComponentsWithTextfield('First textfield', '.db-island-layers')
      await displayBuilder.dragSimpleComponentsWithTextfield('Second textfield', '.db-island-layers')

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Second textfield Test simple Slot 1 Textfield: First textfield Page layout (from active theme) Header [Page] Title Tabs Pre-content Tabs Breadcrumb Breadcrumbs Highlighted Messages Help Empty slot Content Primary admin actions [Page] Main content"
      `)

      await displayBuilder.dragSimpleComponentsWithTextfield('Third textfield', '.db-island-layers')

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).first(),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).first(),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Third textfield Test simple Slot 1 Textfield: Second textfield Test simple Slot 1 Textfield: First textfield Page layout (from active theme) Header [Page] Title Tabs Pre-content Tabs Breadcrumb Breadcrumbs Highlighted Messages Help Empty slot Content Primary admin actions [Page] Main content"
      `)

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).first(),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).nth(1),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Test simple Slot 1 Textfield: Third textfield Textfield: Second textfield Test simple Slot 1 Textfield: First textfield Page layout (from active theme) Header [Page] Title Tabs Pre-content Tabs Breadcrumb Breadcrumbs Highlighted Messages Help Empty slot Content Primary admin actions [Page] Main content"
      `)

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).nth(1),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).first(),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: Second textfield Test simple Slot 1 Textfield: Third textfield Test simple Slot 1 Textfield: First textfield Page layout (from active theme) Header [Page] Title Tabs Pre-content Tabs Breadcrumb Breadcrumbs Highlighted Messages Help Empty slot Content Primary admin actions [Page] Main content"
      `)

      await displayBuilder.dragElement(
        page.locator(`.db-island-layers [data-node-type="textfield"]`).nth(2),
        page.locator(`.db-island-layers [data-slot-id="slot_1"]`).nth(0),
      )

      await expect(page.locator(`.db-island-layers`)).toMatchAriaSnapshot(`
        - text: "Test simple Slot 1 Textfield: First textfield Textfield: Second textfield Test simple Slot 1 Textfield: Third textfield Test simple Slot 1 Page layout (from active theme) Header [Page] Title Tabs Pre-content Tabs Breadcrumb Breadcrumbs Highlighted Messages Help Empty slot Content Primary admin actions [Page] Main content"
      `)
    })
  },
)
