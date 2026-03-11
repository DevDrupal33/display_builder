import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Secondary actions',
  { tag: [ '@display_builder' ] },
  async ({ page, drupal, displayBuilder }) => {

    const firstDrawerId = 'db-first-drawer'
    const secondDrawerId = 'db-second-drawer'

    await test.step(`Create Page Layout and login`, async () => {
      await displayBuilder.initTestsWithPageLayout(drupal)
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

    // await test.step(`Drawer resize`, async () => {
    //   const firstDrawer = page.locator(`#${firstDrawerId}`)
    //   await firstDrawer.locator(`.shoelace-resize-handle`).hover()
    //   await page.mouse.down()
    //   await page.mouse.move(400 + 133, 400)
    //   await page.mouse.up()
    //   let box = await firstDrawer.locator(`.drawer__panel`).boundingBox()

    //   await expect(firstDrawer).toHaveAttribute('style', '--size: 533px;')
    //   await expect(firstDrawer).toHaveAttribute('data-offset-left', '533px')
    //   await expect(box?.width).toEqual(533)

    //   const secondDrawer = page.locator(`#${secondDrawerId}`)
    //   await secondDrawer.locator(`.shoelace-resize-handle`).hover()
    //   await page.mouse.down()
    //   box = await secondDrawer.locator(`.drawer__panel`).boundingBox()
    //   await page.mouse.move((box?.x ?? 0) - 133, 400)
    //   await page.mouse.up()

    //   await expect(secondDrawer).toHaveAttribute('style', '--size: 533px;')
    //   box = await secondDrawer.locator(`.drawer__panel`).boundingBox()
    //   expect(box?.width).toEqual(533)
    // })

    // await test.step(`Delete`, async () => {
    // await displayBuilder.deleteDisplayBuilderFromDevUi(dbName)
    // })
  },
)

test(
  'Contextual menu',
  { tag: [ '@display_builder' ] },
  async ({ page, drupal, displayBuilder }) => {
    await test.step(`Create Page Layout and login`, async () => {
      await displayBuilder.initTestsWithPageLayout(drupal)
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
        page.locator(`.db-island-builder [data-test="test_simple"]`).first(),
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
        page.locator(`.db-island-builder [data-test="test_simple"]`).nth(1),
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
