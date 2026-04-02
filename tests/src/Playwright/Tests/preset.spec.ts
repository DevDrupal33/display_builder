import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Preset', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const presetName = `test_${utils.createRandomString()}`

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal)
  })

  await test.step(`Build instance`, async () => {
    await displayBuilder.shoelaceReady()
    await displayBuilder.fullHighlight()

    await displayBuilder.dragElementFromLibraryById(
      'Components',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
    )
    await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', page.locator('.db-dropzone--root').first())

    await displayBuilder.dragElement(
      page.locator(`.db-island-builder [data-node-type="textfield"]`),
      page.locator(`.db-island-builder [data-slot-id="slot_1"]`),
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-type="textfield"]`),
      'I am a test textfield in a slot',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ],
    )

    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-test="test_simple"]`),
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
      ],
    )

    // Apply a style
    await page.getByRole('tab', { name: 'Styles', exact: true }).click()

    await page.getByRole('button', { name: 'Style category 1' }).click()
    await page.getByRole('group', { name: 'Test style 1' }).getByLabel('- None -').click()
    const styleOption = page.locator(`input[value="test-style-1"]`)
    await styleOption.click()
    await page.getByRole('textbox', { name: 'Extra classes' }).fill('my-extra-class')
    await displayBuilder.htmxReady()

    await displayBuilder.closeDialog('both')
    await page.getByRole('tab', { name: 'Preview' }).click()
    await displayBuilder.shoelaceReady()
    await expect(page.locator('.db-island-preview')).toMatchAriaSnapshot({ name: 'test-preset.aria.yml' })
    await expect(page.locator('.db-island-preview').locator('[data-test="test_simple"]').first()).toHaveClass(/my-extra-class/)

    await page.getByRole('tab', { name: 'Builder' }).click()
    await displayBuilder.shoelaceReady()
  })

  await test.step(`Save preset`, async () => {
    // Preset will open a dialog prompt to fill a name.
    page.on('dialog', async dialog => {
      expect(dialog.type()).toBe('prompt')
      expect(dialog.message()).toBe('Name of preset')
      await dialog.accept(`foo_${presetName}`)
    })

    await page
      .getByRole('heading', { name: 'label: I am a component with a textfield' })
      .click({ button: 'right', position: { x: 40, y: 10 } })
    await page.getByRole('menuitemcheckbox', { name: 'Save as preset' }).locator('slot').nth(1).click()
  })

  await test.step(`Check preset and drag`, async () => {
    await displayBuilder.openLibrariesTab('Presets')
    const preset = page.getByRole('button', { name: `foo_${presetName}` })

    // Check preview on hover
    await expect(preset).toBeVisible()
    await preset.hover()
    await displayBuilder.htmxReady()
    await expect(page.getByRole('tooltip')).toBeVisible()
    // From the test component.
    await expect(page.getByRole('tooltip')).toMatchAriaSnapshot({ name: 'test-preset-hover.aria.yml' })

    await displayBuilder.dragElementFromLibrary(
      'Presets',
      preset,
      page.getByRole('heading', { name: 'label: I am a component with a textfield' }),
    )

    await displayBuilder.closeDialog('first')
    await page.getByRole('tab', { name: 'Preview' }).click()
    await displayBuilder.shoelaceReady()
    await expect(page.locator('.db-island-preview')).toMatchAriaSnapshot({ name: 'test-preset-final.aria.yml' })
    // Ensure style is propagated.
    await expect(page.locator('.db-island-preview').locator('[data-test="test_simple"]').nth(0)).toHaveClass(/my-extra-class/)
    await expect(page.locator('.db-island-preview').locator('[data-test="test_simple"]').nth(1)).toHaveClass(/my-extra-class/)
  })
})
