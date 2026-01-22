import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import { exec } from '../utilities/DrupalExec'
import config from '../playwright.config.loader'

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'display_builder_dev_tools' ])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Canary',
  { tag: [ '@display_builder', '@display_builder_dev_tools' ] },
  async ({ page, drupal, displayBuilder }) => {
    const dbName = `test_${utils.createRandomString()}`

    await test.step(`Admin login`, async () => {
      await page.goto(`/`)

      expect(page.getByRole('heading', { name: 'Log in' })).toBeVisible()

      const stdout = await exec(`php core/scripts/test-site.php user-login 1 --site-path ${drupal.drupalSite.sitePath}`)
      await page.goto(stdout.toString())

      expect(page.getByRole('heading', { name: 'admin' })).toBeVisible()
    })

    await test.step(`Create dev instance`, async () => {
      await page.goto(config.devAddInstance)

      expect(page.getByRole('heading', { name: 'Add a display builder instance' })).toBeVisible()

      await page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
      await page.getByLabel('Profile').selectOption('test')
      await page.getByRole('button', { name: 'Save' }).click()

      expect(page.getByRole('heading', { name: 'Display Builder instance devel' })).toBeVisible()
    })

    await test.step(`Add component`, async () => {
      await displayBuilder.shoelaceReady()

      expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible()

      await page.getByRole('button', { name: 'Libraries' }).click()

      expect(page.locator(config.startDrawerID)).toBeVisible()

      const component = page.locator(`.db-island-library [hx-vals*="test_simple"]`).first()
      await component.dragTo(page.locator('.db-dropzone--root').first(), {
        force: true,
        targetPosition: {
          x: 20,
          y: 10,
        },
      })
      await displayBuilder.htmxReady()

      expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'canary_empty.aria.yml' })
    })

    await test.step(`Set component value`, async () => {
      const component = page.getByTestId('test_simple').first()
      await component.click({ position: { x: 5, y: 10 } })
      await displayBuilder.htmxReady()

      expect(page.locator(config.endDrawerID)).toBeVisible()

      await page.getByRole('button', { name: 'Label', exact: true }).click()
      await page.getByRole('textbox', { name: 'Label' }).fill('I am component')
      await page.getByRole('button', { name: 'Update', exact: true }).click()
      await displayBuilder.htmxReady()

      expect(page.locator('.db-island-builder')).toMatchAriaSnapshot({ name: 'canary_fill.aria.yml' })
    })
  },
)
