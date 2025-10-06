import { defineConfig, devices } from '@playwright/test'

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
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
  // @todo set retry when tests are stabilized.
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    [ 'list' ],
    [ 'html', { host: '0.0.0.0', open: 'never' } ],
    [ 'junit', { outputFile: 'test-results/playwright.xml' } ],
    [ './tests/src/Playwright/utilities/reporter.ts', { level: process.env.PLAYWRIGHT_DEBUG_LEVEL || 'info' } ],
  ],
  /* https://playwright.dev/docs/test-timeouts */
  timeout: process.env.DRUPAL_TEST_SKIP_INSTALL ? 180_000 : 240_000,
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    // Playwright require ending slash.
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-base-url
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    ignoreHTTPSErrors: true,

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    /* Take screenshot automatically on test failure */
    screenshot: {
      mode: 'only-on-failure',
      fullPage: true,
    },
    launchOptions: {
      // For --headed test, add some slow time.
      slowMo: 200,
    },
    // Quicker fail on local tests if skip install.
    actionTimeout: process.env.CI ? undefined : process.env.DRUPAL_TEST_SKIP_INSTALL ? 2_000 : undefined,
    /* For https://playwright.dev/docs/locators#locate-by-test-id */
    testIdAttribute: 'data-test',
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
        viewport: { width: 2560, height: 1440 }
      },
      dependencies: [ 'setup' ],
    },
    {
      name: 'firefox',
      use: {
        ...devices['Desktop Firefox'],
        deviceScaleFactor: 1,
        viewport: { width: 2560, height: 1440 },
      },
      dependencies: [ 'setup' ],
    },
    // Enable on compatible env.
    // {
    //   name: 'webkit',
    //   use: {
    //     ...devices['Desktop Safari'],
    //     deviceScaleFactor: 1,
    //     viewport: { width: 1920, height: 1080 },
    //   },
    //   dependencies: [ 'setup' ],
    // },
  ],

  /* Run your local dev server before starting the tests */
  /* Comment for a local running server */
  // webServer: {
  //   command: 'php -S localhost:8000 -t ../../../',
  //   url: 'http://localhost:8000',
  //   reuseExistingServer: !process.env.CI,
  // },
})
