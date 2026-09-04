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
//
// The Preview of this display is deliberately not asserted here. It renders
// the display on its own page through a sub-request, two page pipelines deep,
// which this runner resolves inconsistently under parallel load, and
// ViewPagePreviewTest already pins the behavior that matters: the draft shows
// in the preview and never leaks to an ordinary visitor.

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
    // No result on any site: the aria snapshot below is the empty state, and
    // a site with content would otherwise fill the rows.
    `\\$d['default']['display_options']['filters']['type'] = ['id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'entity_type' => 'node', 'entity_field' => 'type', 'plugin_id' => 'bundle', 'value' => ['db_no_such_type' => 'db_no_such_type']];`,
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
    // The exact node titles, which are the source plugin labels. They used to
    // be matched loosely, by accessible name, which is a substring match - so
    // '[View] Attachment_before' passed against a node actually titled
    // '[View] Attachment before'.
    const sources = {
      view_header: '[View] Header',
      view_exposed: '[View] Exposed form',
      view_attachment_before: '[View] Attachment before',
      view_rows: '[View] Rows',
      view_pager: '[View] Pager',
      view_attachment_after: '[View] Attachment after',
      view_more: '[View] More',
      view_footer: '[View] Footer',
      view_feed_icons: '[View] Feed icons',
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
