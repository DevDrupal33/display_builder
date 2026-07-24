import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import { Drupal } from '../objects/Drupal'
import { Displaybuilder } from '../objects/DisplayBuilder'
import config from '../playwright.config.loader'
import * as utils from '../utilities/utils'

// Server-Sent Events drive cross-session live updates: when someone else edits
// the same instance, ApiSseController::sse() re-renders the islands and pushes
// each as a `island-<builder>-<island>` event, which htmx's sse extension
// swaps into the matching pane (every pane carries sse-swap=<its html id>,
// @see ProfileViewBuilder). It is cross-session by construction - the stream
// deliberately SKIPS the editing session (sessionId compare) and only relays
// *other* sessions' edits - and the stream itself is opened by the
// Collaboration island's sse-connect, off in every other test profile. Hence
// the dedicated test_collaboration profile and a second browser context.
//
// Timing: ApiSseController polls every REFRESH_WINDOW (2s) and relays an edit
// while it is newer than STALE (3s), so session A should see B's drop within a
// few seconds without reloading - the generous expect timeout is that window,
// not flakiness slack.
// @see \Drupal\display_builder\Controller\ApiSseController
// @see \Drupal\display_builder\Controller\ApiControllerBase::saveSseData()
// @see \Drupal\display_builder\Plugin\display_builder\Island\Collaboration
test('SSE relays another session edit', { tag: [ '@extra' ] }, async ({ page, browser, drupalSite, drupal, displayBuilder }) => {
  const instanceId = `test_${utils.createRandomString()}`
  const builderUrl = config.pageViewUrl.replace('{instance_id}', instanceId)
  const componentA = page.locator('.db-island-builder [data-test="test_simple"]')

  await test.step(`Session A: login and create the shared instance`, async () => {
    await displayBuilder.createUserAndLogin(drupal)
    await drupal.drush(`
      php:eval "\\Drupal\\display_builder_page_layout\\Entity\\PageLayout::create([
        'id' => '${instanceId}',
        'label' => 'SSE ${instanceId}',
        'sources' => [['source_id' => '']],
        \\Drupal\\display_builder\\DisplayBuildableInterface::PROFILE_PROPERTY => '${config.testProfileCollaborationId}',
      ])->save();"
    `.trim())

    await page.goto(builderUrl)
    await displayBuilder.shoelaceReady()
    // A starts on an empty canvas; the SSE stream is now open.
    await expect(componentA).toHaveCount(0)
  })

  // Second browser context = a genuinely separate session (own cookies), which
  // is what the sessionId comparison in the stream requires to relay at all.
  const contextB = await browser.newContext({ ignoreHTTPSErrors: true })
  const pageB = await contextB.newPage()
  const drupalB = new Drupal({ page: pageB, drupalSite })
  const displayBuilderB = new Displaybuilder({ page: pageB })

  try {
    await test.step(`Session B: login (second context) and open the same instance`, async () => {
      await drupalB.setTestCookie()
      await displayBuilderB.createUserAndLogin(drupalB)
      await pageB.goto(builderUrl)
      await displayBuilderB.shoelaceReady()
    })

    await test.step(`Session B drops a component`, async () => {
      await displayBuilderB.dragElementFromLibraryById(
        'component',
        'test_simple',
        pageB.locator('.db-dropzone--root').first(),
        { x: 40, y: 15 },
      )
      await expect(pageB.locator('.db-island-builder [data-test="test_simple"]')).toHaveCount(1)
    })

    await test.step(`Session A sees it live, without reloading`, async () => {
      // No page.reload() here - the pane must be swapped by the SSE stream.
      await expect(componentA).toHaveCount(1, { timeout: 15_000 })
    })
  }
  finally {
    await contextB.close()
  }
})
