import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['display_builder_dev_tools'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Preset', { tag: ['@display_builder', '@display_builder_dev_tools'] }, async ({ page, drupal, displayBuilder }) => {
  const dbName = `test_${utils.createRandomString()}`

  await test.step(`Admin login`, async () => {
    await drupal.loginAsAdmin()
  })

  await test.step(`Create dev instance`, async () => {
    await displayBuilder.createDisplayBuilderFromUi(dbName)
  })

  await test.step(`Build instance`, async () => {
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    // Test 2: Open libraries and drag elements and set some values
    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      page.locator('.db-dropzone--root').first()
    )
    await displayBuilder.dragElementFromLibraryById(
      'Blocks',
      'textfield',
      page.locator('.db-dropzone--root').first()
    )

    await displayBuilder.dragElement(
      page.locator(`.db-island-builder [data-node-type="textfield"]`),
      page.locator(`.db-island-builder [data-slot-id="slot_1"]`)
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-type="textfield"]`),
      'I am a test textfield in a slot',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ]
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-title="Test simple"]`),
      'I am a component with a textfield',
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

  await test.step(`Save preset`, async () => {
    // Preset will open a dialog prompt to fill a name.
    page.on('dialog', async dialog => {
      expect(dialog.type()).toBe('prompt')
      expect(dialog.message()).toBe('Name of preset')
      await dialog.accept(`foo_${dbName}`)
    })

    await page
      .getByRole('heading', { name: 'label: I am a component with a textfield' })
      .click({ button: 'right', position: { x: 40, y: 10 } })
    await page.getByRole('menuitemcheckbox', { name: 'Save as preset' }).locator('slot').nth(1).click()
  })

  await test.step(`Check preset and drag`, async () => {
    const preset = page.getByRole('button', { name: `foo_${dbName}` })

    // Check preview on hover
    await page.getByRole('tab', { name: 'Presets' }).click()
    await preset.hover()
    await displayBuilder.htmxReady()
    await expect(page.getByRole('tooltip')).toBeVisible()
    // From the test component.
    await expect(page.getByRole('tooltip')).toMatchAriaSnapshot({ name: 'test-preset-hover.aria.yml' })

    await displayBuilder.dragElementFromLibrary(
      'Presets',
      preset,
      page.getByRole('heading', { name: 'label: I am a component with a textfield' })
    )
  })

  await test.step(`Check and delete`, async () => {
    await displayBuilder.closeDialog('both')
    await displayBuilder.expectPreviewAriaSnapshot('dev-instance-preset.aria.yml')
    await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
  })
})
