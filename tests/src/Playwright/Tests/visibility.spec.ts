import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// VisibilityConditionsPanel attaches Drupal condition plugins to a node; at
// render time alterElement() runs them and returns an empty build when any
// fails, so the component's real output vanishes (the hook fires for the Canvas
// real-render too). Here the Request Path condition is pointed at a path the
// builder is not on, which hides the component; clearing it brings it back -
// the control step that proves the hide was the condition, not a dead submit.
// @see \Drupal\display_builder\Plugin\display_builder\Island\VisibilityConditionsPanel
// @see \Drupal\display_builder\Hook\UiPatternsHooks::sourceValueAlter()
test('A visibility condition hides a node', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const component = page.locator('.db-island-builder [data-test="test_simple"]')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileExtraId)
  })

  await test.step(`Drop a component`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_simple',
      page.locator('.db-dropzone--root').first(),
      { x: 40, y: 15 },
    )
    await expect(component).toHaveCount(1)
  })

  await test.step(`Open the Visibility tab`, async () => {
    await component.first().click({ position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await page.getByTestId('tab_contextual_visibility_conditions').click()
    await displayBuilder.shoelaceReady()
    await page.getByRole('button', { name: 'Request Path' }).click()
  })

  const pages = page.locator('.db-island-visibility_conditions textarea[name="request_path[pages]"]')

  await test.step(`Restricting to another path hides the component`, async () => {
    await pages.fill('/a-path-the-builder-is-not-on')
    await pages.blur()
    await displayBuilder.htmxReady()
    await expect(component).toHaveCount(0)
  })

  await test.step(`Clearing the condition brings it back`, async () => {
    await pages.fill('')
    await pages.blur()
    await displayBuilder.htmxReady()
    await expect(component).toHaveCount(1)
  })
})
