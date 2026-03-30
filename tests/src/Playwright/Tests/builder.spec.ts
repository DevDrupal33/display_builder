import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Builder move tests', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  // display_builder_test/config/optional/display_builder_page_layout.page_layout.builder.yml
  const dbName = `builder`
  const viewUrl = `${config.pageViewUrl.replace('{instance_id}', dbName)}`

  const result = page.locator(`.db-island-builder`)
  const dropzoneRoot = page.locator('.db-dropzone--root')

  // ID from config:
  // display_builder_test/config/optional/display_builder_page_layout.page_layout.layers.yml
  const test_1 = page.getByTestId('test_1')
  const test_1_slot = page.getByTestId('test_1_slot_1')
  const test_2 = page.getByTestId('test_2')
  const test_2_slot = page.getByTestId('test_2_slot_1')
  const test_3 = page.getByTestId('test_3')
  const test_3_slot = page.getByTestId('test_3_slot_1')
  const test_4 = page.getByTestId('test_4')
  const test_4_slot_1 = page.getByTestId('test_4_slot_2_1')
  const test_4_slot_2 = page.getByTestId('test_4_slot_2_2')

  await test.step(`User login`, async () => {
    await displayBuilder.createUserAndLogin(drupal)
  })

  await test.step(`Prepare instance`, async () => {
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    await expect(result).toMatchAriaSnapshot(`
        - text: "Block: Tabs"
        - 'button "Block: Tabs"'
        - text: "Textfield: foo foo Test 1 Component 1 Token: corge corge Textfield: grault grault Slot 1 Textfield: bar bar Test 1 Component 2 Textfield: garply garply Slot 1 Textfield: baz baz Test 1 Component 3 Token: waldo waldo Textfield: fred fred Slot 1 Textfield: quux quux Test 2 Token: plugh plugh Textfield: xyzzy xyzzy Slot 1 Textfield: thud thud Slot 2 Textfield: quux quux Base container"
      `)

    // Referesh and ensure no changes.
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    await expect(result).toMatchAriaSnapshot(`
        - text: "Block: Tabs"
        - 'button "Block: Tabs"'
        - text: foo Component 1 corge grault bar Component 2 garply baz Component 3 waldo fred quux plugh xyzzy thud quux
      `)
  })

  await test.step(`Move all in 1 slot`, async () => {
    await displayBuilder.dragManual(test_2, test_1_slot)
    await displayBuilder.dragManual(test_3, test_1_slot)
    await displayBuilder.dragManual(test_4, test_1_slot)

    await expect(result).toMatchAriaSnapshot(`
        - 'button "Block: Tabs"'
        - text: "foo Component 1 Token: corge corge grault bar Component 2 garply baz Component 3 waldo fred quux plugh xyzzy thud quux"
      `)

    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    await expect(result).toMatchAriaSnapshot(`
        - 'button "Block: Tabs"'
        - text: "foo Component 1 Token: corge corge grault bar baz Component 2 garply quux plugh xyzzy thud Component 3 waldo fred quux"
      `)
  })

  await test.step(`View the result page`, async () => {
    await page.goto(`test-builder`)
    await expect(page.locator('.page-wrapper')).toMatchAriaSnapshot({ name: 'builder-result.aria.yml' })
  })
})
