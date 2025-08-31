import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_entity_view' ])
  await drupal.setPreprocessing({ css: false, javascript: false })
})

test(
  'Entity view',
  { tag: [ '@display_builder', '@display_builder_entity_view', '@display_builder_min' ] },
  async ({ page, drupal, displayBuilder }) => {
    const testName = utils.createRandomString(6).toLowerCase()
    const name = `test_${testName}`

    await drupal.loginAsAdmin()

    // Go to the content entity and create the display.
    await page.goto(config.contentTypesAdd)
    await page.getByLabel('Name', { exact: true }).fill(`Test ${testName}`)
    await page.getByText('Save and manage fields').click()
    await page.getByRole('link', { name: '+Re-use an existing field' }).click()
    await expect(page.getByRole('dialog')).toBeVisible()
    await page.getByRole('button', { name: 'Reuse body' }).click()
    await page.getByRole('button', { name: 'Save settings' }).click()
    await drupal.expectMessage('Saved')

    await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
    // Save the fields for copy in the builder.
    await expect(page.getByRole('button', { name: 'Display builder' })).toBeVisible()

    // Enable the Display builder for default display
    await page.getByLabel('Profile', { exact: true }).selectOption('Test')
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('Your settings have been saved.')

    await page.getByRole('link', { name: 'Build the display' }).click()
    await displayBuilder.shoelaceReady()

    // Enable highlight to ease drag.
    await displayBuilder.keyboardShortcut('Shift+H')

    // Test the proper blocks are available for Entity view context.
    const sources = {
      entity_link: '[Entity] Link',
    }
    await displayBuilder.expectBlocksAvailable(sources, false)

    // Basic common drag component and token.
    await displayBuilder.dragSimpleComponentsWithToken('I am a test token in a slot in an Entity view!')

    // Should be from fields.
    await displayBuilder.closeDialog('both')
    await displayBuilder.saveDisplayBuilder()

    await displayBuilder.expectPreviewAriaSnapshot('entity.aria.yml')

    // Disable the display builder.
    await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
    await page.getByLabel('Profile', { exact: true }).selectOption('- Disabled -')
    await page.getByRole('button', { name: 'Save' }).click()

    // Check it is not deleted (should it be?)
    await page.goto(config.dbList)
    await expect(page.getByRole('link', { name: `entity_view__node__${name}__default` })).toBeVisible()

    // Create a field ui patterns for sources, hide it and select a profile.
    await drupal.drush(
      `field:create -y node ${name} --field-name=field_test_sources_${testName} --field-label="UIP Sources" --field-type=ui_patterns_source --field-widget=ui_patterns_source --is-required=0 --cardinality=-1`
    )
    await page.goto(config.contentTypesFormDisplay.replace('{content_type}', name))
    await page.getByRole('button', { name: 'Show row weights' }).click()
    await page.getByLabel('Region for UIP Sources').selectOption('Disabled')
    await page.getByRole('button', { name: 'Save', exact: true }).click()

    await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
    await page
      .getByLabel('Select a field to override this display per content')
      .selectOption({ value: `field_test_sources_${testName}` })
    await page.getByLabel('Override profile').selectOption('Test')
    await page.getByRole('button', { name: 'Save', exact: true }).click()

    await page.goto(config.contentTypeAdd.replace('{bundle}', name))
    await page.getByRole('textbox', { name: 'Title *' }).fill(`Test content for ${name}`)
    // await page.getByLabel('body').fill('This is a <b>test</b>!')
    await page.getByRole('button', { name: 'Save' }).click()
    await drupal.expectMessage('has been created.')

    await page.getByRole('link', { name: 'Default display' }).click()
    await displayBuilder.shoelaceReady()
    // Basic common drag component and token.
    await displayBuilder.dragSimpleComponentsWithToken('I am a test token in a slot in an Entity view override!')

    // Check result on preview and on view entity page.
    await displayBuilder.closeDialog('both')
    await displayBuilder.saveDisplayBuilder()
    await displayBuilder.expectPreviewAriaSnapshot('entity-override.aria.yml')
    await page.getByRole('link', { name: 'View' }).click()
    await expect(page.locator('.block-system-main-block')).toMatchAriaSnapshot({ name: 'entity-override.aria.yml' })
  }
)
