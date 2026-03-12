import { expect, Locator } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

// Test for debug with a running DDEV instance and existing instance.
// Must be used with .env settings for DDEV.

test.beforeEach('Setup', async ({ drupal }) => {
  // await drupal.installModules([ 'display_builder_dev_tools' ])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test('Local tests with DDEV', { tag: [ '@local' ] }, async ({ page, drupal, displayBuilder }) => {
  // const username = `test_${utils.createRandomString()}`
  // const dbName = `standalone__test`
  // const dropzoneRoot = page.locator('.db-dropzone--root')
  // await displayBuilder.initTestsWithPageLayout(drupal)
  // await expect(page.locator(`.db-island-builder .db-dropzone--root`)).toMatchAriaSnapshot(``)
  // await test.step(`Prepare user`, async () => {
  //   await drupal.createUser({
  //     username,
  //     password: 'test',
  //     email: `${username}}@test.com`,
  //     roles: [ 'display_builder_test' ],
  //   })
  //   await drupal.login({ username })
  // })
  // await test.step(`Prepare instance`, async () => {
  //   await page.goto(`${config.dbViewUrl.replace('{instance_id}', dbName)}`)
  //   await displayBuilder.shoelaceReady()
  //   await displayBuilder.fullscreen()
  // })
})
