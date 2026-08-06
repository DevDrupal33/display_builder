import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_entity_view_test' ])
  await drupal.installModules([ 'display_builder_entity_view_override_test' ])
})

test(
  'Entity view',
  { tag: [ '@base' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = utils.createRandomString()
    const name = `test_${id}`

    await test.step(`Create User and login`, async () => {
      await displayBuilder.createUserAndLogin(drupal, [ 'test_db_entity' ])
    })

    await test.step(`Create entity type`, async () => {
      await drupal.createContentType(name, id)
    })

    await test.step(`Enable display`, async () => {
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name).replace('{display}', 'default'))
      await expect(page.locator('main').getByRole('button', { name: 'Display builder' })).toBeVisible()

      // Enable the Display builder for default display
      await page.getByLabel('Enable with profile', { exact: true }).selectOption(config.testProfileBuilder)
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Check the display`, async () => {
      await page.getByRole('link', { name: 'Display builder', exact: true }).click()
      await displayBuilder.shoelaceReady()
      await displayBuilder.highlight()

      // Test the proper blocks are available for Entity view context.
      // @todo test more fields in sources list.
      const sources = {
        entity_link: '[Entity] Link',
        extra_field: 'Extra field',
      }
      await displayBuilder.expectBlocksAvailable(sources, false)
    })

    await test.step(`Build the display`, async () => {
      // Basic common drag component and textfield.
      await displayBuilder.dragComponentsAndTextfield('I am a test textfield in a slot in an Entity view!')

      // Instance form variant configuration
      await page.locator(`.db-island-builder [data-test="test_simple"]`).click({ position: { x: 5, y: 5 } }) // Avoid click on the slot textfield.
      await displayBuilder.htmxReady()

      // Apply multiple config
      await displayBuilder.setContextualFormValue(
        page.getByRole('button', { name: 'Label', exact: true }),
        page.getByRole('textbox', { name: 'Label', exact: true }),
        'I am a test',
      )
      await displayBuilder.setContextualFormValue(
        page.getByRole('button', { name: 'Tag', exact: true }),
        page.getByRole('textbox', { name: 'Tag', exact: true }),
        'h2',
      )

      // Click somewhere for htmx submit
      await page.getByTestId('tab_view_builder').click()
      await displayBuilder.htmxReady()

      await displayBuilder.publishDisplayBuilder()

      await expect(page.locator('.db-island-builder .test_simple')).toMatchAriaSnapshot(`
        - text: Test simple
        - 'heading "label: I am a test" [level=2]'
        - text: Slot 1 Textfield I am a test textfield in a slot in an Entity view!
        - button "Click me"
      `)
    })

    await test.step(`Disable the display`, async () => {
      // Disable the display builder.
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name).replace('{display}', 'default'))
      await page.getByLabel('Enable with profile', { exact: true }).selectOption('- Disabled -')
      await page.getByRole('button', { name: 'Save' }).click()

      // @todo Check it is not deleted (should it be?)
      // await page.goto(config.dbList)
      // await expect(page.locator(`tr.${config.entityPrefix}node__${name}__default`)).toBeVisible()
    })
  },
)

// Expand mode covers the viewport and hides Drupal's own chrome. On the
// embedded entity-view route (case 2) the toolbar and sidebars are position:
// fixed overlays, normally cleared by the .dialog-off-canvas-main-canvas
// wrapper - which expanded mode escapes by making .display-builder itself
// position: fixed. So the panes' wrapper (.display-builder__main) has to
// re-create the offsets itself (@see
// components/display_builder/css/display_builder.css); without that the built
// content sits under the toolbar and behind the open sidebar. Measured on the
// real boxes rather than the CSS, so it fails if either offset is lost.
test(
  'Entity view expand clears the toolbar and sidebar',
  { tag: [ '@base' ] },
  async ({ page, drupal, displayBuilder }) => {
    const id = utils.createRandomString()
    const name = `test_${id}`

    await test.step(`Create user, content type, enable Display builder`, async () => {
      await displayBuilder.createUserAndLogin(drupal, [ 'test_db_entity' ])
      await drupal.createContentType(name, id)
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name).replace('{display}', 'default'))
      await page.getByLabel('Enable with profile', { exact: true }).selectOption(config.testProfileBuilder)
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
      await page.getByRole('link', { name: 'Display builder', exact: true }).click()
      await displayBuilder.shoelaceReady()
    })

    // A placed block gives the Canvas a measurable element, and the drag leaves
    // the Libraries sidebar open, so it has a real width for the left offset to
    // clear.
    const block = page.locator('.db-island-builder [data-node-type="textfield"]').first()
    await test.step(`Place a block`, async () => {
      await displayBuilder.dragElementFromLibraryById(
        'block',
        'textfield',
        page.locator('.db-island-builder > div.db-dropzone').first(),
        { x: 40, y: 15 },
      )
      await expect(block).toBeVisible()
    })

    await test.step(`Expanded content clears the fixed toolbar (top) and sidebar (left)`, async () => {
      const sidebar = page.locator('.db-sidebar--start')
      await page.locator('[data-island-action="expand"]').click()
      await expect(page.locator('.display-builder')).toHaveClass(/display-builder--expanded/)
      await expect(sidebar).not.toHaveClass(/is-collapsed/)

      const toolbarBox = await page.locator('.db-toolbar').boundingBox()
      const sidebarBox = await sidebar.boundingBox()
      const contentBox = await block.boundingBox()

      // The left offset only proves anything if the sidebar actually has width.
      expect(sidebarBox!.width).toBeGreaterThan(100)
      // Content starts below the toolbar and to the right of the sidebar.
      expect(contentBox!.y).toBeGreaterThanOrEqual(toolbarBox!.y + toolbarBox!.height - 1)
      expect(contentBox!.x).toBeGreaterThanOrEqual(sidebarBox!.x + sidebarBox!.width - 1)
    })
  },
)

test(
  'Entity view override',
  { tag: [ '@extra'] },
  async ({ page, drupal, displayBuilder }) => {
    const id = utils.createRandomString()
    const name = `test_${id}`

    await test.step(`Create entity type`, async () => {
      await drupal.createContentType(name, id)
    })

    await test.step(`Create User and login`, async () => {
      await displayBuilder.createUserAndLogin(drupal, [ 'test_db_entity' ])
      await drupal.addPermissions({
        role: 'test_db_entity',
        permissions: [ `create ${name} content`, `edit own ${name} content` ],
      })
    })

    await test.step(`Create entity type and set display`, async () => {
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name).replace('{display}', 'default'))
      await expect(page.locator('main').getByRole('button', { name: 'Display builder' })).toBeVisible()

      // Enable the Display builder for default display
      await page.getByLabel('Enable with profile', { exact: true }).selectOption(config.testProfileBuilder)
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')
    })

    await test.step(`Enable override`, async () => {
      await page.goto(config.contentTypesDisplay.replace('{content_type}', name).replace('{display}', 'default'))
      await page.getByRole('checkbox', { name: 'Enable content overrides' }).click()

      await expect(page.getByLabel('Profile for overrides')).toBeVisible()
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Your settings have been saved.')

      await expect(page.getByText('Display Override Field')).toBeVisible()
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
      await displayBuilder.dragComponentsAndTextfield('I am a test textfield in a slot in an Entity view override!')

      // Add a field.
      await displayBuilder.openLibrariesTab('block')
      const target = page.locator(`.db-island-builder > div.db-dropzone`).first()
      const element = page.locator(`.db-island-library [data-node-title="Body"]`).first()

      await displayBuilder.dragElementFromLibrary('block', element, target, { x: 10, y: 10 })

      // Check result on preview and on view entity page.
      await displayBuilder.publishDisplayBuilder()
      await page.goto('/node/1')
      await expect(page.locator('.block-system-main-block')).toMatchAriaSnapshot({ name: 'entity-override.aria.yml' })
    })
  },
)
