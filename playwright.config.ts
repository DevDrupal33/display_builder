import { defineConfig, devices } from '@playwright/test'

import dotenv from 'dotenv'
import path from 'path'

dotenv.config({ path: path.resolve(__dirname, '.env'), quiet: true })

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './tests/src/Playwright',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 1,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. @see https://playwright.dev/docs/test-reporters */
  reporter: process.env.CI
    ? [
      // ['dot'],
      ['list', { printSteps: true }],
      ['html', { open: 'never' }],
      ['junit', { outputFile: 'test-results/playwright.xml' }],
      ['./tests/src/Playwright/utilities/reporter.ts', { level: process.env?.PLAYWRIGHT_DEBUG_LEVEL || 'error' }],
    ]
    : [
      ['list', { printSteps: true }],
      ['html'],
      ['./tests/src/Playwright/utilities/reporter.ts', { level: process.env?.PLAYWRIGHT_DEBUG_LEVEL || 'info' }],
    ],
  /* https://playwright.dev/docs/test-timeouts */
  timeout: process.env?.DRUPAL_TEST_SKIP_INSTALL ? 120_000 : 180_000,
  /* Shared settings for all the projects below. @see https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    // Playwright require ending slash.
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-base-url
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    ignoreHTTPSErrors: true,

    /* Collect trace when retrying the failed test. @see https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    /* Take screenshot automatically on test failure */
    screenshot: {
      mode: 'only-on-failure',
      fullPage: true,
    },
    video: {
      mode: 'retain-on-failure',
      size: { width: 1280, height: 900 },
    },
    // Default timeout for each Playwright action in milliseconds, defaults to 0 (no timeout).
    // Quicker fail on local tests if skip install.
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-action-timeout
    actionTimeout: process.env.CI ? 10_000 : process.env.DRUPAL_TEST_SKIP_INSTALL ? 4_000 : 20_000,
  },
  /* Configure snapshot folder */
  expect: {
    toMatchAriaSnapshot: {
      pathTemplate: './tests/src/Playwright/__snapshots__/{testFilePath}/{arg}{ext}',
    },
  },
  /* Configure projects for major browsers */
  projects: [
    {
      name: 'setup',
      testMatch: /global\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        deviceScaleFactor: 1,
        viewport: { width: 1920, height: 1080 }
      },
      dependencies: ['setup'],
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        deviceScaleFactor: 1,
        viewport: { width: 1920, height: 1080 },
      },
      dependencies: ['setup'],
    },
    // Not working on Fedora and fail on ci for now, only for local Docker or Ubuntu.
    {
      name: 'webkit',
      use: {
        ...devices['Desktop Safari'],
        deviceScaleFactor: 1,
        viewport: { width: 1920, height: 1080 }
      },
      dependencies: [ 'setup' ],
    },
  ],
})
