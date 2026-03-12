import fs from 'fs'
import path from 'path'

// Get the config directory
const configDir = path.join(__dirname, 'config')

// Find all playwright.*.config.ts files in the config folder
const configFiles = fs
  .readdirSync(configDir)
  .filter(file => file.startsWith('playwright.') && file.endsWith('.config.ts'))

let mergedConfig: { [key: string]: any } = {}

// Merge all configs, starting with playwright.drupal.config.ts if present
for (const file of configFiles) {
  const config = require(path.join(configDir, file)).default
  mergedConfig = { ...mergedConfig, ...config }
}

export default mergedConfig
