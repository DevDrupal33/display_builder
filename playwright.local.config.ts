import { defineConfig } from '@playwright/test';
import { default as baseConfig } from './playwright.config'

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  ...baseConfig,
  retries: 0,
  timeout: 240_000,
  reporter: [
    // ['dot'],
    // ['list', { printSteps: true }],
    ['html'],
    // ['./tests/src/Playwright/utilities/reporter.ts', { level: process.env?.PLAYWRIGHT_DEBUG_LEVEL || 'info' }],
  ],
  use: {
    baseURL: `${process.env.DRUPAL_TEST_BASE_URL}/`,
    ignoreHTTPSErrors: true,

    trace: 'on',
    screenshot: {
      mode: 'on',
      fullPage: true,
    },
    video: 'on',

    launchOptions: {
      // For --headed test, add some slow time.
      slowMo: 300,
    },
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-action-timeout
    actionTimeout: 30_000,
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
