import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// The save -> publish -> persist flow had only thin coverage
// (ApiPublishingController sat at 62.5% lines). StateButtons renders the
// Publish button only while the draft differs from the published version
// (!saveIsCurrent), so the button's presence is a faithful, non-flaky signal
// for "there are unpublished changes": it appears on the first edit, clears on
// publish, survives a reload as published, and returns on the next edit.
// @see \Drupal\display_builder\Plugin\display_builder\Island\StateButtons
// @see \Drupal\display_builder\Controller\ApiPublishingController
test('Save, publish and persist', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const component = page.locator('.db-island-builder [data-test="test_simple"]')
  const publish = page.locator('[data-island-action="publish"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`Editing raises an unpublished draft`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(component).toHaveCount(1)
    await expect(publish).toBeVisible()
  })

  await test.step(`Publishing clears the draft`, async () => {
    await displayBuilder.publishDisplayBuilder()
    await expect(publish).toBeHidden()
  })

  await test.step(`The published state persists across a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(component).toHaveCount(1)
    await expect(publish).toBeHidden()
  })

  await test.step(`A further edit raises a new draft`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(component).toHaveCount(2)
    await expect(publish).toBeVisible()
  })
})
