import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'
import { getRootDir } from '../utilities/DrupalFilesystem';

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test('Preset', {tag: ['@display_builder', '@display_builder_preset', '@display_builder_min']}, async ({ page, drupal }) => {
  const dbId = `test_${utils.createRandomString(6)}`

  await drupal.loginAsAdmin()

  // Test 1: Create a Display builder
  await cmd.createDisplayBuilderFromUi(page, dbId)

  // @todo seems needed because of failing SortableJs on empty builder
  // await cmd.refresh(page, dbId)

  // Test 2: Open libraries and drag elements and set some values
  await cmd.dragElementFromLibraryById(page, 'Components', 'test_simple', page.locator(`.db-island-builder > slot.db-dropzone`))
  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))

  await cmd.dragElement(page,
    page.locator(`.db-island-builder [data-instance-title="Token"]`),
    page.locator(`.db-island-builder [data-slot-id="slot_1"]`),
  )

  await cmd.setElementValue(page,
    page.locator(`.db-island-builder [data-instance-title="Token"]`),
    'I am a test token in a slot',
      [
      {
        action: 'fill',
        locator: page.locator('#edit-value'),
      }
    ]
  )

  await cmd.setElementValue(page,
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
      }
    ]
  )

  page.on('dialog', async dialog => {
    expect(dialog.type()).toBe('prompt');
    expect(dialog.message()).toBe('Name of preset');
    await dialog.accept(`foo_${dbId}`);
  });

  await page.locator(`#island-${dbId}-builder [data-test="testing"]`).click({button: 'right', position: { x: 30, y: 10 }})
  await page.getByRole('menuitemcheckbox', { name: 'Save as preset' }).locator('slot').nth(1).click()

  const preset = page.getByRole('button', { name: `foo_${dbId}` })
  await cmd.dragElementFromLibrary(page, 'Presets', preset, page.locator(`#island-${dbId}-builder [data-test="testing"]`))

  await cmd.closeDialog(page)
  await cmd.closeDialog(page, 'second')

  await page.getByRole('tab', { name: 'Preview' }).click()
  await expect(page.locator(`#island-${dbId}-preview`)).toMatchAriaSnapshot('- text: "label: I am a component with a token I am a test token in a slot label: I am a component with a token I am a test token in a slot"')
})
