import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// MenuStyles adds a "Styles" submenu to the right-click menu that copies /
// pastes / merges / deletes the ui_styles third-party setting a node carries,
// independent of copying the node itself. Copy stashes the source node in
// localStorage (client-side); Paste is an htmx call to api_paste_styles that
// applies the stashed node's styles to the right-clicked one. style.spec covers
// the Styles *panel*; this covers moving a style *between* nodes via the menu.
// `menu_styles` and `styles` are both on in test_builder.
// @see \Drupal\display_builder\Plugin\display_builder\Island\MenuStyles
// @see components/contextual_menu/contextual_menu.js (updateMenuItems / sl-select)
test('Copy styles between nodes via the menu', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const components = page.locator('.db-island-builder [data-test="test_simple"]')
  const nodeA = components.first()
  const nodeB = components.nth(1)
  // The "Styles" parent item is the one wrapping the copy_styles child.
  const stylesParent = page.locator('sl-menu-item.menu__item:has(sl-menu-item[value="copy_styles"])')

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`Drop two components`, async () => {
    for (let i = 0; i < 2; i++) {
      await displayBuilder.dragElementFromLibraryById(
        'component',
        'test_simple',
        page.locator('.db-dropzone--root').first(),
        { x: 40, y: 15 },
      )
    }
    await expect(components).toHaveCount(2)
  })

  await test.step(`Style only the first node`, async () => {
    await nodeA.click({ position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await page.getByTestId('tab_contextual_styles').click()
    await displayBuilder.shoelaceReady()
    await page.getByRole('button', { name: 'Style category 1' }).click()
    const option = page.locator('#edit-styles-wrapper-style-category-1-ui-styles-test-style-1-test-style-1')
    await option.focus()
    await option.check({ force: true })
    await displayBuilder.htmxReady()
    await expect(nodeA).toHaveClass(/test-style-1/)
    await expect(nodeB).not.toHaveClass(/test-style-1/)
  })

  await test.step(`Copy the first node's styles`, async () => {
    await nodeA.click({ button: 'right', position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await stylesParent.hover()
    await page.locator('sl-menu-item[value="copy_styles"]').click()
    await displayBuilder.shoelaceReady()
  })

  await test.step(`Paste them onto the second node`, async () => {
    await nodeB.click({ button: 'right', position: { x: 5, y: 5 } })
    await displayBuilder.shoelaceReady()
    await stylesParent.hover()
    await page.locator('sl-menu-item[value="paste_styles"]').click()
    await displayBuilder.htmxReady()
    await expect(nodeB).toHaveClass(/test-style-1/)
  })

  await test.step(`The pasted style survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()
    await expect(nodeB).toHaveClass(/test-style-1/)
  })
})
