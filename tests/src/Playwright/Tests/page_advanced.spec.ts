import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_page_layout' ])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Builder: Page Drag and move',
  { tag: [ '@display_builder', '@display_builder_page_layout', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = utils.createRandomString()

    await test.step(`Create Page Layout and login with Drush (fast)`, async () => {
      await displayBuilder.ceatePageLayoutDisplayBuilder(drupal, id)
      await drupal.loginAsAdminDrush()
      await page.goto(`${config.pageViewUrl.replace('{instance_id}', `test_${id}`)}`)
    })

    await test.step(`Manipulate instance in full highlight`, async () => {
      await displayBuilder.shoelaceReady()
      await displayBuilder.fullHighlight()

      const dropzoneRoot = page.locator('.db-dropzone--root').first()

      await displayBuilder.dragSimpleComponentsWithTextfield('I am Test in a slot!')

      await displayBuilder.dragElementFromLibraryById('Components', 'test_simple', dropzoneRoot)

      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', dropzoneRoot)

      await displayBuilder.dragElement(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
        page.locator(`.db-island-builder [data-slot-id="slot_1"]`).first(),
      )
    })

    await test.step(`Check Builder snapshots`, async () => {
      await expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'page_advanced_builder.aria.yml' })

      await page.getByRole('tab', { name: 'Layers', exact: true }).click();
      await expect(page.locator('.db-island-layers')).toMatchAriaSnapshot({ name: 'page_advanced_layers.aria.yml' })

      await page.getByRole('tab', { name: 'Logs', exact: true }).click();
      await expect(page.locator('.db-island-logs')).toMatchAriaSnapshot({ name: 'page_advanced_logs.aria.yml' })
    })

    await test.step(`Revert last change and check`, async () => {
      await page.locator('[data-island-action="undo"]').click()
      await page.locator('[data-island-action="undo"]').click()
      await displayBuilder.htmxReady()
  
      await expect(page.locator('.db-island-logs')).toMatchAriaSnapshot({ name: 'page_advanced_logs_step_minus_1.aria.yml' })
      await page.getByRole('tab', { name: 'Layers', exact: true }).click();
      await expect(page.locator('.db-island-layers')).toMatchAriaSnapshot({ name: 'page_advanced_layers_step_minus_1.aria.yml' })
      await page.getByRole('tab', { name: 'Builder', exact: true }).click();
      await expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'page_advanced_builder_step_minus_1.aria.yml' })
    })
  },
)
