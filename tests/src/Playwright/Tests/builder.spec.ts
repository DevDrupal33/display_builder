import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Builder move tests', { tag: [ '@wip' ] }, async ({ page, drupal, displayBuilder }) => {
  // modules/display_builder_page_layout/tests/modules/display_builder_page_layout_test/config/optional/display_builder_page_layout.page_layout.builder.yml
  const instanceId = `builder`
  const viewUrl = `${config.pageViewUrl.replace('{instance_id}', instanceId)}`

  const result = page.locator(`.db-island-builder`)
  const dropzoneRoot = page.locator('.db-dropzone--root')

  // ID from config:
  // modules/display_builder_page_layout/tests/modules/display_builder_page_layout_test/config/optional/display_builder_page_layout.page_layout.builder.yml
  const component_1 = page.getByTestId('component_1')
  const component_1_slot = page.getByTestId('component_1_slot_1')
  const component_2 = page.getByTestId('component_2')
  const component_2_slot = page.getByTestId('component_2_slot_1')
  const component_3 = page.getByTestId('component_3')
  const component_3_slot = page.getByTestId('component_3_slot_1')
  const component_4 = page.getByTestId('component_4')
  const component_4_slot_1 = page.getByTestId('component_4_slot_2_1')
  const component_4_slot_2 = page.getByTestId('component_4_slot_2_2')

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
      - text: "Textfield: textfield_1 textfield_1 Test 1 component_1_textfield_1 Token: component_1_token_1 component_1_token_1 Textfield: component_1_textfield_2 component_1_textfield_2 Slot 1 Textfield: textfield_2 textfield_2 Test 1 component_2_textfield_1 Textfield: component_2_textfield_2 component_2_textfield_2 Slot 1 Textfield: textfield_3 textfield_3 Test 1 component_3_textfield_1 Token: component_3_token_2 component_3_token_2 Textfield: component_3_textfield_2 component_3_textfield_2 Slot 1 Textfield: textfield_4 textfield_4 Test 2 Token: component_4_token_1 component_4_token_1 Textfield: component_4_textfield_1 component_4_textfield_1 Slot 1 Textfield: component_4_textfield_2 component_4_textfield_2 Slot 2 Textfield: textfield_5 textfield_5 Base container"
    `)

    // Referesh and ensure no changes.
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    await expect(result).toMatchAriaSnapshot(`
      - text: "Block: Tabs"
      - 'button "Block: Tabs"'
      - text: "Textfield: textfield_1 textfield_1 Test 1 component_1_textfield_1 Token: component_1_token_1 component_1_token_1 Textfield: component_1_textfield_2 component_1_textfield_2 Slot 1 Textfield: textfield_2 textfield_2 Test 1 component_2_textfield_1 Textfield: component_2_textfield_2 component_2_textfield_2 Slot 1 Textfield: textfield_3 textfield_3 Test 1 component_3_textfield_1 Token: component_3_token_2 component_3_token_2 Textfield: component_3_textfield_2 component_3_textfield_2 Slot 1 Textfield: textfield_4 textfield_4 Test 2 Token: component_4_token_1 component_4_token_1 Textfield: component_4_textfield_1 component_4_textfield_1 Slot 1 Textfield: component_4_textfield_2 component_4_textfield_2 Slot 2 Textfield: textfield_5 textfield_5 Base container"
    `)
  })

  await test.step(`Move all in 1 slot`, async () => {
    await displayBuilder.dragManual(component_2, component_1_slot)
    await displayBuilder.dragManual(component_3, component_1_slot)
    await displayBuilder.dragManual(component_4, component_1_slot)

    await expect(result).toMatchAriaSnapshot(`
      - text: "Block: Tabs"
      - 'button "Block: Tabs"'
      - text: "Textfield: textfield_1 textfield_1 Test 1 component_1_textfield_1 Test 2 Token: component_4_token_1 component_4_token_1 Textfield: component_4_textfield_1 component_4_textfield_1 Slot 1 Textfield: component_4_textfield_2 component_4_textfield_2 Slot 2 Test 1 component_3_textfield_1 Test 1 component_2_textfield_1 Token: component_1_token_1 component_1_token_1 Textfield: component_1_textfield_2 component_1_textfield_2 Slot 1 Textfield: textfield_2 textfield_2 Textfield: component_2_textfield_2 component_2_textfield_2 Slot 1 Textfield: textfield_3 textfield_3 Token: component_3_token_2 component_3_token_2 Textfield: component_3_textfield_2 component_3_textfield_2 Slot 1 Textfield: textfield_4 textfield_4 Textfield: textfield_5 textfield_5 Base container"
    `)

    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    await expect(result).toMatchAriaSnapshot(`
      - text: "Block: Tabs"
      - 'button "Block: Tabs"'
      - text: "Textfield: textfield_1 textfield_1 Test 1 component_1_textfield_1 Test 2 Token: component_4_token_1 component_4_token_1 Textfield: component_4_textfield_1 component_4_textfield_1 Slot 1 Textfield: component_4_textfield_2 component_4_textfield_2 Slot 2 Test 1 component_3_textfield_1 Token: component_3_token_2 component_3_token_2 Textfield: component_3_textfield_2 component_3_textfield_2 Slot 1 Test 1 component_2_textfield_1 Textfield: component_2_textfield_2 component_2_textfield_2 Slot 1 Token: component_1_token_1 component_1_token_1 Textfield: component_1_textfield_2 component_1_textfield_2 Slot 1 Textfield: textfield_2 textfield_2 Textfield: textfield_3 textfield_3 Textfield: textfield_4 textfield_4 Textfield: textfield_5 textfield_5 Base container"
    `)
  })

  await test.step(`View the result page`, async () => {
    await page.goto(`test-builder`)
    await expect(page.locator('.page-wrapper')).toMatchAriaSnapshot({ name: 'builder-result.aria.yml' })
  })
})
