import { expect, Locator } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Two of these move steps used to be silent no-ops. The cause was the drop
// position, not the module: dragManual() aimed at the vertical middle of the
// target, and with SortableJS's swapThreshold of 0.65 the centre band of an
// item is ambiguous - neither "insert before" nor "insert after" - so the drop
// did nothing. Harmless on tall Builder components, fatal on the ~30px rows
// here. dragManual() now aims near the top edge.
//
// Before re-running with -u, check that each step's FIRST snapshot differs from
// the previous step's: the moves are the assertion, and -u will happily record
// an unchanged tree as the new baseline. Each step's SECOND snapshot is
// expected to match its first - that is the reload persistence check.
// @see components/dropzone/dropzone.js for the Sortable thresholds.
test('Move tests', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  // tests/modules/display_builder_page_layout_test/config/install/display_builder_page_layout.page_layout.test_scaffold.yml
  const instanceId = `test_scaffold`
  const viewUrl = `${config.pageViewUrl.replace('{instance_id}', instanceId)}`

  const result = page.locator(`.db-island-scaffold`)
  const dropzoneRoot = result.locator('.db-dropzone--root')

  // The node IDs are the readable ones declared by the fixture.
  const layer = (nodeId: string): Locator => result.locator(`[data-testid^="layer_"][data-node-id="${nodeId}"]`)
  const slot = (nodeId: string, slotId: string): Locator =>
    result.locator(`[data-node-id="${nodeId}"][data-slot-id="${slotId}"]`)

  const component_1 = layer('component_1')
  const component_1_slot = slot('component_1', 'slot_1')
  const component_2 = layer('component_2')
  const component_2_slot = slot('component_2', 'slot_1')
  const component_3 = layer('component_3')
  const component_3_slot = slot('component_3', 'slot_1')
  const component_4 = layer('component_4')
  const component_4_slot_1 = slot('component_4', 'slot_2_1')
  const component_4_slot_2 = slot('component_4', 'slot_2_2')

  await test.step(`User login`, async () => {
    await displayBuilder.createUserAndLogin(drupal)
  })

  await test.step(`Prepare instance`, async () => {
    await page.goto(`${config.pageViewUrl.replace('{instance_id}', instanceId)}`)
    await displayBuilder.shoelaceReady()

    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Col 2 Textfield: textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Col 3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Textfield: textfield_2 Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Col 2 Textfield: textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Col 3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Textfield: textfield_2 Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 Textfield: textfield_5"
    `)
  })

  await test.step(`Move all in 1 slot`, async () => {
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_all_0.png' })
    await displayBuilder.dragManual(component_2, component_1_slot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_all_1.png' })
    await displayBuilder.dragManual(component_3, component_1_slot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_all_2.png' })
    await displayBuilder.dragManual(component_4, component_1_slot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_all_3.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
  })

  await test.step(`Move back to root`, async () => {
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_0.png' })
    await displayBuilder.dragManual(component_2, dropzoneRoot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_1.png' })
    await displayBuilder.dragManual(component_3, dropzoneRoot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_2.png' })
    await displayBuilder.dragManual(component_4, dropzoneRoot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_3.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
  })

  await test.step(`Move nested`, async () => {
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_0.png' })
    await displayBuilder.dragManual(component_1, component_4_slot_2)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_1.png' })
    await displayBuilder.dragManual(component_2, component_1_slot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_2.png' })
    await displayBuilder.dragManual(component_3, component_2_slot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_3.png' })
    await displayBuilder.dragManual(component_4, component_3_slot)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_4.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Test 1"
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
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Textfield: component_2_textfield_2 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Textfield: component_4_textfield_3 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: "Root container Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Test 1"
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
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Textfield: component_2_textfield_2 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Textfield: component_4_textfield_3 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
  })

  await test.step(`Move nested group`, async () => {
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_group_0.png' })
    await displayBuilder.dragManual(component_1, component_4_slot_1)
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_nested_group_1.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: Root container Test 2 Slot 1 Test 1
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
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Textfield: component_2_textfield_2 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: Root container Test 2 Slot 1 Test 1
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
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Textfield: component_2_textfield_2 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
  })

  await test.step(`Move back to root`, async () => {
    // await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_2_0.png' })
    await displayBuilder.dragManual(component_3, dropzoneRoot)
    //  await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_2_1.png' })
    await displayBuilder.dragManual(component_2, dropzoneRoot)
    //  await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_2_2.png' })
    await displayBuilder.dragManual(component_1, dropzoneRoot)
    //  await page.locator('.db-island-scaffold').screenshot({ path: 'move_back_2_3.png' })

    await expect(result).toMatchAriaSnapshot(`
      - text: Root container Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
    await page.goto(viewUrl)
    await displayBuilder.shoelaceReady()
    await expect(result).toMatchAriaSnapshot(`
      - text: Root container Test 1
      - emphasis: Config
      - list:
        - listitem: "Title: component_1_textfield_1"
      - text: "Slot 1 Textfield: component_1_textfield_2 Textfield: component_1_textfield_3 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_2_textfield_1"
      - text: "Slot 1 Textfield: component_2_textfield_2 Test 1"
      - emphasis: Config
      - list:
        - listitem: "Title: component_3_textfield_1"
      - text: "Slot 1 Textfield: component_3_textfield_2 Textfield: component_3_textfield_3 Test 2 Slot 1 Textfield: component_4_textfield_1 Textfield: component_4_textfield_2 Slot 2 Textfield: component_4_textfield_3 [Page] Title Textfield: textfield_1 Grid Row 3 Cols Col 1 Textfield: textfield_4 Col 2 Textfield: textfield_3 Col 3 Textfield: textfield_2 Textfield: textfield_5"
    `)
  })

  await test.step(`View the result page`, async () => {
    await page.goto(`test-layers`)
    await expect(page.locator('.page-wrapper')).toMatchAriaSnapshot({ name: 'scaffold-result.aria.yml' })
    // await page.locator('.page-wrapper').screenshot({ path: 'final.png' })
  })
})
