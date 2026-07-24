import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import * as utils from '../utilities/utils'

// Page layout front-end integration, the counterpart to entity_view.spec:
// build content in a page layout, publish, then visit the path its request_path
// condition targets and assert the built content renders as the page. A page
// layout replaces the page display variant on any non-admin route matching its
// condition (PageVariantSubscriber), including the 404 route for an otherwise
// unrouted path — which is exactly how the seeded fixtures render at /test-*.
// @see \Drupal\display_builder_page_layout\EventSubscriber\PageVariantSubscriber
// @see \Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout
test('Page layout renders its built content on the front end', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const id = utils.createRandomString()
  const instanceId = `test_${id}`
  const path = `/db-page-layout-${id}`
  const marker = `Rendered by the page layout ${id}`

  await test.step(`Login and create a page layout bound to a path`, async () => {
    await displayBuilder.createUserAndLogin(drupal)
    await drupal.drush(`
      php:eval "\\Drupal\\display_builder_page_layout\\Entity\\PageLayout::create([
        'id' => '${instanceId}',
        'label' => 'Test ${id}',
        'conditions' => ['request_path' => ['id' => 'request_path', 'negate' => FALSE, 'pages' => '${path}']],
        'sources' => [['source_id' => '']],
        \\Drupal\\display_builder\\DisplayBuildableInterface::PROFILE_PROPERTY => '${config.testProfileBuilderId}',
      ])->save();"
    `.trim())
  })

  await test.step(`Build and publish content in the builder`, async () => {
    await page.goto(config.pageViewUrl.replace('{instance_id}', instanceId))
    await displayBuilder.shoelaceReady()
    await displayBuilder.dragComponentsAndTextfield(marker)
    // Deselect the node so its contextual panel does not swallow the publish.
    await page.getByTestId('tab_view_builder').click()
    await displayBuilder.htmxReady()
    await displayBuilder.publishDisplayBuilder()
  })

  await test.step(`Visiting the path renders the published content`, async () => {
    await page.goto(path)
    await expect(page.locator('.page-wrapper')).toContainText(marker)
  })
})
