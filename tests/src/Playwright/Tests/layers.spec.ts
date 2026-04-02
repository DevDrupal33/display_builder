import { expect, Locator } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Layers move tests', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  // modules/display_builder_page_layout/tests/modules/display_builder_page_layout_test/config/optional/display_builder_page_layout.page_layout.layers.yml
  const instanceId = `layers`
  const viewUrl = `${config.pageViewUrl.replace('{instance_id}', instanceId)}`

  const result = page.locator(`.db-island-layers`)
  const dropzoneRoot = page.locator('.db-dropzone--root')

  // ID from config:
  // modules/display_builder_page_layout/tests/modules/display_builder_page_layout_test/config/optional/display_builder_page_layout.page_layout.layers.yml
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
    await page.goto(`${config.pageViewUrl.replace('{instance_id}', instanceId)}`)
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullscreen()

    await expect(result).toMatchAriaSnapshot(`
      - text: "Tabs Textfield: textfield_1 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Textfield: textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Textfield: textfield_4 Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Tabs Textfield: textfield_1 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Textfield: textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Textfield: textfield_4 Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Textfield: textfield_5"
    `)
  })

  await test.step(`Move all in 1 slot`, async () => {
    // await page.locator('.db-island-layers').screenshot({ path: 'move_all_0.png' })
    await displayBuilder.dragManual(component_2, component_1_slot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_all_1.png' })
    await displayBuilder.dragManual(component_3, component_1_slot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_all_2.png' })
    await displayBuilder.dragManual(component_4, component_1_slot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_all_3.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: "Tabs Textfield: textfield_1 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Tabs Textfield: textfield_1 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
  })

  await test.step(`Move back to root`, async () => {
    // await page.locator('.db-island-layers').screenshot({ path: 'move_back_0.png' })
    await displayBuilder.dragManual(component_2, dropzoneRoot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_back_1.png' })
    await displayBuilder.dragManual(component_3, dropzoneRoot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_back_2.png' })
    await displayBuilder.dragManual(component_4, dropzoneRoot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_back_3.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: "Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Tabs Textfield: textfield_1 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Tabs Textfield: textfield_1 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
  })

  await test.step(`Move nested`, async () => {
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_0.png' })
    await displayBuilder.dragManual(component_1, component_4_slot_2)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_1.png' })
    await displayBuilder.dragManual(component_2, component_1_slot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_2.png' })
    await displayBuilder.dragManual(component_3, component_2_slot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_3.png' })
    await displayBuilder.dragManual(component_4, component_3_slot)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_4.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: "Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Textfield: component_2_textfield_2 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: component_4_textfield_2 Tabs Textfield: textfield_1 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Textfield: component_2_textfield_2 Token: component_1_token_1 Textfield: component_1_textfield_2 Textfield: component_4_textfield_2 Tabs Textfield: textfield_1 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
  })

  await test.step(`Move nested group`, async () => {
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_group_0.png' })
    await displayBuilder.dragManual(component_1, component_4_slot_1)
    // await page.locator('.db-island-layers').screenshot({ path: 'move_nested_group_1.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: Test 2 Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Textfield: component_2_textfield_2 Token: component_1_token_1 Textfield: component_1_textfield_2 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Tabs Textfield: textfield_1 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: Test 2 Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: Slot 1 Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Textfield: component_2_textfield_2 Token: component_1_token_1 Textfield: component_1_textfield_2 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Tabs Textfield: textfield_1 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
  })

  await test.step(`Move back to root`, async () => {
    // await page.locator('.db-island-layers').screenshot({ path: 'move_back_2_0.png' })
    await displayBuilder.dragManual(component_3, dropzoneRoot)
    //  await page.locator('.db-island-layers').screenshot({ path: 'move_back_2_1.png' })
    await displayBuilder.dragManual(component_2, dropzoneRoot)
    //  await page.locator('.db-island-layers').screenshot({ path: 'move_back_2_2.png' })
    await displayBuilder.dragManual(component_1, dropzoneRoot)
    //  await page.locator('.db-island-layers').screenshot({ path: 'move_back_2_3.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Token: component_1_token_1 Textfield: component_1_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Tabs Textfield: textfield_1 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Token: component_1_token_1 Textfield: component_1_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Token: component_3_token_2 Textfield: component_3_textfield_2 Test 2 Slot 1 Token: component_4_token_1 Textfield: component_4_textfield_1 Slot 2 Textfield: component_4_textfield_2 Tabs Textfield: textfield_1 Textfield: textfield_2 Textfield: textfield_3 Textfield: textfield_4 Textfield: textfield_5"
    `)
  })

  await test.step(`View the result page`, async () => {
    await page.goto(`test-layers`)
    await expect(page.locator('.page-wrapper')).toMatchAriaSnapshot({ name: 'layers-result.aria.yml' })
    // await page.locator('.page-wrapper').screenshot({ path: 'final.png' })
  })
})
