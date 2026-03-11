import { expect, Locator } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Layers move tests',
  { tag: [ '@display_builder' ] },
  async ({ page, drupal, displayBuilder }) => {
    // display_builder_test/config/optional/display_builder_page_layout.page_layout.layers.yml
    const dbName = `layers`
    const viewUrl = `${config.pageViewUrl.replace('{instance_id}', dbName)}`

    const result = page.locator(`.db-island-layers`)
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
      await page.goto(`${config.pageViewUrl.replace('{instance_id}', dbName)}`)
      await displayBuilder.shoelaceReady()
      await displayBuilder.fullscreen()

      await expect(result).toMatchAriaSnapshot(`
        - text: "Tabs Textfield: foo Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Token: corge Textfield: grault Textfield: bar Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Textfield: baz Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Textfield: quux Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Textfield: quux"
      `)
      await page.goto(viewUrl)
      await displayBuilder.shoelaceReady()
      await expect(result).toMatchAriaSnapshot(`
        - text: "Tabs Textfield: foo Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Token: corge Textfield: grault Textfield: bar Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Textfield: baz Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Textfield: quux Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Textfield: quux"
      `)
    })

    await test.step(`Move all in 1 slot`, async () => {
      await displayBuilder.dragManual(test_2, test_1_slot)
      await displayBuilder.dragManual(test_3, test_1_slot)
      await displayBuilder.dragManual(test_4, test_1_slot)

      await expect(result).toMatchAriaSnapshot(`
        - text: "Tabs Textfield: foo Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Token: corge Textfield: grault Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
      await page.goto(viewUrl)
      await displayBuilder.shoelaceReady()
      await expect(result).toMatchAriaSnapshot(`
        - text: "Tabs Textfield: foo Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Token: corge Textfield: grault Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
    })

    await test.step(`Move back to root`, async () => {
      await displayBuilder.dragManual(test_2, dropzoneRoot)
      await displayBuilder.dragManual(test_3, dropzoneRoot)
      await displayBuilder.dragManual(test_4, dropzoneRoot)

      await expect(result).toMatchAriaSnapshot(`
        - text: "Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Tabs Textfield: foo Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Token: corge Textfield: grault Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
      await page.goto(viewUrl)
      await displayBuilder.shoelaceReady()
      await expect(result).toMatchAriaSnapshot(`
        - text: "Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Tabs Textfield: foo Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Token: corge Textfield: grault Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
    })

    await test.step(`Move nested`, async () => {
      await displayBuilder.dragManual(test_1, test_4_slot_2)
      await displayBuilder.dragManual(test_2, test_1_slot)
      await displayBuilder.dragManual(test_3, test_2_slot)
      await displayBuilder.dragManual(test_4, test_3_slot)

      await expect(result).toMatchAriaSnapshot(`
        - text: "Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Textfield: garply Token: corge Textfield: grault Textfield: thud Tabs Textfield: foo Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
      await page.goto(viewUrl)
      await displayBuilder.shoelaceReady()
      await expect(result).toMatchAriaSnapshot(`
        - text: "Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Textfield: garply Token: corge Textfield: grault Textfield: thud Tabs Textfield: foo Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
    })

    await test.step(`Move nested group`, async () => {
      await displayBuilder.dragManual(test_1, test_4_slot_1)

      await expect(result).toMatchAriaSnapshot(`
        - text: Test 2 Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Textfield: garply Token: corge Textfield: grault Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Tabs Textfield: foo Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
      await page.goto(viewUrl)
      await displayBuilder.shoelaceReady()
      await expect(result).toMatchAriaSnapshot(`
        - text: Test 2 Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: Slot 1 Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Textfield: garply Token: corge Textfield: grault Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Tabs Textfield: foo Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
    })

    await test.step(`Move back to root`, async () => {
      await displayBuilder.dragManual(test_3, dropzoneRoot)
      await displayBuilder.dragManual(test_2, dropzoneRoot)
      await displayBuilder.dragManual(test_1, dropzoneRoot)

      await expect(result).toMatchAriaSnapshot(`
        - text: Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Token: corge Textfield: grault Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Tabs Textfield: foo Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
      await page.goto(viewUrl)
      await displayBuilder.shoelaceReady()
      await expect(result).toMatchAriaSnapshot(`
        - text: Test 1
        - emphasis: Config
        - list:
          - listitem: "Title: Component 1"
        - text: "Slot 1 Token: corge Textfield: grault Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 2"
        - text: "Slot 1 Textfield: garply Test 1"
        - emphasis: Config
        - list:
          - listitem: "Title: Component 3"
        - text: "Slot 1 Token: waldo Textfield: fred Test 2 Slot 1 Token: plugh Textfield: xyzzy Slot 2 Textfield: thud Tabs Textfield: foo Textfield: bar Textfield: baz Textfield: quux Textfield: quux"
      `)
    })
  
    await test.step(`View the result page`, async () => {
      await page.goto(`test-layers`)
      await expect(page.locator('.page-wrapper')).toMatchAriaSnapshot({ name: 'layers-result.aria.yml' })
    })
  },
)
