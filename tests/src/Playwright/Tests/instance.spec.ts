import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import dbConfig from '../playwright.db.config'

test(
  'From fixture',
  { tag: [ '@display_builder', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const dbName = `test_${utils.createRandomString(6)}`
    await drupal.loginAsAdmin()
    let result: string

    // Pre-flight check: status page
    await page.goto('admin/reports/status')
    await drupal.screenshot('status_page.png')

    const componentSimpleSlot = page.locator(`.db-island-builder .test_simple .slot_test [data-slot-id="slot_1"]`)

    // Create a Display builder with a fixture from the fixture:
    // tests/themes/display_builder_theme_test/fixtures/test_simple.yml
    await displayBuilder.createDisplayBuilderFromUi(dbName, 'Test simple')
    // Enable highlight to ease drag.
    await displayBuilder.keyboardShortcut('Shift+H')

    result = `
    - 'heading "label: This is a test" [level=5]'
    - text: This is a token test
    - paragraph: This is a Wysiwyg test
    - button "Click me"
  `
    await displayBuilder.expectPreviewAriaSnapshot(dbName, result)

    // Check preview on hover
    await displayBuilder.openLibrariesTab('Components')
    const testComponent = page.getByRole('button', { name: 'Test simple', exact: true })
    await testComponent.hover()
    await displayBuilder.htmxReady()
    // From the test component.
    await expect(page.getByRole('tooltip', { name: 'label: Bar Foo Click me' })).toBeVisible()

    // Add a token in a slot and set a value
    await displayBuilder.dragElementFromLibraryById('Blocks', 'token', componentSimpleSlot)
    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-instance-title="Token"]`).first(),
      'I am a test token in a slot! ',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ]
    )
    result = `
    - 'heading "label: This is a test" [level=5]'
    - text: I am a test token in a slot! This is a token test
    - paragraph: This is a Wysiwyg test
    - button "Click me"
  `
    await displayBuilder.expectPreviewAriaSnapshot(dbName, result)

    // Move token to the end of slot, after wysiwyg
    await displayBuilder.dragElement(
      page.locator(`.db-island-builder .test_simple .slot_test [data-slot-position="0"]`),
      page.locator(`.db-island-builder .test_simple .slot_test [data-slot-position="2"]`),
      { x: 40, y: 20 }
    )

    result = `
    - 'heading "label: This is a test" [level=5]'
    - text: This is a token test
    - paragraph: This is a Wysiwyg test
    - text: I am a test token in a slot!
    - button "Click me"
  `
    await displayBuilder.expectPreviewAriaSnapshot(dbName, result)

    await displayBuilder.closeDialog('both')
    await drupal.screenshot('instance_from_fixture_ok.png')

    await displayBuilder.deleteDisplayBuilderFromUi(dbName)
  }
)

test('From scratch', { tag: [ '@display_builder' ] }, async ({ page, drupal, displayBuilder }) => {
  const dbName = `test_${utils.createRandomString(6)}`
  await drupal.loginAsAdmin()

  await displayBuilder.createDisplayBuilderFromUi(dbName)
  // Enable highlight to ease drag.
  await displayBuilder.keyboardShortcut('Shift+H')

  const componentSimpleSlot = page.locator(`.db-island-builder .test_simple .slot_test [data-slot-id="slot_1"]`)

  // Drag element and set some values
  await displayBuilder.dragElementFromLibraryById(
    'Components',
    'test_simple',
    page.locator(`.db-island-builder > slot.db-dropzone`)
  )
  await displayBuilder.dragElementFromLibraryById('Components', 'test_simple', componentSimpleSlot)
  await displayBuilder.dragElementFromLibraryById('Blocks', 'token', componentSimpleSlot.nth(1))

  await displayBuilder.setElementValue(
    page.locator(`.db-island-builder [data-instance-title="Token"]`).first(),
    'I am a test token in a slot',
    [
      {
        action: 'fill',
        locator: page.locator('#edit-value'),
      },
    ]
  )

  await displayBuilder.setElementValue(
    page.locator(`.db-island-builder [data-instance-title="Test simple"]`).first(),
    'First component',
    [
      {
        action: 'click',
        locator: page.getByRole('button', { name: 'Label' }),
      },
      {
        action: 'fill',
        locator: page.locator('input[name="component[props][label][source][value]"]'),
      },
    ]
  )

  await displayBuilder.setElementValue(
    page.locator(`.db-island-builder [data-instance-title="Test simple"]`).nth(1),
    'Second component with a token',
    [
      {
        action: 'click',
        locator: page.getByRole('button', { name: 'Label' }),
      },
      {
        action: 'fill',
        locator: page.locator('input[name="component[props][label][source][value]"]'),
      },
    ]
  )

  await displayBuilder.closeDialog('both')

  const result = `
  - 'heading "label: First component" [level=5]'
  - 'heading "label: Second component with a token" [level=5]'
  - text: I am a test token in a slot
  - button "Click me"
  - button "Click me"
  `
  await displayBuilder.expectPreviewAriaSnapshot(dbName, result)

  await displayBuilder.deleteDisplayBuilderFromUi(dbName)
})
