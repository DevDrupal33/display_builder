import { defineConfig, devices } from '@playwright/test';
import { default as baseConfig } from './playwright.config'

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  ...baseConfig,
  retries: 0,
  workers: 1,
  timeout: 160_000,
  reporter: [
    ['dot'],
    // ['list', { printSteps: true }],
  ],
  use: {
    baseURL: 'https://display-builder.ddev.site/',
    ignoreHTTPSErrors: true,

    trace: 'off',
    screenshot: {
      mode: 'off',
    },
    video: 'off',

    launchOptions: {
      // For --headed test, add some slow time.
      slowMo: 100,
    },
    // @see https://playwright.dev/docs/api/class-testoptions#test-options-action-timeout
    // actionTimeout: 10_000,
  },
  projects: [
    {
      name: 'ddev',
      use: {
        ...devices['Desktop Firefox'],
        baseURL: 'https://display-builder.ddev.site/',
        deviceScaleFactor: 1,
        viewport: { width: 1280, height: 920 },
      },
    },
  ],
})
