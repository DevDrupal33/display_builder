import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

// A single test for the standalone CKEditor 5 that Display Builder hand-attaches
// to its 'textarea' source's plain <textarea> (data-db-ckeditor). Core's
// editor.module / #type => 'text_format' integration is deliberately bypassed,
// so the editor keeps the hidden <textarea> synced itself for HTMX to read.
// @see assets/js/textarea_ckeditor.js
// @see src/Plugin/UiPatterns/Source/TextareaWidget.php
//
// The instance is seeded with a textarea source directly (drush), so the test
// exercises the editor - not the flaky drag-and-drop of a library block.

test('Textarea CKEditor', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const id = utils.createRandomString()

  await test.step(`Create a page layout with a textarea source and login`, async () => {
    await displayBuilder.createUserAndLogin(drupal)

    const cmd = `
      php:eval "\\Drupal\\display_builder_page_layout\\Entity\\PageLayout::create([
        'id' => 'test_${id}',
        'label' => 'Test ${id}',
        'sources' => [['source_id' => 'textarea', 'source' => ['value' => 'seed']]],
        \\Drupal\\display_builder\\DisplayBuildableInterface::PROFILE_PROPERTY => '${config.testProfileBuilderId}',
      ])->save();"
    `.trim()
    await drupal.drush(cmd)

    await page.goto(config.pageViewUrl.replace('{instance_id}', `test_${id}`))
    await displayBuilder.shoelaceReady()
  })

  const editable = page.locator('.ck-editor__editable[contenteditable="true"]').first()

  await test.step(`CKEditor 5 attaches to the source textarea`, async () => {
    await page.locator('.db-island-builder [data-node-type="textarea"]').first().click({ force: true })
    await displayBuilder.htmxReady()
    await page.getByTestId('tab_contextual_contextual_form').click()

    // The rich editor replaces the plain textarea, which stays in the DOM
    // (hidden) so HTMX can read the value the editor keeps synced.
    await expect(editable).toBeVisible()
    await expect(page.locator('#edit-value')).toBeHidden()
  })

  await test.step(`Typed rich text is synced and saved`, async () => {
    await editable.click()
    // Replace the seeded value, then bold it via CKEditor's own shortcut.
    await page.keyboard.press('Control+a')
    await page.keyboard.type('Bold text')
    await page.keyboard.press('Control+a')
    await page.keyboard.press('Control+b')

    // The hidden <textarea> is kept current by the editor; submit the form.
    await page.getByTestId('contextual_form_update').click()
    await displayBuilder.htmxReady()
  })

  await test.step(`Rich text renders in preview`, async () => {
    await displayBuilder.publishDisplayBuilder()

    await expect(page.getByTestId('textarea')).toBeVisible()
    await page.locator('[data-db-split-toggle]').click()
    await displayBuilder.htmxReady()

    const iframe = page.locator('iframe[title="Live preview"]').first().contentFrame()
    await expect(iframe.locator('div').nth(1)).toBeVisible()
    await expect(iframe.locator('div').nth(1)).toContainText('Bold text')
  })
})
