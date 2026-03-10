import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'field_ui', 'node', 'display_builder_entity_view', 'display_builder_entity_view_test', 'display_builder_entity_view_override_test' ])
})

test(
  'Entity view',
  { tag: [ '@display_builder', '@display_builder_entity_view' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = utils.createRandomString()
    const name = `test_${id}`

    await test.step(`Create User and login`, async () => {
      await displayBuilder.createUserAndLogin(drupal, ['db_test_entity'])
    })

    await test.step(`Create entity type`, async () => {
      await drupal.createContentType(name, id)
    })

    await test.step(`Enable display`, async () => {
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      await expect(page.locator('main').getByRole('button', { name: 'Display builder' })).toBeVisible()

      // Enable the Display builder for default display
      await page.getByLabel('Enable with profile', { exact: true }).selectOption('Test profile')
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Check the display`, async () => {
      await page.getByRole('link', { name: 'Display builder', exact: true }).click()
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
      await displayBuilder.dragComponentsAndTextfield('I am a test textfield in a slot in an Entity view!')

      await displayBuilder.closeDialog('second')

      // Instance form variant configuration
      await page.locator(`.db-island-builder [data-test="test_simple"]`).click({ position: { x: 5, y: 5 } }) // Avoid click on the slot textfield.
      await displayBuilder.htmxReady()

      // Apply multiple config
      await page.getByRole('button', { name: 'Label' }).click()
      await page.locator(`input[name='component[props][label][source][value]']`).fill('I am a test')
      await displayBuilder.htmxReady()

      await page.getByRole('button', { name: 'Tag' }).click()
      await page.locator(`input[name='component[props][tag][source][value]']`).fill('h2')
      await displayBuilder.htmxReady()

      // Click somewhere for htmx submit
      await page.getByRole('tab', { name: 'Builder' }).click()
      await displayBuilder.htmxReady()

      // Apply a style
      await page.getByRole('tab', { name: 'Styles', exact: true }).click()

      await page.getByRole('button', { name: 'Style category 1' }).click()
      await page.getByRole('group', { name: 'Test style 1' }).getByLabel('- None -').click()
      const styleOption = page.locator(`input[value="test-style-1"]`)
      await styleOption.click()
      await displayBuilder.htmxReady()

      // Apply style extra class.
      await page.locator(`input[name='styles[wrapper][_ui_styles_extra]']`).fill('foo bar')

      // Click somewhere for htmx submit
      await page.getByRole('tab', { name: 'Builder' }).click()
      await displayBuilder.htmxReady()

      // Ensure styles are applied
      // @todo ensure they are on preview or view
      await expect(page.locator(`.db-island-builder [data-test="test_simple"]`)).toHaveClass(
        /test-style-1 foo bar test_simple/,
      )

      await displayBuilder.closeDialog('both')
      await displayBuilder.publishDisplayBuilder()

      await expect(page.locator('.db-island-builder .test_simple')).toMatchAriaSnapshot(`
        - text: Test simple
        - 'heading "label: I am a test" [level=2]'
        - text: "Textfield: I am a test textfield... I am a test textfield in a slot in an Entity view! Slot 1"
        - button "Click me"
      `)
    })

    await test.step(`Disable the display`, async () => {
      // Disable the display builder.
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      await page.getByLabel('Enable with profile', { exact: true }).selectOption('- Disabled -')
      await page.getByRole('button', { name: 'Save' }).click()

      // @todo Check it is not deleted (should it be?)
      // await page.goto(config.dbList)
      // await expect(page.locator(`tr.${config.entityPrefix}node__${name}__default`)).toBeVisible()
    })
  },
)

test(
  'Entity view override',
  { tag: [ '@display_builder', '@display_builder_entity_view' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = utils.createRandomString()
    const name = `test_${id}`

    await test.step(`Create entity type`, async () => {
      await drupal.createContentType(name, id)
    })

    await test.step(`Create User and login`, async () => {
      await displayBuilder.createUserAndLogin(drupal, ['db_test_entity'])
      await drupal.addPermissions({role: 'db_test_entity', permissions: [`create ${name} content`, `edit own ${name} content`]})
    })

    await test.step(`Create entity type and set display`, async () => {
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      await expect(page.locator('main').getByRole('button', { name: 'Display builder' })).toBeVisible()

      // Enable the Display builder for default display
      await page.getByLabel('Enable with profile', { exact: true }).selectOption('Test profile')
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Enable override`, async () => {
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name))
      await page
        .getByRole('checkbox', { name: 'Enable content overrides' }).click()

      await expect(page.getByLabel('Override profile')).toBeVisible()
      await page.getByLabel('Override profile').selectOption('Test profile')
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Create override`, async () => {
      await page.goto(config.contentTypeAdd.replace('{bundle}', name))
      await page.getByRole('textbox', { name: 'Title *' }).fill(`Test content`)
      await page.getByRole('textbox', { name: 'Body' }).fill(`This is a test content!`)
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('has been created.')

      await page.getByRole('link', { name: 'Default display' }).click()
      await displayBuilder.shoelaceReady()
      // Basic common drag component and textfield.
      await displayBuilder.dragComponentsAndTextfield(
        'I am a test textfield in a slot in an Entity view override!',
      )

      // Add a field.
      await displayBuilder.openLibrariesTab('Blocks')
      const target = page.locator(`.db-island-builder > div.db-dropzone`).first()
      const element = page.locator(`.db-island-library [data-node-title="Body"]`).first()

      await displayBuilder.dragElementFromLibrary('Blocks', element, target, { x: 10, y: 10 })

      // Check result on preview and on view entity page.
      await displayBuilder.closeDialog('both')
      await displayBuilder.publishDisplayBuilder()
      await displayBuilder.expectPreviewAriaSnapshot('entity-override.aria.yml')
      await page.getByRole('link', { name: 'View' }).click()
      await expect(page.locator('.block-system-main-block')).toMatchAriaSnapshot({ name: 'entity-override.aria.yml' })
    })
  },
)
