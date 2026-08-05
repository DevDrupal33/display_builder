import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

test('Config form', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await displayBuilder.highlight()

  await test.step('Drag component', async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'test_complex',
      page.locator(`.db-island-builder > div.db-dropzone`).first(),
      { x: 40, y: 15 },
    )
  })

  await test.step(`Apply config on component`, async () => {
    await page.getByTestId('display_builder_theme_test:test_complex').click()

    await expect(page.getByTestId('tab_contextual_contextual_form')).toBeVisible()

    await page.getByTestId('tab_contextual_contextual_form').click()
    await displayBuilder.shoelaceReady()

    await displayBuilder.setContextualFormValue(
      page.getByRole('button', { name: 'Attributes', exact: true }),
      page.getByRole('textbox', { name: 'Attributes', exact: true }),
      'data-foo="bar"',
    )

    await displayBuilder.setContextualFormValue(
      page.getByRole('button', { name: 'Test attributes', exact: true }),
      page.getByRole('textbox', { name: 'Test attributes', exact: true }),
      'data-bar="foo"',
    )

    await displayBuilder.setContextualFormValue(
      page.getByRole('button', { name: 'Label', exact: true }),
      page.getByRole('textbox', { name: 'Label', exact: true }),
      'Test Label',
    )

    await displayBuilder.setContextualFormValue(
      page.getByRole('button', { name: 'Open', exact: true }),
      page.getByRole('checkbox', { name: 'Open', exact: true }),
      'Test Label',
      'uncheck',
    )

    await displayBuilder.setContextualFormValue(
      page.getByRole('button', { name: 'Duration', exact: true }),
      page.getByRole('spinbutton', { name: 'Duration', exact: true }),
      '100',
    )

    // Click somewhere for htmx submit
    await page.getByTestId('tab_view_builder').click()
    await displayBuilder.shoelaceReady()
    await displayBuilder.htmxReady()
  })

  await test.step(`Check result`, async () => {
    await expect(page.locator('.db-island-builder [data-test="test-parent"]')).toHaveAttribute('data-foo', 'bar')
    await expect(page.locator('.db-island-builder [data-test="test-child"]')).toHaveAttribute('data-bar', 'foo')
  })

  // Targeted locator assertions instead of a full-tree aria snapshot: the
  // Preview must reflect the submitted config. The component renders its props
  // into the heading ("label: <Label>, open: <Open>, duration: <Duration>"), so
  // asserting the heading text pins every value we set above without redding on
  // an unrelated markup change elsewhere in the tree.
  await test.step(`Preview reflects the config`, async () => {
    await page.locator('[data-db-split-toggle]').click()
    await displayBuilder.shoelaceReady()

    const heading = page.locator('iframe[title="Live preview"]').first().contentFrame().getByRole('heading', { level: 2 })
    await expect(heading).toContainText('label: Test Label')
    await expect(heading).toContainText('open: false')
    await expect(heading).toContainText(/duration: \d+/)
  })
})
