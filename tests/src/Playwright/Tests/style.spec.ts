import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Style', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal)
  })

  await test.step('Drag component', async () => {
    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      page.locator(`.db-island-builder > div.db-dropzone`).first(),
      { x: 40, y: 15 },
    )
  })

  await test.step(`Apply style on component`, async () => {
    await page.getByRole('heading', { name: 'label: none' }).first().click()

    await expect(page.getByRole('tab', { name: 'Styles', exact: true })).toBeVisible()

    await page.getByRole('tab', { name: 'Styles', exact: true }).click()
    await page.getByRole('button', { name: 'Style category 1' }).click()
    await page.getByRole('group', { name: 'Test style 1' }).getByLabel('- None -').click()
    const styleOption = page.locator(`input[value="test-style-1"]`)
    await styleOption.click()

    await displayBuilder.htmxReady()

    // Apply style extra class.
    await page.getByRole('textbox', { name: 'Extra classes' }).fill('extra-comp-1 extra-comp-2')

    // Click somewhere for htmx submit
    await page.getByRole('tab', { name: 'Builder' }).click()
    await displayBuilder.shoelaceReady()
    await displayBuilder.htmxReady()
  })

  await test.step('Drag block', async () => {
    const slotTarget = page.locator(`.db-island-builder [data-slot-id="slot_1"]`).first()

    await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', slotTarget, { x: 40, y: 15 })
    await page.getByRole('tab', { name: 'Config', exact: true }).click()
    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
      'Test style',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ],
    )
  })

  await test.step(`Apply style on textfield`, async () => {
    await page.getByRole('tab', { name: 'Styles', exact: true }).click()

    await page.getByRole('button', { name: 'Style category 2' }).click()
    await page.getByRole('group', { name: 'Test style 2' }).getByLabel('- None -').click()
    const styleOption = page.locator(`input[value="h2"]`)
    await styleOption.click()
    await displayBuilder.htmxReady()

    // Apply style extra class.
    await page.getByRole('textbox', { name: 'Extra classes' }).fill('extra-block-1 extra-block-2')

    // Click somewhere for htmx submit
    await page.getByRole('tab', { name: 'Builder' }).click()
    await displayBuilder.shoelaceReady()
    await displayBuilder.htmxReady()
  })

  await test.step(`Check result`, async () => {
    await displayBuilder.closeDialog('both')
    await page.getByRole('tab', { name: 'Preview' }).click()
    await displayBuilder.shoelaceReady()

    // Ensure styles are applied
    await expect(page.locator(`.db-island-preview [data-test="test_simple"]`)).toHaveClass(
      /test-style-1 extra-comp-1 extra-comp-2 test_simple/,
    )
    await expect(page.locator(`.db-island-preview [data-test="test_simple_slot"] > div`)).toHaveClass(
      /h2 extra-block-1 extra-block-2/,
    )
    await displayBuilder.expectPreviewAriaSnapshot('style.aria.yml')
  })
})
