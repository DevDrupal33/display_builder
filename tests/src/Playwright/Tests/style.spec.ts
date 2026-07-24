import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Styles is the headline feature (StylesPanel, a Contextual island applying
// ui_styles utility classes) and had zero e2e coverage. The two test styles
// come from the test theme:
//   test_style_1 -> test-style-1 | test-style-2  (category "Style category 1")
//   test_style_2 -> h1 | h2 | h3                 (category "Style category 2")
// @see tests/themes/display_builder_theme_test/display_builder_theme_test.ui_styles.yml
//
// The Select source renders each style as `radios` (previews), so an option is
// an <input type="radio" value="test-style-1">. Selecting it applies the class
// to the rendered element in the Canvas via StylesPanel::alterElement().
// @see \Drupal\display_builder\Plugin\display_builder\Island\StylesPanel
test('Apply a style utility class', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const component = page.locator('.db-island-builder [data-test="test_simple"]').first()

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
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

  await test.step(`Open the Styles contextual tab`, async () => {
    await component.click({ position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await page.getByTestId('tab_contextual_styles').click()
    await displayBuilder.shoelaceReady()
  })

  await test.step(`Select a style option`, async () => {
    // Each category is a collapsed <details> with a summary button; expand it
    // first. The Select source then renders the style as a CSS fake-dropdown:
    // the options are radios in a 47px overflow-hidden box that only expands on
    // :focus-within, with the real <input> (opacity 0) stacked above its
    // preview <label>. So focus the target radio to unfold the box, then check
    // it - clicking the input while the box is collapsed is a silent no-op.
    // @see web/modules/contrib/ui_styles/assets/css/ui_styles_source_plugin_select.css
    await page.getByRole('button', { name: 'Style category 1' }).click()
    const target = page.locator('#edit-styles-wrapper-style-category-1-ui-styles-test-style-1-test-style-1')
    await target.focus()
    await target.check({ force: true })
    await displayBuilder.htmxReady()
  })

  await test.step(`Class renders on the component`, async () => {
    await expect(component).toHaveClass(/test-style-1/)
  })

  await test.step(`The style survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(component).toHaveClass(/test-style-1/)
  })
})
