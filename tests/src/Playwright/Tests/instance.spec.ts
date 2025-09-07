import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_dev_tools' ])
  await drupal.setPreprocessing({ css: false, javascript: false })
})

test('From fixture', { tag: [ '@display_builder_dev_tools' ] }, async ({ page, drupal, displayBuilder }) => {
  const dbName = `test_${utils.createRandomString()}`

  await test.step(`Admin login`, async () => {
    await drupal.loginAsAdmin()
  })

  await test.step(`Pre-flight check: status page`, async () => {
    await page.goto(config.statusReport)
    await drupal.screenshot('status_page.png')
  })

  // Create a Display builder with a fixture from the fixture:
  // tests/themes/display_builder_theme_test/fixtures/test_simple.yml
  await test.step(`Create dev instance`, async () => {
    await displayBuilder.createDisplayBuilderFromUi(dbName, 'Test simple')
  })

  await test.step(`Check instance fixture`, async () => {
    // Enable highlight to ease drag.
    await displayBuilder.fullHighlight()

    await displayBuilder.expectPreviewAriaSnapshot('dev-instance-1.aria.yml')
  })

  await test.step(`Check preview on hover`, async () => {
    await displayBuilder.openLibrariesTab('Components')
    const testComponent = page.getByRole('button', { name: 'Test simple', exact: true })
    await testComponent.hover()
    await displayBuilder.htmxReady()
    // From the test component.
    await expect(page.getByRole('tooltip', { name: 'label: Bar Foo Click me' })).toBeVisible()
  })

  await test.step(`Build instance`, async () => {
    // Add a token in a slot and set a value
    const componentSimpleSlot = page.locator(`.db-island-builder .test_simple .slot_test [data-slot-id="slot_1"]`)
    await displayBuilder.dragElementFromLibraryById('Blocks', 'token', componentSimpleSlot)
    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-title="Token"]`).first(),
      'I am a test token in a slot! ',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ]
    )

    await displayBuilder.expectPreviewAriaSnapshot('dev-instance-2.aria.yml')
  })

  await test.step(`Move token to the end of slot`, async () => {
    await displayBuilder.dragElement(
      page.locator(`.db-island-builder .test_simple .slot_test [data-slot-position="0"]`),
      page.locator(`.db-island-builder .test_simple .slot_test [data-slot-position="2"]`),
      { x: 40, y: 20 }
    )

    await displayBuilder.expectPreviewAriaSnapshot('dev-instance-3.aria.yml')
  })

  await test.step(`Check and delete`, async () => {
    await displayBuilder.closeDialog('both')
    await drupal.screenshot('instance_from_fixture_ok.png')

    await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
  })
})

test('From scratch', { tag: [ '@display_builder_dev_tools' ] }, async ({ page, drupal, displayBuilder }) => {
  const dbName = `test_${utils.createRandomString()}`

  await test.step(`Admin login`, async () => {
    await drupal.loginAsAdmin()
  })

  await test.step(`Create dev instance`, async () => {
    await displayBuilder.createDisplayBuilderFromUi(dbName)
  })

  await test.step(`Build instance`, async () => {
    await displayBuilder.fullHighlight()

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
      page.locator(`.db-island-builder [data-node-title="Token"]`).first(),
      'I am a test token in a slot',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ]
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-title="Test simple"]`).first(),
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
      page.locator(`.db-island-builder [data-node-title="Test simple"]`).nth(1),
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
  })

  await test.step(`Check and delete`, async () => {
    await displayBuilder.closeDialog('both')
    await displayBuilder.expectPreviewAriaSnapshot('dev-instance-scratch.aria.yml')
    await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
  })
})
