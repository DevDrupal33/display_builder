import { defineConfig } from '@playwright/test';
import { default as baseConfig } from './playwright.config'

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  ...baseConfig,
  retries: 2,
  workers: 1,
  timeout: 240_000,
  reporter: [
    ['list', { printSteps: true }],
    ['html'],
  ],
  use: {
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    ignoreHTTPSErrors: true,

    trace: 'retain-on-first-failure',
    screenshot: {
      mode: 'only-on-failure',
      fullPage: true,
    },
    video: 'retain-on-failure',

    launchOptions: {
      // For --headed test, add some slow time.
      slowMo: 100,
    },
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-action-timeout
    actionTimeout: 15_000,
    testIdAttribute: 'data-test',
  },
  webServer: {
    name: 'PHP',
    command: 'php -q -S localhost:8000 -t ../../../',
    url: 'http://localhost:8000',
    reuseExistingServer: true,
    stdout: 'ignore',
    stderr: 'pipe',
  },
})
