import { defineConfig } from '@playwright/test';
import { default as baseConfig } from './playwright.config'

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  ...baseConfig,
  webServer: {
    name: 'PHP',
    command: 'php -q -S localhost:8000 -t ../../../',
    url: 'http://localhost:8000',
    reuseExistingServer: true,
    stdout: 'ignore',
    stderr: 'pipe',
  },
})
