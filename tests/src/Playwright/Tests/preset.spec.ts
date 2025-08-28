import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'

import * as utils from '../utilities/utils'

import config from '../playwright.config.loader'

test(
  'Preset',
  { tag: [ '@display_builder', '@display_builder_preset', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const dbName = `test_${utils.createRandomString(6)}`

    await drupal.loginAsAdmin()

    // Test 1: Create a Display builder
    await displayBuilder.createDisplayBuilderFromUi(dbName)
    // Enable highlight to ease drag.
    await displayBuilder.keyboardShortcut('Shift+H')

    // Test 2: Open libraries and drag elements and set some values
    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )
    await displayBuilder.dragElementFromLibraryById(
      'Blocks',
      'token',
      page.locator(`.db-island-builder > slot.db-dropzone`)
    )

    await displayBuilder.dragElement(
      page.locator(`.db-island-builder [data-instance-title="Token"]`),
      page.locator(`.db-island-builder [data-slot-id="slot_1"]`)
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-instance-title="Token"]`),
      'I am a test token in a slot',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ]
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-instance-title="Test simple"]`),
      'I am a component with a token',
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

    // Preset will open a dialog prompt to fill a name.
    page.on('dialog', async dialog => {
      expect(dialog.type()).toBe('prompt')
      expect(dialog.message()).toBe('Name of preset')
      await dialog.accept(`foo_${dbName}`)
    })

    await page
      .locator(`#island-${dbName}-builder [data-test="test_simple"]`)
      .click({ button: 'right', position: { x: 40, y: 10 } })
    await page.getByRole('menuitemcheckbox', { name: 'Save as preset' }).locator('slot').nth(1).click()

    const preset = page.getByRole('button', { name: `foo_${dbName}` })
    await displayBuilder.dragElementFromLibrary(
      'Presets',
      preset,
      page.locator(`#island-${dbName}-builder [data-test="test_simple"]`)
    )

    await displayBuilder.closeDialog('both')

    const result = `
      - 'heading "label: I am a component with a token" [level=5]'
      - text: I am a test token in a slot
      - button "Click me"
      - 'heading "label: I am a component with a token" [level=5]'
      - text: I am a test token in a slot
      - button "Click me"
    `
    await displayBuilder.expectPreviewAriaSnapshot(dbName, result)

    await displayBuilder.deleteDisplayBuilderFromUi(dbName)
  }
)
