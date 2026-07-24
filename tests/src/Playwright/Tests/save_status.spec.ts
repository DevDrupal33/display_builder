import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// The SaveStatus island renders a single status pip whose variant class mirrors
// where the display stands: `db-save-status--saved` after any structural edit,
// `db-save-status--published` once published (and at rest thereafter, since the
// published version is then present). publish.spec keys on the Publish button;
// this pins the pip itself, the piece the user actually reads. `save_status` is
// enabled in test_builder alongside `state` for this.
// @see \Drupal\display_builder\Plugin\display_builder\Island\SaveStatus
// @see components/save_status/save_status.twig
test('Save status pip tracks publish state', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const pip = page.locator('.db-save-status')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`An edit shows the "saved" (unpublished draft) pip`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(pip).toHaveClass(/db-save-status--saved/)
  })

  await test.step(`Publishing flips the pip to "published"`, async () => {
    await displayBuilder.publishDisplayBuilder()
    await expect(pip).toHaveClass(/db-save-status--published/)
  })

  await test.step(`The published pip is the resting state after a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(pip).toHaveClass(/db-save-status--published/)
  })
})
