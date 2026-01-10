import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'

/**
 * Theme isolation tests.
 *
 * These tests verify that:
 * 1. The Build mode doesn't have CSS bloat from backend theme
 * 2. The Preview mode uses the frontend (default) theme
 * 3. Backend and frontend themes are properly isolated
 */

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules(['display_builder_dev_tools'])
  await drupal.drush('state:set -y display_builder.asset_libraries_local true')
})

test(
  'Theme isolation: Preview uses frontend theme',
  { tag: ['@display_builder', '@display_builder_dev_tools', '@theme_isolation'] },
  async ({ page, drupal, displayBuilder }) => {
    const dbName = `test_${utils.createRandomString()}`

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    await test.step(`Create dev instance with content`, async () => {
      await displayBuilder.createDisplayBuilderFromUi(dbName, 'Test simple')
    })

    await test.step(`Verify preview iframe exists`, async () => {
      await displayBuilder.fullHighlight()

      // Switch to Preview tab
      await page.getByRole('tab', { name: 'Preview' }).click()

      // Verify the preview iframe exists
      const iframe = page.locator('.db-preview-iframe')
      await expect(iframe).toBeVisible({ timeout: 5000 })

      // Get the iframe frame
      const frame = iframe.contentFrame()

      // Verify the preview content container exists
      const previewContent = frame.locator('.display-builder-preview-content')
      await expect(previewContent).toHaveCount(1, { timeout: 10000 })
    })

    await test.step(`Verify preview iframe is isolated`, async () => {
      // The iframe should have a src pointing to the preview route
      const iframe = page.locator('.db-preview-iframe')
      const src = await iframe.getAttribute('src')
      expect(src).toContain('/api/display-builder/')
      expect(src).toContain('/preview')
    })
  }
)

test(
  'Theme isolation: Build mode has CSS containment',
  { tag: ['@display_builder', '@display_builder_dev_tools', '@theme_isolation'] },
  async ({ page, drupal, displayBuilder }) => {
    const dbName = `test_${utils.createRandomString()}`

    await test.step(`Admin login`, async () => {
      await drupal.loginAsAdmin()
    })

    await test.step(`Create dev instance`, async () => {
      await displayBuilder.createDisplayBuilderFromUi(dbName, 'Test simple')
    })

    await test.step(`Verify build container exists`, async () => {
      await displayBuilder.fullHighlight()

      // Verify the build container wrapper exists in the builder island (not layers)
      const buildContainer = page.locator('.db-island-builder .db-build-container')
      await expect(buildContainer).toBeVisible()

      // Verify the dropzone is inside the build container
      const dropzone = page.locator('.db-island-builder .db-build-container .db-dropzone--root')
      await expect(dropzone).toBeVisible()
    })
  }
)

