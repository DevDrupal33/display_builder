import { expect } from '@playwright/test'
import { test } from '../fixtures/DrupalSite'

import * as utils from '../utilities/utils'
import * as cmd from '../utilities/commands'

import dbConfig from '../playwright.db.config'

test('Create Display Builder', async ({ page, drupal }) => {

  await drupal.setupMinimalTestSite()
  await drupal.loginAsAdmin()
  const dbName = `test_${utils.createRandomString(6)}`

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

  // Delete all
  await page.goto(dbConfig.dbList)
  await expect(page.getByRole('link', { name: dbName })).toBeVisible()
  await page.goto(dbConfig.dbDeleteAllUrl)
  await page.getByRole('button', { name: 'Confirm' }).click()
  await page.goto(dbConfig.dbList)
})

test('Simple Display Builder', async ({ page, drupal }) => {
  await drupal.setupMinimalTestSite()
  await drupal.loginAsAdmin()
  const dbName = `test_${utils.createRandomString(6)}`
  const dbId = `#island-${dbName}-builder`

  await cmd.createDisplayBuilderFromUi(page, dbName)

  // Test 1: Open libraries
  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible()
  await page.getByRole('button', { name: 'Libraries' }).click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Components' })).toBeVisible()

  // Test 2: Move component to builder
  const testComponent = page.getByRole('button', { name: 'Test simple', exact: true })
  await expect(testComponent).toBeVisible()
  await testComponent.hover()

  await page.mouse.down()
  await page.locator(`${dbId} slot`).hover()
  await page.mouse.up()
  await cmd.builderIsReady(page)

  await expect(
    page.locator(`${dbId} [data-test="testing"]`)
  ).toBeVisible()
  await expect(
    page.locator(`${dbId} [data-test="testing"]`)
  ).toContainText('label: none')

  // Test 3: Move Token to slot
  await expect(
    page.getByRole('tab', { name: 'Blocks', exact: true })
  ).toBeVisible()
  await page
    .getByRole('tab', { name: 'Blocks', exact: true })
    .locator('div')
    .click()
  await expect(
    page.locator('.db-island-block_library')
  ).toBeVisible()
  const targetSlot = page.locator(`#island-${dbName}-builder`).getByTitle('Slot 1', { exact: true })
  await expect(targetSlot).toBeVisible()
  const tokenBlock = page.locator(`#island-${dbName}-block_library`).getByRole('button', { name: 'Token', exact: true })
  await expect(tokenBlock).toBeVisible()

  await tokenBlock.hover()

  await page.mouse.down()
  await page.mouse.move(300, 300) // Needed for sortable to be ready, @todo find better way
  await targetSlot.hover()
  await page.mouse.up()
  await cmd.builderIsReady(page)

  const NewTokenBlock = page.locator(`#island-${dbName}-block_library`).getByRole('button', { name: 'Token', exact: true })
  await expect(NewTokenBlock).toBeVisible()

  await NewTokenBlock.hover()

  await page.mouse.down()
  await page.mouse.move(300, 300) // Needed for sortable to be ready, @todo find better way
  await targetSlot.hover()
  await page.mouse.up()
  await cmd.builderIsReady(page)

  // Test 4: Instance form configuration for both tokens
  await page.locator(`#island-${dbName}-builder`).getByRole('button', { name: 'Token' }).nth(1).click()
  await cmd.builderIsReady(page)
  await expect(page.getByRole('dialog', { name: 'Settings' })).toBeVisible()
  await page
    .locator(`#edit-value`)
    .fill('I am')
  await page.getByRole('button', { name: 'Update' }).click()

  await cmd.builderIsReady(page)

  await page.locator(`#island-${dbName}-builder`).getByRole('button', { name: 'Token' }).click()
  await cmd.builderIsReady(page)
  await expect(page.getByRole('dialog', { name: 'Settings' })).toBeVisible()
  await page
    .locator(`#edit-value`)
    .fill('a test')
  await page.getByRole('button', { name: 'Update' }).click()
  await cmd.builderIsReady(page)

  // Test 5: Move token before
  await page.getByText('I am ', { exact: true }).hover()
  await page.mouse.down()
  await page.mouse.move(300, 300) // Needed for sortable to be ready, @todo find better way
  await page.getByText('a test', { exact: true }).hover()
  await page.mouse.up()
  await cmd.builderIsReady(page)

  await expect(page.locator(`#island-${dbName}-builder`)).toContainText('I ama test')

  // await expect(page.getByRole('tab', { name: 'Preview' })).toBeVisible()
  // await page.getByRole('tab', { name: 'Preview' }).click()
  // await cmd.builderIsReady(page)
  // @token token are not refreshed in proper order..?
  // await expect(page.locator(`#island-${dbName}-preview`)).toContainText('a testI am')
})

test('Full Display Builder ', async ({ page, drupal }) => {

  await drupal.setupMinimalTestSite()
  await drupal.loginAsAdmin()
  const dbName = `test_${utils.createRandomString(6)}`
  const dbId = `#island-${dbName}-builder`

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

  // Test 3: check preview on hover
  const testComponent = page.getByRole('button', { name: 'Test complex', exact: true })
  await expect(testComponent).toBeVisible()
  await testComponent.hover()
  await cmd.builderIsReady(page)
  await expect(page.getByRole('tooltip')).toBeVisible()
  // From the test component.
  await expect(page.getByRole('tooltip')).toContainText('label: Bar, open: true, duration: 123')

  // Test 4: Move component to builder.
  await page.mouse.down()
  await page.locator(`${dbId} slot`).hover()
  await page.mouse.up()
  await cmd.builderIsReady(page)
  await expect(
    page.locator(`${dbId} [data-test="testing"]`)
  ).toBeVisible()
  await expect(
    page.locator(`${dbId} [data-test="testing"]`)
  ).toContainText('label: none, open: false, duration: 0')

  // Test 5: Instance form variant configuration
  await page
    .locator(`${dbId} [data-test="testing"]`)
    .click()
  await cmd.builderIsReady(page)
  await expect(page.getByRole('dialog', { name: 'Settings' })).toBeVisible()

  // Test 5-1: Apply multiple config
  await page.getByRole('button', { name: 'Label' }).click()
  await page
    .locator(`input[name='component[props][label][source][value]']`)
    .fill('I am a test')
  await cmd.builderIsReady(page)

  await page.getByRole('button', { name: 'Open' }).click()
  await page
    .locator(`input[name='component[props][open][source][value]']`)
    .check()
  await cmd.builderIsReady(page)

  await page.getByRole('button', { name: 'Duration' }).click()
  await page
    .locator(`input[name='component[props][duration][source][value]']`)
    .fill('22')
  // click somewhere for htmx submit
  await page.getByRole('tab', { name: 'Builder' }).click()
  await cmd.builderIsReady(page)

  await expect(
    page.locator(`${dbId} [data-test="testing"]`)
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
  await cmd.builderIsReady(page)

  // Test 5-3: Apply a token
  // await page.getByRole('tab', { name: 'Tokens', exact: true }).click()
  // await expect(page.getByRole('button', { name: 'Testing' })).toBeVisible()
  // await page.getByRole('button', { name: 'Testing' }).click()
  // await page
  //   .locator(`input[name='test_token_1']`)
  //   .fill('test-token-1')
  // await cmd.builderIsReady(page)

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
  await cmd.builderIsReady(page)

  const builderTokenBlock = page.locator(`#island-${dbName}-builder`).getByRole('button', { name: 'Token', exact: true })
  await expect(builderTokenBlock).toBeVisible()

  await builderTokenBlock.hover()

  await page.mouse.down()
  await targetSlot.hover()
  await page.mouse.up()
  await cmd.builderIsReady(page)

  // Move Wysiwyg to slot 2

  // Delete all
  // await page.goto(dbConfig.dbList)
  // await expect(page.getByRole('link', { name: dbName })).toBeVisible()
  // await page.goto(dbConfig.dbDeleteAllUrl)
  // await page.getByRole('button', { name: 'Confirm' }).click()
  // await page.goto(dbConfig.dbList)
})
