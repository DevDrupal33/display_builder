import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'

test('Sequential drops', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal,config.testProfileBuilderId)
  })

  await displayBuilder.highlight()

  // Regression test for a bug where SortableJS's emptyInsertThreshold set to
  // 0 (falsy) fully disabled its "nearest empty sortable" fallback
  // detection, which made drops into the builder increasingly unreliable
  // within the same page load (a page refresh reset it). Three sequential
  // drops into the same (non-nested) root dropzone must all succeed without
  // a reload in between.
  for (let i = 1; i <= 3; i++) {
    await test.step(`Drop Textfield #${i} into the root dropzone`, async () => {
      await displayBuilder.dragElementFromLibraryById(
        'block',
        'textfield',
        page.locator(`.db-island-builder > div.db-dropzone`).first(),
        { x: 40, y: 15 },
      )
      await expect(page.locator('.db-island-builder [data-node-type="textfield"]')).toHaveCount(i)
    })
  }
})

test('Sequential nested drops', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  await test.step(`Create Page Layout and login`, async () => {
    await displayBuilder.initTestsWithPageLayout(drupal, config.testProfileBuilderId)
  })

  await displayBuilder.highlight()

  await test.step(`Drop grid #1 into the root`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'grid_row_3',
      page.locator(`.db-island-builder > div.db-dropzone`).first(),
      { x: 40, y: 15 },
    )
    await expect(page.getByTestId('display_builder_theme_test:grid_row_3')).toHaveCount(1)
  })

  await test.step(`Drop grid #2 into the grid slot`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'component',
      'grid_row_3',
      page.getByTestId('dropzone_col_2_content').first(),
      { x: 40, y: 15 },
    )
    await expect(page.getByTestId('display_builder_theme_test:grid_row_3')).toHaveCount(2)
  })

  await test.step(`Drop textfield #1 into the root`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      page.locator(`.db-island-builder > div.db-dropzone`).first(),
      { x: 40, y: 15 },
    )
    await expect(page.locator('.db-island-builder [data-node-type="textfield"]')).toHaveCount(1)
  })

  await test.step(`Drop textfield #2 into the col`, async () => {
    await displayBuilder.dragElementFromLibraryById(
      'block',
      'textfield',
      page.getByTestId('dropzone_col_2_content').nth(1),
      { x: 40, y: 15 },
    )
    await expect(page.locator('.db-island-builder [data-node-type="textfield"]')).toHaveCount(2)
  })

})
