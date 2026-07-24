import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

// The HighlightToggle Floating island is four independent checkboxes, not one
// lens (deliberately not Divi's single "X-ray"), each toggling its own
// .display-builder--highlight-{slot,component,block,space} class. toolbar.spec
// only exercises the "Select all" shortcut; this pins that each checkbox flips
// its own class on and off without touching the others, which is the whole
// point of them being separate. The menu is stay-open-on-select, so every
// click lands on the same open dropdown.
// @see \Drupal\display_builder\Plugin\display_builder\Island\HighlightToggle
// @see assets/js/highlight.js
test('Highlight toggles are independent', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const builder = page.locator('.display-builder')
  // value -> the class suffix it controls (they happen to match).
  const toggles = ['slot', 'component', 'block', 'space']
  const item = (value: string) =>
    page.locator(`[data-highlight-menu] sl-menu-item[value="${value}"]`).locator('slot').nth(1)

  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await test.step(`Open the Highlight dropdown`, async () => {
    await expect(page.getByTestId('floating_highlight')).toBeVisible()
    await page.getByTestId('floating_highlight').click()
  })

  for (const value of toggles) {
    await test.step(`"${value}" flips only its own class`, async () => {
      await item(value).click()
      await expect(builder).toHaveClass(new RegExp(`display-builder--highlight-${value}\\b`))
      // The other three stay off.
      for (const other of toggles.filter((v) => v !== value)) {
        await expect(builder).not.toHaveClass(new RegExp(`display-builder--highlight-${other}\\b`))
      }

      await item(value).click()
      await expect(builder).not.toHaveClass(new RegExp(`display-builder--highlight-${value}\\b`))
    })
  }
})
