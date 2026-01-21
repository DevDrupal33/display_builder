import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_dev_tools' ])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'From fixture',
  { tag: [ '@display_builder', '@display_builder_dev_tools' ] },
  async ({ page, drupal, displayBuilder }) => {
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

    await test.step(`Build instance`, async () => {
      // Add a textfield in a slot and set a value
      await displayBuilder.dragElementFromLibraryById(
        'Blocks',
        'textfield',
        page.locator('.db-island-builder [data-test="test_simple_slot"]'),
      )

      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
        'Test fixture: I am a test textfield in a slot! ',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
      )

      await displayBuilder.expectPreviewAriaSnapshot('dev-instance-2.aria.yml')
    })

    await test.step(`Move textfield to the end of slot`, async () => {
      await displayBuilder.dragElement(
        page.locator(`.db-island-builder .test_simple .slot_test [data-slot-position="0"]`),
        page.locator(`.db-island-builder .test_simple .slot_test [data-slot-position="1"]`),
        { x: 40, y: 20 },
      )

      await displayBuilder.expectPreviewAriaSnapshot('dev-instance-3.aria.yml')
    })

    await test.step(`Check and delete`, async () => {
      await displayBuilder.closeDialog('both')
      // await drupal.screenshot('instance_from_fixture_ok.png')

      // await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
    })
  },
)

test(
  'Secondary actions',
  { tag: [ '@display_builder', '@display_builder_dev_tools' ] },
  async ({ page, drupal, displayBuilder }) => {
    const dbName = `test_${utils.createRandomString()}`

    const firstDrawerId = 'db-first-drawer'
    const secondDrawerId = 'db-second-drawer'

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    // Create a Display builder with a fixture from the fixture:
    // tests/themes/display_builder_theme_test/fixtures/test_simple.yml
    await test.step(`Create dev instance`, async () => {
      await displayBuilder.createDisplayBuilderFromUi(dbName, 'Test simple')
      // Enable highlight to ease drag.
      await displayBuilder.fullHighlight()
    })

    await test.step(`Check preview on components`, async () => {
      await displayBuilder.openLibrariesTab('Components')
      const testComponent = page.getByRole('button', { name: 'Test simple', exact: true })
      await testComponent.hover()
      await displayBuilder.htmxReady()
      // From the test component.
      await expect(page.getByRole('tooltip', { name: 'label: Bar Foo Click me' })).toBeVisible()
    })

    await test.step(`Check preview on blocks`, async () => {
      await displayBuilder.openLibrariesTab('Blocks')
      const testBlock = page.getByRole('button', { name: 'Powered by Drupal', exact: true })
      await testBlock.hover()
      await displayBuilder.htmxReady()
      await expect(page.getByRole('tooltip')).toMatchAriaSnapshot({ name: 'block-powered-hover.aria.yml' })
    })

    await test.step(`Drawer resize`, async () => {
      await page
        .locator(`.db-island-builder [data-node-type="textfield"]`)
        .first()
        .click({ position: { x: 5, y: 10 } })
      await displayBuilder.htmxReady()

      const firstDrawer = page.locator(`#${firstDrawerId}`)
      await firstDrawer.locator(`.shoelace-resize-handle`).hover()
      await page.mouse.down()
      await page.mouse.move(400 + 133, 400)
      await page.mouse.up()
      let box = await firstDrawer.locator(`.drawer__panel`).boundingBox()

      await expect(firstDrawer).toHaveAttribute('style', '--size: 533px;')
      await expect(firstDrawer).toHaveAttribute('data-offset-left', '533px')
      await expect(box?.width).toEqual(533)

      const secondDrawer = page.locator(`#${secondDrawerId}`)
      await secondDrawer.locator(`.shoelace-resize-handle`).hover()
      await page.mouse.down()
      box = await secondDrawer.locator(`.drawer__panel`).boundingBox()
      await page.mouse.move((box?.x ?? 0) - 133, 400)
      await page.mouse.up()

      await expect(secondDrawer).toHaveAttribute('style', '--size: 533px;')
      box = await secondDrawer.locator(`.drawer__panel`).boundingBox()
      expect(box?.width).toEqual(533)
    })

    // await test.step(`Delete`, async () => {
    // await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
    // })
  },
)

test(
  'From scratch',
  { tag: [ '@display_builder', '@display_builder_dev_tools' ] },
  async ({ page, drupal, displayBuilder }) => {
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
        page.locator('.db-dropzone--root').first(),
      )
      await displayBuilder.dragElementFromLibraryById('Components', 'test_simple', componentSimpleSlot)
      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', componentSimpleSlot.nth(1))

      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
        'I am a test textfield in a slot',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
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
        ],
      )

      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-title="Test simple"]`).nth(1),
        'Second component with a textfield',
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
    })

    await test.step(`Check and delete`, async () => {
      await displayBuilder.closeDialog('both')
      await displayBuilder.expectPreviewAriaSnapshot('dev-instance-scratch.aria.yml')
      // await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
    })
  },
)

test(
  'Contextual',
  { tag: [ '@display_builder', '@display_builder_dev_tools' ] },
  async ({ page, drupal, displayBuilder }) => {
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
        page.locator('.db-dropzone--root').first(),
      )
      await displayBuilder.dragElementFromLibraryById('Components', 'test_simple', componentSimpleSlot)
      await displayBuilder.dragElementFromLibraryById('Blocks', 'textfield', componentSimpleSlot.nth(1))

      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-title="Test simple"]`).first(),
        'I am component',
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

      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-title="Test simple"]`).nth(1),
        'I am component inside component with a textfield',
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

      await displayBuilder.setElementValue(
        page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
        'I am a test textfield in a slot',
        [
          {
            action: 'fill',
            locator: page.locator('#edit-value'),
          },
        ],
      )

      await displayBuilder.closeDialog('both')

      await expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'contextual.aria.yml' })

      await page
        .getByRole('heading', { name: 'label: I am component inside component with a textfield' })
        .click({ button: 'right', position: { x: 40, y: 10 } })

      await page.getByRole('menuitemcheckbox', { name: 'Duplicate Test simple' }).locator('slot').nth(1).click()
      await displayBuilder.shoelaceReady()

      await expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'contextual-duplicate.aria.yml' })

      await page
        .getByRole('heading', { name: 'label: I am component inside component with a textfield' })
        .nth(1)
        .click({ button: 'right', position: { x: 40, y: 10 } })

      await page.getByRole('menuitemcheckbox', { name: 'Remove' }).locator('slot').nth(1).click()
      await displayBuilder.shoelaceReady()

      await expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'contextual-remove.aria.yml' })

      // await test.step(`Delete`, async () => {
      // await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
      // })
    })
  },
)
