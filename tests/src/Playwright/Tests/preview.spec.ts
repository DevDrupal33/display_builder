import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// The Preview island is its own IslandType, not a View tab: it renders as a
// hidden pane in the main region and is revealed by the split toggle, which
// pins it *beside* the active editor pane rather than replacing it. Its
// content is a persistent iframe refreshed flash-free through a hidden token,
// never by rebuilding the iframe.
//
// Nothing pinned any of that: config.spec and textarea.spec click the split
// toggle only to read a value back out of the iframe, so a regression in the
// toggle, the pane wiring or the refresh mechanism would surface there as a
// confusing content failure, or not at all.
//
// @see \Drupal\display_builder\Plugin\display_builder\Island\PreviewPanel
// @see \Drupal\display_builder\ProfileViewBuilder::buildPreviewToggle()
// @see components/display_builder/js/split.js
// @see components/display_builder/js/live_preview.js
test('Preview panel', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const builder = page.locator('.display-builder')
  const main = page.locator('.display-builder__main')
  const toggle = page.locator('[data-db-split-toggle]')
  const handle = main.locator('.db-split-handle')
  const tabStrip = page.locator('.db-toolbar__middle')
  const floatingControls = page.locator('.db-island-floating-controls')
  const builderPane = page.locator('.db-island-builder')
  const previewPane = page.locator('.db-island-preview')
  const frame = page.locator('iframe[title="Live preview"]')
  // Hidden, so only ever read through evaluate(), never through actionability.
  const token = page.locator('.db-live-preview-refresh')
  const tokenValue = () => token.evaluate((el) => (el.textContent ?? '').trim())
  // The editor column width is an inline custom property, so read it directly.
  const ratio = () => main.evaluate((el) => el.style.getPropertyValue('--db-split-ratio'))

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`Preview is a pane, not a tab`, async () => {
    // Preview is deliberately excluded from the main tab group: the toggle is
    // its only affordance. A tab here would mean prepareViewIslands() started
    // treating it as a View island.
    await expect(page.getByTestId('tab_preview_preview')).toHaveCount(0)
    await expect(previewPane).toBeHidden()
    await expect(toggle).toHaveAttribute('aria-pressed', 'false')

    // No single request_path pins this page layout to one page, so the pane
    // points at the isolated-preview route rather than a real page.
    await expect(frame).toHaveAttribute('src', /\/display-builder\/preview\//)
  })

  await test.step(`Toggle pins the Preview beside the editor`, async () => {
    await toggle.click()
    await displayBuilder.shoelaceReady()

    await expect(main).toHaveClass(/display-builder__main--split/)
    await expect(toggle).toHaveAttribute('aria-pressed', 'true')

    // The pinned pane selector is copied onto the main element; tabs.js reads
    // it to keep that pane visible whatever the active tab is.
    const target = await toggle.getAttribute('data-split-target')
    await expect(main).toHaveAttribute('data-split-target', String(target))
    await expect(page.locator(String(target))).toHaveClass(/db-island-preview/)

    // Both columns at once is the whole point of the mode.
    await expect(previewPane).toBeVisible()
    await expect(builderPane).toBeVisible()
  })

  await test.step(`Preview renders the current display`, async () => {
    await displayBuilder.dragComponentsAndTextfield('Preview alpha')

    // One iframe again means the double buffer has been promoted and the
    // previous render dropped.
    await expect(frame).toHaveCount(1)
    await expect(frame.contentFrame().locator('body')).toContainText('Preview alpha')
  })

  await test.step(`Refresh swaps the token, never the token element`, async () => {
    // The documented invariant: reloadWithGlobalData() out-of-band swaps only
    // the token's *content*. If the island were rebuilt instead, the element
    // would be replaced - taking its MutationObserver with it - and this marker
    // would be gone. The iframe would also reload from blank and flash white.
    await token.evaluate((el) => {
      el.setAttribute('data-db-test-token', 'kept')
    })
    const before = await tokenValue()

    // A second node rather than a re-edit of the first: clicking the node that
    // is already selected toggles it closed (@see js/sidebar.js), which would
    // close the contextual form instead of opening it.
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      page.locator('.db-dropzone--root').first(),
    )
    const textfields = builderPane.locator('[data-node-type="textfield"]')
    await expect(textfields).toHaveCount(2)
    // Identified by content, not by position: the root dropzone inserts the new
    // node ahead of the existing component, so nth()/last() would land on the
    // node edited above.
    await displayBuilder.setElementValue(
      textfields.filter({ hasNotText: 'Preview alpha' }),
      'Preview beta',
      [{ action: 'fill', locator: page.locator('#edit-value') }],
    )

    await expect(token).toHaveAttribute('data-db-test-token', 'kept')
    await expect.poll(tokenValue).not.toBe(before)

    await expect(frame).toHaveCount(1)
    await expect(frame.contentFrame().locator('body')).toContainText('Preview beta')
    // The earlier render is still there: the refresh replaced the iframe's
    // content, not the display.
    await expect(frame.contentFrame().locator('body')).toContainText('Preview alpha')
  })

  await test.step(`Split state survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()

    await expect(main).toHaveClass(/display-builder__main--split/)
    await expect(toggle).toHaveAttribute('aria-pressed', 'true')
    await expect(previewPane).toBeVisible()
    await expect(frame.contentFrame().locator('body')).toContainText('Preview beta')
  })

  // Full-width Preview is the far end of the split axis, not a fourth mode: the
  // same handle that sizes the columns collapses the editor away entirely, and
  // the main tab strip goes with it - by then every pane it switches is hidden.
  // @see components/display_builder/js/split.js
  await test.step(`Handle collapses the editor to a full-width Preview`, async () => {
    await expect(handle).toBeVisible()
    await handle.dblclick()

    await expect(builder).toHaveClass(/display-builder--preview-only/)
    await expect(previewPane).toBeVisible()
    await expect(builderPane).toBeHidden()
    await expect(tabStrip).toBeHidden()
    await expect(handle).toHaveAttribute('aria-valuenow', '0')
    // The editor pane's floating controls are a separate fixed box, not a child
    // of the pane they act on, so they would otherwise stay pinned over the
    // Preview's own header. @see components/shoelace/tabs/tabs.js
    await expect(floatingControls).toBeHidden()
  })

  await test.step(`Collapsed state survives a reload`, async () => {
    await page.reload()
    await displayBuilder.shoelaceReady()

    await expect(builder).toHaveClass(/display-builder--preview-only/)
    await expect(builderPane).toBeHidden()
  })

  // The tab strip is hidden here, but the shortcuts that drive it are not:
  // keyboard.js answers them by clicking the matching tab, which would switch a
  // pane nobody can see. The editor comes back first.
  await test.step(`A View panel shortcut brings the editor back`, async () => {
    // The library search box takes focus on load, and keyboard.js deliberately
    // stands down while an input has it.
    await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
    await displayBuilder.keyboardShortcut('c')

    await expect(builder).not.toHaveClass(/display-builder--preview-only/)
    await expect(builderPane).toBeVisible()
    await expect(tabStrip).toBeVisible()
    await expect(floatingControls).toBeVisible()
    // Still split: the shortcut restores the column, it does not unpin Preview.
    await expect(main).toHaveClass(/display-builder__main--split/)
  })

  // A splitter reachable only by mouse is a splitter half the users cannot move.
  await test.step(`Handle answers the keyboard`, async () => {
    await handle.focus()

    await page.keyboard.press('Home')
    await expect(builder).toHaveClass(/display-builder--preview-only/)

    // Growing out of the rail lands on the minimum, not on the previous ratio.
    await page.keyboard.press('ArrowRight')
    await expect(builder).not.toHaveClass(/display-builder--preview-only/)
    expect(await ratio()).toBe('15%')

    await page.keyboard.press('ArrowRight')
    expect(await ratio()).toBe('20%')
  })

  await test.step(`Leaving split clears the collapse`, async () => {
    await page.keyboard.press('Home')
    await expect(builder).toHaveClass(/display-builder--preview-only/)

    // Otherwise the toggle would restore preview only next time, which is not
    // what "show the preview beside the editor" promises.
    await toggle.click()
    await expect(builder).not.toHaveClass(/display-builder--preview-only/)
    await expect(builderPane).toBeVisible()

    await toggle.click()
    await displayBuilder.shoelaceReady()
    await expect(main).toHaveClass(/display-builder__main--split/)
    await expect(builder).not.toHaveClass(/display-builder--preview-only/)
  })

  await test.step(`Toggle off unpins the Preview`, async () => {
    await toggle.click()
    await displayBuilder.shoelaceReady()

    await expect(main).not.toHaveClass(/display-builder__main--split/)
    await expect(main).not.toHaveAttribute('data-split-target')
    await expect(toggle).toHaveAttribute('aria-pressed', 'false')
    await expect(previewPane).toBeHidden()
    await expect(builderPane).toBeVisible()
  })

  // PreviewPanel::keyboardShortcuts() declares 'p', but the island gets neither
  // a sidebar start button nor a main tab - the two places that carry an
  // island's data-keyboard-key - so the key was declared and never bound. The
  // split toggle is the island's only affordance and now carries it.
  // @see \Drupal\display_builder\ProfileViewBuilder::buildPreviewToggle()
  await test.step(`Keyboard shortcut drives the same toggle`, async () => {
    await expect(toggle).toHaveAttribute('data-keyboard-key', 'p')
    await expect(toggle).toHaveAttribute('aria-keyshortcuts', 'p')

    await displayBuilder.keyboardShortcut('p')
    await expect(main).toHaveClass(/display-builder__main--split/)
    await expect(previewPane).toBeVisible()

    await displayBuilder.keyboardShortcut('p')
    await expect(main).not.toHaveClass(/display-builder__main--split/)
    await expect(previewPane).toBeHidden()
  })
})

// The viewport switcher is a pane_header Floating island attached to 'preview':
// it renders inside the Preview pane as its header bar, and it is the only
// island that can act on the pane's iframe width. It belongs in the Preview
// spec because the width it sets is what makes the iframe worth having at all -
// an in-page div could never reflow its own media queries.
//
// The switcher's entries come from the breakpoint manager. On the test site the
// only provider left after ViewportSwitcher::HIDE_PROVIDER drops toolbar and
// stark is the test theme, installed as default by DisplayBuilderTestSetup, so
// the button list is exactly its four breakpoints plus the Responsive default.
// @see tests/themes/display_builder_theme_test/display_builder_theme_test.breakpoints.yml
// @see \Drupal\display_builder\Plugin\display_builder\Island\ViewportSwitcher
// @see assets/js/viewport_switcher.js
test('Preview viewport switcher', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  // tests/modules/display_builder_page_layout_test/config/install/display_builder_page_layout.page_layout.test_builder.yml
  const instanceId = `test_builder`

  const builder = page.locator('.display-builder')
  const previewPane = page.locator('.db-island-preview')
  const switcher = previewPane.locator('.db-island-viewport')
  const button = (title: string) => switcher.locator(`.switch-viewport-btn[title="${title}"]`)
  // Inline custom properties, so read from the style attribute directly.
  const paneStyle = (property: string) =>
    previewPane.evaluate((el, name) => el.style.getPropertyValue(name), property)

  await test.step(`Prepare and user login`, async () => {
    await displayBuilder.createUserAndLogin(drupal)
  })

  await test.step(`Switcher rides in the Preview pane header`, async () => {
    await page.goto(`${config.pageViewUrl.replace('{instance_id}', instanceId)}`)
    await displayBuilder.shoelaceReady()
  
    await page.locator('[data-db-split-toggle]').click()
    await displayBuilder.shoelaceReady()

    await expect(previewPane).toBeVisible()
    await expect(previewPane.locator('.db-island-pane-header')).toBeVisible()
    await expect(switcher).toBeVisible()

    // Responsive plus the test theme's sm/md/lg/xl, nothing else.
    await expect(switcher.locator('.switch-viewport-btn')).toHaveCount(5)
    await expect(button('Small (640 px)')).toBeVisible()
    await expect(button('Medium (768 px)')).toBeVisible()
    await expect(button('Large (1024 px)')).toBeVisible()
    await expect(button('Extra Large (1280 px)')).toBeVisible()

    // Responsive is the initial state: no width applied.
    expect(await paneStyle('--db-preview-width')).toBe('')
    await expect(builder).not.toHaveClass(/viewport-active/)
  })

  await test.step(`Picking a breakpoint sets the iframe width`, async () => {
    await button('Medium (768 px)').click()

    // The width lands on the pane as a custom property, never inline on the
    // iframe, so the loading buffer inherits it too.
    expect(await paneStyle('--db-preview-width')).toBe('768px')
    await expect(builder).toHaveClass(/viewport-active/)
    await expect(builder).toHaveAttribute('data-db-viewport', 'display_builder_theme_test.md')
    // Single active state: only the picked button keeps the primary variant.
    await expect(button('Medium (768 px)')).toHaveAttribute('variant', 'primary')
    await expect(switcher.locator('.switch-viewport-btn[variant="primary"]')).toHaveCount(1)

    await button('Extra Large (1280 px)').click()
    expect(await paneStyle('--db-preview-width')).toBe('1280px')
    await expect(builder).toHaveAttribute('data-db-viewport', 'display_builder_theme_test.xl')
  })

  await test.step(`Zoom scales the render, independent of the width`, async () => {
    const select = switcher.locator('.switch-zoom-select')
    await select.evaluate((el: HTMLElement & { value: string }) => {
      el.value = '0.5'
      el.dispatchEvent(new CustomEvent('sl-change', { bubbles: true }))
    })

    expect(await paneStyle('--db-preview-zoom')).toBe('0.5')
    await expect(builder).toHaveAttribute('data-db-zoom', '0.5')
    // The chosen device width is untouched by zooming.
    expect(await paneStyle('--db-preview-width')).toBe('1280px')
  })

  await test.step(`Responsive clears the simulated width`, async () => {
    await button('Responsive (fills the available width)').click()

    expect(await paneStyle('--db-preview-width')).toBe('')
    await expect(builder).not.toHaveClass(/viewport-active/)
    await expect(builder).toHaveAttribute('data-db-viewport', '')
  })
})

// The isolated-preview route renders the same island with the in_iframe option,
// which must emit the display alone - no iframe nested in an iframe, and no
// builder chrome, since the pane loads this URL directly.
// @see \Drupal\display_builder\Controller\ApiPreviewController::getDisplayPreview()
test('Preview isolated route', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  let previewUrl = ''

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
    await displayBuilder.dragComponentsAndTextfield('Isolated preview')
  })

  await test.step(`Read the pane's preview URL`, async () => {
    previewUrl = String(await page.locator('iframe[title="Live preview"]').first().getAttribute('src'))
    expect(previewUrl).toContain('/display-builder/preview/')
  })

  await test.step(`Route renders the display bare`, async () => {
    await page.goto(previewUrl)

    await expect(page.locator('body')).toContainText('Isolated preview')
    // in_iframe returns the sources directly, so neither the builder nor a
    // further preview iframe may appear.
    await expect(page.locator('.display-builder')).toHaveCount(0)
    await expect(page.locator('iframe[title="Live preview"]')).toHaveCount(0)
  })
})
