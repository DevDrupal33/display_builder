import { test as base } from '@playwright/test'
import { Drupal } from '../objects/Drupal'
import { exec } from '../utilities/DrupalExec'
import { hasDrush } from '../utilities/DrupalFilesystem'
import * as utils from '../utilities/utils'

export type DrupalSite = {
  dbPrefix: string
  userAgent: string
  sitePath: string
  url: string
  username: string
  password: string
  hasDrush: boolean
  teardown: Promise<string>
}

export type DrupalSiteInstall = {
  drupalSite: DrupalSite
}

/**
 * Drupal site fixture to manage Drupal installation and teardown for tests.
 * If the environment variable DRUPAL_TEST_SKIP_INSTALL is set to 'true',
 * the installation step will be skipped, assuming Drupal is already installed.
 * This is useful for debugging or when the test environment is already set up.
 *
 * The teardown function will remove the test site after all tests have run,
 * unless the environment variable PLAYWRIGHT_SKIP_TEARDOWN is set to 'true'.
 *
 * The installation process uses environment variables to customize the setup:
 * - DRUPAL_TEST_BASE_URL: The base URL for the Drupal site (required
 *   for installation).
 * - DRUPAL_TEST_DB_URL: The database connection string (required for
 *   installation).
 * - DRUPAL_TEST_SETUP_PROFILE: The installation profile to use
 *   (defaults to 'minimal' if not set).
 * - DRUPAL_TEST_SETUP_LANGCODE: The language code for the site (optional).
 * - DRUPAL_TEST_SETUP_FILE: A setup file to pre-configure the site
 *   (optional).
 * - PLAYWRIGHT_SKIP_TEARDOWN: If set to 'true', the teardown step will be
 *   skipped (optional, useful for debugging).
 * The fixture runs once per worker to optimize test execution time.
 */
export const drupalSite = base.extend<DrupalSiteInstall>({
  drupalSite: [
    async ({ }, use, workerInfo) => {
      if (process.env.DRUPAL_TEST_SKIP_INSTALL && process.env.DRUPAL_TEST_SKIP_INSTALL === 'true') {
        const withDrush = await hasDrush()
        utils.info('Drupal is installed, skip installation for tests')
        await use({
          userAgent: '',
          sitePath: '',
          url: process.env.DRUPAL_TEST_BASE_URL ?? '/',
          hasDrush: withDrush,
          teardown: async () => {
            return Promise.resolve('')
          },
        })
        return
      }

      utils.debug('Install Drupal with test environment...')

      const setupFile = process.env.DRUPAL_TEST_SETUP_FILE ? `--setup-file "${process.env.DRUPAL_TEST_SETUP_FILE}"` : ''
      const installProfile = `--install-profile "${process.env.DRUPAL_TEST_SETUP_PROFILE || 'minimal'}"`
      const langcodeOption = process.env.DRUPAL_TEST_SETUP_LANGCODE
        ? `--langcode "${process.env.DRUPAL_TEST_SETUP_LANGCODE}"`
        : ''
      const dbOption =
        process.env.DRUPAL_TEST_DB_URL && process.env.DRUPAL_TEST_DB_URL.length > 0
          ? `--db-url "${process.env.DRUPAL_TEST_DB_URL}"`
          : ''
      const stdout = await exec(
        `php ./core/scripts/test-site.php install ${setupFile} ${installProfile} ${langcodeOption} --base-url ${process.env.DRUPAL_TEST_BASE_URL} ${dbOption} --json`,
      )

      const installData = JSON.parse(stdout.toString())

      const withDrush = await hasDrush()

      await use({
        dbPrefix: installData.db_prefix,
        userAgent: installData.user_agent,
        sitePath: installData.site_path,
        url: process.env.DRUPAL_TEST_BASE_URL ?? '',
        hasDrush: withDrush,
        teardown: async () => {
          if (process.env.PLAYWRIGHT_SKIP_TEARDOWN && process.env.PLAYWRIGHT_SKIP_TEARDOWN === 'true') {
            return Promise.resolve('')
          }
          return await exec(
            `php core/scripts/test-site.php tear-down --no-interaction --db-url ${process.env.DRUPAL_TEST_DB_URL}-${workerInfo.workerIndex} ${installData.db_prefix}`,
          )
        },
      })
    },
    { scope: 'worker' },
  ],
})

type DrupalObj = {
  drupal: Drupal
}

export const drupal = base.extend<DrupalObj>({
  drupal: [
    async ({ page, drupalSite }, use) => {
      const drupal = new Drupal({ page, drupalSite })
      await use(drupal)
    },
    { auto: true },
  ],
})

export const beforeAllTests = base.extend<{ forEachWorker: void }>({
  forEachWorker: [
    async ({ drupalSite }, use) => {
      await use()
      // This code runs after all the tests in the worker process.
      drupalSite.teardown()
    },
    { scope: 'worker', auto: true },
  ], // automatically starts for every worker.
})

export const beforeEachTest = base.extend<{ forEachTest: void }>({
  forEachTest: [
    async ({ drupal }, use) => {
      // This code runs before every test.
      await drupal.setTestCookie()
      await use()
    },
    { auto: true },
  ], // automatically starts for every test.
})
