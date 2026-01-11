import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['display_builder_entity_view'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Entity view',
  { tag: ['@display_builder', '@display_builder_entity_view', '@display_builder_min'] },
  async ({ page, drupal, displayBuilder }) => {
    const testName = utils.createRandomString()
    const name = `test_${testName}`

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    await test.step(`Create entity type and set display`, async () => {
      // Go to the content entity and create the display.
      await page.goto(config.contentTypesAdd)
      await page.getByLabel('Name', { exact: true }).fill(`Test ${testName}`)
      await page.getByText('Save and manage fields').click()
      await page.getByRole('link', { name: 'Create a new field' }).click()
      await expect(page.getByRole('dialog')).toBeVisible()
      await page.locator('.add-field-container > a').nth(1).click()
      await expect(page.getByRole('textbox', { name: 'Label' })).toBeVisible()
      await page.getByRole('textbox', { name: 'Label' }).fill(`Body ${name}`)
      await page.getByRole('radio', { name: 'Text (formatted, long)' }).click()
      await page.getByRole('button', { name: 'Continue' }).click()
      await page.getByRole('button', { name: 'Save' }).click()
      await expect(page.getByRole('dialog')).toBeHidden()
      await drupal.expectMessage('Saved')

      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      // Save the fields for copy in the builder.
      await expect(page.locator('main').getByRole('button', { name: 'Display builder' })).toBeVisible()

      // Enable the Display builder for default display
      await page.getByLabel('Profile', { exact: true }).selectOption('Test')
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Check the display`, async () => {
      await page.getByRole('link', { name: 'Build the display' }).click()
      await displayBuilder.shoelaceReady()

      // Enable highlight to ease drag.
      await displayBuilder.fullHighlight()

      // Check preview on hover
      await displayBuilder.openLibrariesTab('Components')
      const testComponent = page.getByRole('button', { name: 'Test simple', exact: true })
      await expect(testComponent).toBeVisible()
      await testComponent.hover()
      await displayBuilder.htmxReady()
      await expect(page.getByRole('tooltip')).toBeVisible()
      // From the test component.
      await expect(page.getByRole('tooltip')).toMatchAriaSnapshot({ name: 'test-simple-hover.aria.yml' })

      // Test the proper blocks are available for Entity view context.
      // @todo test more fields in sources list.
      const sources = {
        entity_link: '[Entity] Link',
      }
      await displayBuilder.expectBlocksAvailable(sources, false)
    })

    await test.step(`Build the display`, async () => {
      // Basic common drag component and textfield.
      await displayBuilder.dragSimpleComponentsWithTextfield('I am a test textfield in a slot in an Entity view!')

      await displayBuilder.closeDialog('second')

      // Instance form variant configuration
      await page
        .locator(`[data-db-build-container] [data-test="test_simple"]`)
        .click({ position: {x: 5, y: 5} }) // Avoid click on the slot textfield.
      await displayBuilder.htmxReady()

      // Apply multiple config
      await page.getByRole('button', { name: 'Label' }).click()
      await page
        .locator(`input[name='component[props][label][source][value]']`)
        .fill('I am a test')
      await displayBuilder.htmxReady()

      await page.getByRole('button', { name: 'Tag' }).click()
      await page
        .locator(`input[name='component[props][tag][source][value]']`)
        .fill('h2')
      await displayBuilder.htmxReady()

      // Click somewhere for htmx submit
      await page.getByRole('tab', { name: 'Builder' }).click()
      await displayBuilder.htmxReady()

      // Apply a style
      await page.getByRole('tab', { name: 'Styles', exact: true }).click()

      await page.getByRole('button', { name: 'Style category 1' }).click()
      await page
        .getByRole('group', { name: 'Test style 1' })
        .getByLabel('- None -')
        .click()
      const styleOption = page.locator(`input[value="test-style-1"]`)
      await styleOption.click()
      await displayBuilder.htmxReady()

      // Apply style extra class.
      await page
        .locator(`input[name='styles[wrapper][_ui_styles_extra]']`)
        .fill('foo bar')

      // Click somewhere for htmx submit
      await page.getByRole('tab', { name: 'Builder' }).click()
      await displayBuilder.htmxReady()

      // Ensure styles are applied
      // @todo ensure they are on preview or view
      await expect(displayBuilder.getBuildLocator(`[data-test="test_simple"]`)).toHaveClass(/test-style-1 foo bar test_simple/)

      await displayBuilder.closeDialog('both')
      await displayBuilder.publishDisplayBuilder()

      await displayBuilder.expectPreviewAriaSnapshot('entity.aria.yml')
    })

    await test.step(`Disable the display`, async () => {
      // Disable the display builder.
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      await page.getByLabel('Profile', { exact: true }).selectOption('- Disabled -')
      await page.getByRole('button', { name: 'Save' }).click()

      // @todo Check it is not deleted (should it be?)
      // await page.goto(config.dbList)
      // await expect(page.locator(`tr.${config.entityPrefix}node__${name}__default`)).toBeVisible()
    })
  }
)

test(
  'Entity view override',
  { tag: ['@display_builder', '@display_builder_entity_view', '@display_builder_min'] },
  async ({ page, drupal, displayBuilder }) => {
    const testName = utils.createRandomString()
    const name = `test_${testName}`

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    await test.step(`Create entity type and set display`, async () => {
      // Go to the content entity and create the display.
      await page.goto(config.contentTypesAdd)
      await page.getByLabel('Name', { exact: true }).fill(`Test ${testName}`)
      await page.getByText('Save and manage fields').click()
      await page.getByRole('link', { name: 'Create a new field' }).click()
      await expect(page.getByRole('dialog')).toBeVisible()
      await page.locator('.add-field-container > a').nth(1).click()
      await expect(page.getByRole('textbox', { name: 'Label' })).toBeVisible()
      await page.getByRole('textbox', { name: 'Label' }).fill(`Body ${name}`)
      await page.getByRole('radio', { name: 'Text (formatted, long)' }).click()
      await page.getByRole('button', { name: 'Continue' }).click()
      await page.getByRole('button', { name: 'Save' }).click()
      await expect(page.getByRole('dialog')).toBeHidden()
      await drupal.expectMessage('Saved')

      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      // Save the fields for copy in the builder.
      await expect(page.locator('main').getByRole('button', { name: 'Display builder' })).toBeVisible()

      // Enable the Display builder for default display
      await page.getByLabel('Profile', { exact: true }).selectOption('Test')
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Check the display`, async () => {
      await page.getByRole('link', { name: 'Build the display' }).click()
      await displayBuilder.shoelaceReady()

      // Enable highlight to ease drag.
      await displayBuilder.fullHighlight()

      // Test the proper blocks are available for Entity view context.
      // @todo test more fields in sources list.
      const sources = {
        entity_link: '[Entity] Link',
      }
      await displayBuilder.expectBlocksAvailable(sources, false)
    })

    await test.step(`Create override`, async () => {
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
      // Basic common drag component and textfield.
      await displayBuilder.dragSimpleComponentsWithTextfield('I am a test textfield in a slot in an Entity view override!')

      // Check result on preview and on view entity page.
      await displayBuilder.closeDialog('both')
      await displayBuilder.publishDisplayBuilder()
      await displayBuilder.expectPreviewAriaSnapshot('entity-override.aria.yml')
      await page.getByRole('link', { name: 'View' }).click()
      await expect(page.locator('.block-system-main-block')).toMatchAriaSnapshot({ name: 'entity-override.aria.yml' })
    })
  }
)
