import { expect, Locator, Page } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// Click position required to avoid icon to intercept the click.
const position = { position: { x: 5, y: 5 } }

// ControlOrMeta is Playwright's platform accelerator - Meta on macOS, Control
// elsewhere - matching the "mod" token the keyboard mapping resolves to
// (@see components/display_builder/js/keyboard.js).
const key = {
  builder: 'c',
  libraries: 'l',
  logs: 'o',
  preview: 'p',
  scaffold: 'g',
  tree: 'n',
  expand: config.keyExpand,
  undo: 'ControlOrMeta+z',
  redo: 'ControlOrMeta+Shift+z',
  clear: 'Shift+C',
  publish: 'Shift+P',
}

test.beforeEach('Setup', async ({ drupal }) => {
  // Breakpoint is required for viewport switcher.
  await drupal.installModules([ 'breakpoint' ])
})

/**
 * Assert a feature is on or off.
 *
 * The Library/Tree sidebar is always present in the DOM (an inline
 * collapsible column, not an overlay drawer), so "on" is the absence of the
 * is-collapsed class rather than true visibility.
 *
 * @param {Locator} isOn - The locator carrying the on/off state.
 * @param {boolean} isSidebar - Whether that locator is the sidebar.
 * @param {boolean} on - The expected state.
 * @returns {Promise<void>}
 */
async function expectToggled (isOn: Locator, isSidebar: boolean, on: boolean): Promise<void> {
  if (isSidebar) {
    if (on) {
      await expect(isOn).not.toHaveClass(/is-collapsed/)
    } else {
      await expect(isOn).toHaveClass(/is-collapsed/)
    }
  } else if (on) {
    await expect(isOn).toBeVisible()
  } else {
    await expect(isOn).not.toBeVisible()
  }
}

// Buttons in toolbar configuration is based on
// display_builder.profile.test_base.yml ("Test full") - the only test profile
// enabling the scaffold and logs panels this test asserts.
// Any change to the profile will be reflected here.
test('Buttons', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileFullId)
  })

  // Test highlight and expand before any further tests to not conflict with
  // highlight or expand switch in the test to make it easier for position
  // and error snapshot.
  //
  // Highlight is no longer a toolbar button: it is the HighlightToggle
  // Floating island, a dropdown of four independent checkboxes toggling
  // .display-builder--highlight-{slot,component,block,space}.
  //
  // The menu is `stay-open-on-select`, so the "all" item is clicked twice on
  // the same open dropdown. It is matched on its value, not its label, because
  // the label flips to "Unselect all" once everything is checked.
  // @see \Drupal\display_builder\Plugin\display_builder\Island\HighlightToggle
  // @see assets/js/highlight.js
  await test.step(`Highlight`, async () => {
    const builder = page.locator('.display-builder')
    const selectAll = page.locator('[data-highlight-menu] sl-menu-item[value="all"]').locator('slot').nth(1)

    await page.getByTestId('floating_highlight').click()

    await selectAll.click()
    await expect(builder).toHaveClass(/display-builder--highlight-slot/)
    await expect(builder).toHaveClass(/display-builder--highlight-component/)

    await selectAll.click()
    await expect(builder).not.toHaveClass(/display-builder--highlight-slot/)
    await expect(builder).not.toHaveClass(/display-builder--highlight-component/)

    // Close the dropdown so it cannot overlay the next steps.
    await page.keyboard.press('Escape')
  })

  await test.step(`Expand`, async () => {
    const btn = page.locator('[data-island-action="expand"]')
    await testToggleFeature(page, btn, '.display-builder--expanded')
  })

  await test.step(`Minimal build instance`, async () => {
    await displayBuilder.dragElementFromLibraryById('block', 'textfield', page.locator('.db-dropzone--root').first())
    await displayBuilder.dragElementFromLibraryById('block', 'textfield', page.locator('.db-dropzone--root').first())
  })

  await test.step(`Libraries`, async () => {
    const btn = page.getByRole('button', { name: 'Libraries' })
    await testToggleFeature(page, btn, config.startDrawerID)
  })

  await test.step(`Navigator`, async () => {
    const btn = page.getByTestId('tab_view_scaffold')
    await testToggleTab(page, btn, '.db-island-scaffold', 'scaffold')
  })

  await test.step(`Scaffold`, async () => {
    const btn = page.getByTestId('tab_view_scaffold')
    await testToggleTab(page, btn, '.db-island-scaffold', 'scaffold')
  })

  await test.step(`Logs`, async () => {
    const btn = page.getByTestId('tab_view_logs')
    await testToggleTab(page, btn, '.db-island-logs', null)

    // const btn2 = page.getByTestId('tab_contextual_contextual_form'), { name: '[Test] Logs raw', exact: true })
    // await testToggleTab(page, btn2, '.db-island-test_logs_raw', 'logs_raw')
  })

  await test.step(`Preview`, async () => {
    const btn = page.getByTestId('tab_view_preview')
    await testToggleTab(page, btn, '.db-island-preview', 'preview')
  })

  async function testToggleFeature (page: Page, button: Locator, isOnLocator: string) {
    const isOn = page.locator(isOnLocator)
    const isSidebar = isOnLocator === config.startDrawerID
    // Earlier steps drag from the library, which leaves the sidebar open, so
    // assert the feature flips from whatever state it is currently in rather
    // than assuming it starts off.
    const wasOn = isSidebar ? !(await displayBuilder.isSidebarCollapsed(isOn)) : await isOn.isVisible()

    await button.click(position)
    await displayBuilder.shoelaceReady()
    await expectToggled(isOn, isSidebar, !wasOn)

    await button.click(position)
    await displayBuilder.shoelaceReady()
    await expectToggled(isOn, isSidebar, wasOn)
  }

  async function testToggleTab (page: Page, button: Locator, isOnLocator: string, name: string | null) {
    const builder = page.getByTestId('tab_view_builder')
    const isOn = page.locator(isOnLocator)

    await button.click(position)
    await displayBuilder.shoelaceReady()
    await expect(isOn).toBeVisible()

    await assertPanelMirrorsBuilder(page, isOn, name)

    await builder.click(position)
    await displayBuilder.shoelaceReady()
    await expect(isOn).not.toBeVisible()
  }
})

// Targeted-locator replacement for the panel aria snapshots: rather than pin the
// whole panel tree, assert what the toggle is meant to prove - that the panel
// renders the current builder content. The Scaffold panel exposes one
// data-node-type="textfield" row per textfield, so it must match the live count
// in the (still-in-DOM but hidden) Canvas; the Preview simply has to be
// non-empty (its exact output is covered by config.spec / entity_view.spec).
async function assertPanelMirrorsBuilder (page: Page, panel: Locator, name: string | null): Promise<void> {
  if (name === 'scaffold') {
    const count = await page.locator('.db-island-builder [data-node-type="textfield"]').count()
    await expect(panel.locator('[data-node-type="textfield"]')).toHaveCount(count)
  } else if (name === 'preview') {
    await expect(panel).not.toBeEmpty()
  }
}

test('Keyboard', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileFullId)
  })

  // Highlight has no keyboard shortcut since it became the HighlightToggle
  // Floating island (its button is built with a NULL action and no
  // data-keyboard-key), so there is nothing to test here - it is covered by
  // the 'Buttons' test above.
  //
  // Test expand before any further tests to not conflict with the expand
  // switch in the test to make it easier for position and error snapshot.
  await test.step(`expand`, async () => {
    await testToggleFeature(page, '.display-builder--expanded', key.expand)
  })

  await test.step(`Minimal build instance`, async () => {
    await displayBuilder.highlight()
    for (let i = 1; i <= 3; i++) {
      await displayBuilder.dragElementFromLibraryById(
        'block',
        'textfield',
        page.locator(`.db-island-builder > div.db-dropzone`).first(),
        { x: 40, y: 15 },
      )
      await expect(page.locator('.db-island-builder [data-node-type="textfield"]')).toHaveCount(i)
    }
  })

  await test.step(`Keyboard Undo / Redo / Clear`, async () => {
    const builderTextfield = page.locator(`.db-island-builder [data-node-type="textfield"]`)

    await displayBuilder.dragElementFromLibraryById('block', 'textfield', page.locator('.db-dropzone--root').first())
    await expect(builderTextfield).toHaveCount(4)
    await displayBuilder.keyboardShortcut(key.undo)

    await expect(builderTextfield).toHaveCount(3)
    await displayBuilder.keyboardShortcut(key.redo)

    await expect(builderTextfield).toHaveCount(4)
    await displayBuilder.keyboardShortcut(key.clear)

    await expect(builderTextfield).toHaveCount(4)
    await expect(page.locator('[data-island-action="undo"]')).toBeVisible()
    await expect(page.locator('[data-island-action="redo"]')).toBeVisible()
    await expect(page.locator('[data-island-action="clear"]')).toBeHidden()
  })

  // This is helping next tests.
  await test.step(`Set some values for next tests`, async () => {
    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-type="textfield"]`).first(),
      'I am first',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ],
    )
    await displayBuilder.setElementValue(
      page.locator(`.db-island-builder [data-node-type="textfield"]`).nth(1),
      'I am second',
      [
        {
          action: 'fill',
          locator: page.locator('#edit-value'),
        },
      ],
    )

    await page.getByRole('button', { name: 'Close' }).click()
    await displayBuilder.highlight()
  })

  await test.step(`Libraries`, async () => {
    await testToggleFeature(page, config.startDrawerID, key.libraries)
  })

  // @todo No Tree step: `tree` is status:false in all four test profiles.

  await test.step(`Scaffold`, async () => {
    await testToggleTab(page, '.db-island-scaffold', key.scaffold, 'scaffold')
  })

  await test.step(`Logs`, async () => {
    // @todo aria snapshot is hard with the table of logs, because of dates.
    await testToggleTab(page, '.db-island-logs', key.logs, null)
  })

  await test.step(`Preview`, async () => {
    await testToggleTab(page, '.db-island-preview', key.preview, 'preview')
  })

  async function testToggleFeature (page: Page, isOnLocator: string, keyShortcut: string) {
    const isOn = page.locator(isOnLocator)
    const isSidebar = isOnLocator === config.startDrawerID
    // Earlier steps drag from the library, which leaves the sidebar open, so
    // assert the feature flips from whatever state it is currently in rather
    // than assuming it starts off.
    const wasOn = isSidebar ? !(await displayBuilder.isSidebarCollapsed(isOn)) : await isOn.isVisible()

    await displayBuilder.keyboardShortcut(keyShortcut)
    await expectToggled(isOn, isSidebar, !wasOn)

    await displayBuilder.keyboardShortcut(keyShortcut)
    await expectToggled(isOn, isSidebar, wasOn)
  }

  async function testToggleTab (page: Page, isOnLocator: string, keyShortcut: string, name: string | null) {
    const isOn = page.locator(isOnLocator)

    await displayBuilder.keyboardShortcut(keyShortcut)
    await expect(isOn).toBeVisible()

    await assertPanelMirrorsBuilder(page, isOn, name)

    await displayBuilder.keyboardShortcut(key.builder)
    await expect(isOn).not.toBeVisible()
  }
})

test('Viewport switcher', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileFullId)
  })

  await test.step(`Switch viewport (compact)`, async () => {
    // ViewportSwitcher is a Floating island rendered exactly once, riding both
    // the 'builder' and 'preview' panes it attaches to. Its unique testid
    // addresses that single control.
    // @see \Drupal\display_builder\ProfileViewBuilder::buildFloatingControlsRegion()
    const viewportControls = page.getByTestId('floating_viewport')
    const switchViewport = viewportControls.locator('[data-island-action="viewport"]')
    const switchViewportList = viewportControls.locator('#listbox')

    // The simulated width is applied to the Builder/Preview panes themselves,
    // not to .display-builder__main, plus a viewport-active class on the root.
    // @see assets/js/viewport_switcher.js updateMainRegionWidth()
    const builder = page.locator('.display-builder')
    const builderPane = page.locator('.db-island-builder')

    await switchViewport.click()

    await page.getByRole('menuitem', { name: 'Extra small' }).locator('slot').nth(1).click()
    await expect(switchViewportList).not.toBeVisible()
    await expect(builderPane).toHaveCSS('max-width', '575px')
    await expect(builder).toHaveClass(/viewport-active/)

    await switchViewport.click()
    // Tooltip hover can mess the click, need to be on the right side of the button.
    await page.getByRole('menuitem', { name: 'Fluid (Current viewport)' }).locator('slot').nth(1).click({ position: { x: 130, y: 20 } })
    await expect(switchViewportList).not.toBeVisible()
    await expect(builder).not.toHaveClass(/viewport-active/)
  })
})
