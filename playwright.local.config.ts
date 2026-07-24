import { defineConfig } from '@playwright/test';
import { default as baseConfig } from './playwright.config'

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  ...baseConfig,
  retries: 1,
  workers: undefined,
  timeout: 180_000,
  reporter: [
    ['dot'],
    // ['list', { printSteps: true }],
    ['html'],
  ],
  snapshotPathTemplate: '__screenshots__{/projectName}/{testFilePath}/{arg}{ext}',
  expect: {
    toMatchAriaSnapshot: {
      pathTemplate: './tests/src/Playwright/__snapshots__/{testFilePath}/{arg}{ext}',
    },
    // @see https://playwright.dev/docs/test-timeouts#expect-timeout
    timeout: 10_000,
  },
  use: {
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    ignoreHTTPSErrors: true,

    trace: 'retain-on-first-failure',
    screenshot: {
      mode: 'only-on-failure',
      fullPage: true,
    },
    video: {
      mode: 'retain-on-failure',
      size: { width: 1280, height: 900 },
    },
    launchOptions: {
      // For --headed test, add some slow time.
      slowMo: 100,
    },
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-action-timeout
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  webServer: {
    name: 'PHP',
    // PHP's built-in server is single-threaded by default.
    // PHP_CLI_SERVER_WORKERS forks it so requests are actually concurrent.
    command: 'PHP_CLI_SERVER_WORKERS=8 php -q -S localhost:8000 -t ../../../',
    url: 'http://localhost:8000',
    reuseExistingServer: true,
    stdout: 'ignore',
    stderr: 'pipe',
  },
})
