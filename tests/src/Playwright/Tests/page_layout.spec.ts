import { expect } from '@playwright/test'
import { test } from '../fixtures/loader'
import config from '../playwright.config.loader'
import * as utils from '../utilities/utils'

// A page layout creates no route, it repaints a page Drupal already serves. So
// every path used here is a real routed page answering 200: the '/test/*' ones
// come from display_builder_page_layout_test, the default layout is checked on
// /user/{uid}. Bound to an unrouted path a layout still renders, but on the 404
// response, which renders the same either way and would hide a layout that
// stopped being selected. The status is asserted for that reason.
// @see \Drupal\display_builder_page_layout_test\Controller\TestPageController
// @see \Drupal\display_builder_page_layout\EventSubscriber\PageVariantSubscriber
// @see \Drupal\display_builder_page_layout\Plugin\display_builder\Buildable\PageLayout

// Page layout front-end integration, the counterpart to entity_view.spec:
// build content in a page layout, publish, then visit the path its request_path
// condition targets and assert the built content renders as the page.
test('Page layout renders its built content on the front end', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
  const id = utils.createRandomString()
  const instanceId = `test_${id}`
  const path = `/test/${id}`
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
    const response = await page.goto(path)
    expect(response?.status()).toBe(200)
    await expect(page.locator('.page-wrapper')).toContainText(marker)
  })
})

// The layouts installed from config by display_builder_page_layout_test are the
// closest thing to a site that adopted Display Builder from an export, so one
// of them is checked exactly as a visitor gets it: no login, no builder, no
// publish, just the path.
// @see tests/modules/display_builder_page_layout_test/config/install/
test('Page layout installed from config renders at its path', { tag: [ '@extra' ] }, async ({ page }) => {
  const response = await page.goto('test/default')
  expect(response?.status()).toBe(200)

  // The comment naming the instance is the layout signing the page.
  // @see modules/display_builder_page_layout/templates/page.html.twig
  expect(await page.content()).toContain(`Display Builder Page Layout: ${config.pagePrefix}test_default_page`)
  // Components, blocks and text sources, at every nesting level of the fixture.
  await expect(page.locator('.page-wrapper')).toContainText('Text 1')
  await expect(page.locator('.page-wrapper')).toContainText('Text 9')
  await expect(page.locator('.page-wrapper')).toContainText('this is a full HTML text')
  // The layout draws the page, it does not replace what the route serves: the
  // main_page_content source still holds the output of the controller.
  await expect(page.locator('.page-wrapper')).toContainText('Page layout test page: default')
})

test.describe('Default page layout', () => {
  // A default layout carries no condition, so it catches every non-admin page
  // of the site until it is removed. The worker installs one Drupal site for
  // all of its tests, so leaving it behind would repaint the front end under
  // every test that runs after this one.
  test.afterEach(async ({ drupal }) => {
    await drupal.drush(`php:eval "\\Drupal\\display_builder_page_layout\\Entity\\PageLayout::load('default')?->delete();"`)
  })

  // The test site ships conditional layouts only, so the default one is created
  // here the way a user creates it: from the starting point chooser, which is
  // the only route in and is offered exactly once.
  // @see \Drupal\display_builder_page_layout\Form\PageLayoutForm::buildStartingPointForm()
  test('The starting point chooser seeds the default layout', { tag: [ '@extra' ] }, async ({ page, drupal, displayBuilder }) => {
    await test.step(`Login`, async () => {
      await displayBuilder.createUserAndLogin(drupal)
    })

    await test.step(`The chooser offers the three starting points`, async () => {
      await page.goto(config.pageListUrl)
      // The local action and the uncovered pages message both link to it.
      await page.getByRole('link', { name: 'Create the default page layout' }).first().click()

      // The import is offered on the default layout only, where continuity with
      // the current site is what is wanted.
      await expect(page.getByRole('radio', { name: 'Start from your current site' })).toBeVisible()
      await expect(page.getByRole('radio', { name: 'Minimal Drupal page' })).toBeVisible()
      await expect(page.getByRole('radio', { name: 'Blank' })).toBeVisible()
      // The site holds one default layout, so it is named for the user, and it
      // is never given a condition.
      await expect(page.getByRole('textbox', { name: 'Label' })).toHaveCount(0)
      await expect(page.getByRole('textbox', { name: 'Pages' })).toHaveCount(0)
    })

    await test.step(`Saving seeds the layout and closes the offer`, async () => {
      await page.getByLabel('Profile').selectOption(config.testProfileBuilder)
      await page.getByRole('radio', { name: 'Minimal Drupal page' }).check()
      await page.getByRole('button', { name: 'Save' }).click()
      await drupal.expectMessage('Created new page layout Default.')
      // One default layout is all there is room for, so the action link goes.
      await expect(page.getByRole('link', { name: 'Create the default page layout' })).toHaveCount(0)
    })

    await test.step(`It builds the pages no other layout matches`, async () => {
      const uid = await drupal.getUserId()
      const response = await page.goto(`user/${uid}`)
      expect(response?.status()).toBe(200)

      expect(await page.content()).toContain(`Display Builder Page Layout: ${config.pagePrefix}default`)
      // The minimal starting point places the blocks a Drupal page needs, and
      // never the theme page shell, so the page template of the theme is gone.
      await expect(page.locator('h1.page-title')).toBeVisible()
      await expect(page.locator('.page-content')).toHaveCount(0)
    })

    await test.step(`A layout with a condition still wins over it`, async () => {
      await page.goto('test/default')
      expect(await page.content()).toContain(`Display Builder Page Layout: ${config.pagePrefix}test_default_page`)
    })
  })
})
