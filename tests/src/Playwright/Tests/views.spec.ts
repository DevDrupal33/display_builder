import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'
import { Drupal } from '../objects/Drupal'

// The Views UI is very ajax-heavy and was the sole source of flakiness here.
// The "set / switch / disable profile" lifecycle it used to exercise is now
// covered deterministically, without any ajax, by the kernel test
// DisplayExtenderTest::testProfileLifecycle(). What genuinely needs a browser
// is left below: opening the builder for a view display, and the drag/render.
// The view is seeded from a config template (display_builder_views_test) and
// duplicated per test, so no Views UI ajax is involved in the setup.

/**
 * Duplicate the template view under a unique id, keeping its Display Builder
 * profile, so the builder can be opened without touching the Views UI.
 *
 * @param {Drupal} drupal - The Drupal object.
 * @param {string} name - The unique view id/label to create.
 * @param {string} path - The unique page path for the duplicated display.
 * @returns {Promise<void>}
 */
async function seedViewFromTemplate (drupal: Drupal, name: string, path: string): Promise<void> {
  // PHP variables are escaped (\$) so the shell running php:eval does not
  // expand them.
  const php = [
    `\\$v = \\Drupal::entityTypeManager()->getStorage('view')->load('test_db_view_render')->createDuplicate();`,
    `\\$v->set('id', '${name}');`,
    `\\$v->set('label', '${name}');`,
    `\\$d = \\$v->get('display');`,
    `\\$d['page_1']['display_options']['path'] = '${path}';`,
    `\\$v->set('display', \\$d);`,
    `\\$v->save();`,
  ].join(' ')
  await drupal.drush(`php:eval "${php}"`)
}

test.beforeEach('Setup', async ({ drupal }) => {
  await drupal.installModules([ 'views', 'views_ui', 'display_builder_views', 'display_builder_views_test' ])
})

test('Views build and render', { tag: [ '@base' ] }, async ({ page, drupal, displayBuilder }) => {
  const testName = utils.createRandomString()
  const name = `test_db_view_render_${testName}`

  await test.step(`Create User and login`, async () => {
    await displayBuilder.createUserAndLogin(drupal, [ 'test_db_view' ])
  })

  await test.step(`Seed a view with a Display Builder profile`, async () => {
    await seedViewFromTemplate(drupal, name, `test-render-${testName}`)
  })

  await test.step(`Check the base display`, async () => {
    await page.goto(config.viewsDbList)
    await page.locator(`[data-link-builder="${config.viewsPrefix}${name}__page_1"]`).click()
    await displayBuilder.shoelaceReady()

    // Test the proper blocks are available for Views context.
    // @todo check the proper views row.
    const sources = {
      view_header: '[View] Header',
      view_exposed: '[View] Exposed',
      view_attachment_before: '[View] Attachment_before',
      // @todo enable within 3542796
      // view_rows: '[View] Rows',
      view_pager: '[View] Pager',
      view_attachment_after: '[View] Attachment_after',
      view_more: '[View] More',
      view_footer: '[View] Footer',
      view_feed_icons: '[View] Feed_icons',
    }
    await displayBuilder.expectBlocksAvailable(sources)

    await expect(page.locator('.db-island-builder .db-dropzone--root')).toMatchAriaSnapshot({
      name: 'view-base.aria.yml',
    })
  })

  await test.step(`Check the display`, async () => {
    await displayBuilder.highlight()

    await displayBuilder.dragComponentsAndTextfield('I am a test textfield in a slot in a View!')

    await displayBuilder.publishDisplayBuilder()
  })
})
