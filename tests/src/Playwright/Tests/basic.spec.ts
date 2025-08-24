import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'
import { getRootDir } from '../utilities/DrupalFilesystem';

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test('Create instance', {tag: ['@display_builder', '@display_builder_min']}, async ({ page, drupal }) => {
  const dbName = `test_${utils.createRandomString(6)}`

  await drupal.loginAsAdmin()
  // Test 0: Check status page
  await page.goto('admin/reports/status')
  await page.screenshot({ path: `${getRootDir()}/../test-results/status_page.png`, fullPage: true });

  // Test 1: Create a Display builder
  await page.goto(dbConfig.dbAddUrl)
  await page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
  await page.locator('select[name="display_builder"]').selectOption('test')
  // @todo select a fixture
  // await page.locator('select[name="fixture_id"]').selectOption(fixture)
  await expect(page.getByRole('button', { name: 'Save' })).toBeVisible()
  await page.getByRole('button', { name: 'Save' }).click()

  await expect(page.getByRole('heading', { name: `Display builder: ${dbName}` })).toBeVisible()
  await cmd.shoelaceReady(page)
  await expect(page.getByRole('tab', { name: 'Builder' })).toBeVisible()

  // Test 2: Open libraries
  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible()
  await page.getByRole('button', { name: 'Libraries' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Components' })).toBeVisible()

  // Test 3: Delete this instance
  await page.goto(dbConfig.dbList)
  await expect(page.getByRole('link', { name: dbName })).toBeVisible()
  await page.getByRole('row', { name: `${dbName}` }).getByRole('button').click()
  await page.getByRole('link', { name: 'Delete', exact: true }).click()
  await expect(page.getByRole('heading', { name: `Do you want to delete ${dbName}?` })).toBeVisible()
  await page.getByRole('button', { name: 'Confirm' }).click()
  await expect(page.getByRole('link', { name: dbName })).not.toBeVisible()
})

test('Actions and cmd', {tag: ['@display_builder']}, async ({ page, drupal }) => {
  const dbName = `test_${utils.createRandomString(6)}`

  await drupal.loginAsAdmin()

  // Test 1: Create a Display builder
  await cmd.createDisplayBuilderFromUi(page, dbName)

  // @todo seems needed because of failing SortableJs on empty builder
  await cmd.refresh(page, dbName)

  // Test 2: Open libraries and drag elements and set some values
  await cmd.dragElementFromLibraryById(page, 'Components', 'test_simple', page.locator(`.db-island-builder > slot.db-dropzone`))
  // page.locator(`.db-island-builder > slot.db-dropzone`).highlight()
  await cmd.dragElementFromLibraryById(page, 'Blocks', 'token', page.locator(`.db-island-builder > slot.db-dropzone`))
  // page.locator(`.db-island-builder > slot.db-dropzone`).highlight()

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

  await cmd.closeDialog(page)
  await cmd.closeDialog(page, 'second')

  await page.getByRole('tab', { name: 'Preview' }).click()
  await expect(page.locator(`#island-${dbName}-preview`)).toMatchAriaSnapshot('- text: "label: I am a component with a token I am a test token in a slot"')
})

// Legacy test with not much cmd usage.
test('Full Display Builder', {tag: '@display_builder'}, async ({ page, drupal }) => {

  await drupal.loginAsAdmin()
  const dbName = `test_${utils.createRandomString(6)}`
  const dbDomId = `#island-${dbName}-builder`

  // Test 1: Create a Display builder
  await page.goto(dbConfig.dbAddUrl)
  await page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
  await page.locator('select[name="display_builder"]').selectOption('test')
  // @todo select a fixture
  // await page.locator('select[name="fixture_id"]').selectOption(fixture)
  await page.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByRole('heading', { name: `Display builder: ${dbName}` })).toBeVisible()
  await cmd.shoelaceReady(page)
  await expect(page.getByRole('tab', { name: 'Builder' })).toBeVisible()

  // Test 2: Open libraries
  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible()
  await page.getByRole('button', { name: 'Libraries' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Components' })).toBeVisible()
  await page.getByRole('tab', { name: 'Components' }).click()

  // Test 3: check preview on hover
  const testComponent = page.getByRole('button', { name: 'Test complex', exact: true })
  await expect(testComponent).toBeVisible()
  await testComponent.hover()
  await cmd.htmxReady(page)
  await expect(page.getByRole('tooltip')).toBeVisible()
  // From the test component.
  await expect(page.getByRole('tooltip')).toContainText('label: Bar, open: true, duration: 123')

  // Test 4: Move component to builder.
  await page.mouse.down()
  await page.locator(`${dbDomId} slot`).hover()
  await page.mouse.up()
  await cmd.htmxReady(page)
  await expect(
    page.locator(`${dbDomId} [data-test="testing"]`)
  ).toBeVisible()
  await expect(
    page.locator(`${dbDomId} [data-test="testing"]`)
  ).toContainText('label: none, open: false, duration: 0')

  // Test 5: Instance form variant configuration
  await page
    .locator(`${dbDomId} [data-test="testing"]`)
    .click()
  await cmd.htmxReady(page)
  await expect(page.getByRole('dialog', { name: 'Settings' })).toBeVisible()

  // Test 5-1: Apply multiple config
  await page.getByRole('button', { name: 'Label' }).click()
  await page
    .locator(`input[name='component[props][label][source][value]']`)
    .fill('I am a test')
  await cmd.htmxReady(page)

  await page.getByRole('button', { name: 'Open' }).click()
  await page
    .locator(`input[name='component[props][open][source][value]']`)
    .check()
  await cmd.htmxReady(page)

  await page.getByRole('button', { name: 'Duration' }).click()
  await page
    .locator(`input[name='component[props][duration][source][value]']`)
    .fill('22')
  // click somewhere for htmx submit
  await page.getByRole('tab', { name: 'Builder' }).click()
  await cmd.htmxReady(page)

  await expect(
    page.locator(`${dbDomId} [data-test="testing"]`)
  ).toContainText('label: I am a test, open: true, duration: 22')

  // Test 5-2: Apply a style
  await page.getByRole('tab', { name: 'Styles', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Style category 1' })).toBeVisible()

  await page.getByRole('button', { name: 'Style category 1' }).click()
  await page
    .getByRole('group', { name: 'Test style 1' })
    .getByLabel('- None -')
    .click()
  const styleOption = page.locator(`input[value="test-style-1"]`)
  await expect(styleOption).toBeVisible()
  await styleOption.click()
  await cmd.htmxReady(page)

  // Test 5-3: Apply a token
  // await page.getByRole('tab', { name: 'Tokens', exact: true }).click()
  // await expect(page.getByRole('button', { name: 'Testing' })).toBeVisible()
  // await page.getByRole('button', { name: 'Testing' }).click()
  // await page
  //   .locator(`input[name='test_token_1']`)
  //   .fill('test-token-1')
  // await cmd.htmxReady(page)

  await page.getByRole('dialog', { name: 'Settings' }).getByLabel('Close').click()

  // Move Token to slot 1
  await expect(
    page.getByRole('tab', { name: 'Blocks', exact: true })
  ).toBeVisible()

  await page
    .getByRole('tab', { name: 'Blocks', exact: true })
    .locator('div')
    .click()

  const targetSlot = page.locator(`#island-${dbName}-builder`).getByTitle('Slot 1', { exact: true })
  const tokenBlock = page.locator(`#island-${dbName}-block_library`).getByRole('button', { name: 'Token', exact: true })
  await expect(tokenBlock).toBeVisible()

  await tokenBlock.hover()

  await page.mouse.down()
  await targetSlot.hover()
  await page.mouse.up()
  await cmd.htmxReady(page)

  const builderTokenBlock = page.locator(`#island-${dbName}-builder`).getByRole('button', { name: 'Token', exact: true })
  await expect(builderTokenBlock).toBeVisible()

  await builderTokenBlock.hover()

  await page.mouse.down()
  await targetSlot.hover()
  await page.mouse.up()
  await cmd.htmxReady(page)

  // @todo move Wysiwyg to slot 2

  // Delete all
  // await page.goto(dbConfig.dbList)
  // await expect(page.getByRole('link', { name: dbName })).toBeVisible()
  // await page.goto(dbConfig.dbDeleteAllUrl)
  // await page.getByRole('button', { name: 'Confirm' }).click()
  // await page.goto(dbConfig.dbList)
})
