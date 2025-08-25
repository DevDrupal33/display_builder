import { existsSync, readFileSync } from 'fs'
import { resolve } from 'path'
import { exec as execNode } from 'node:child_process'
import * as path from 'node:path'
import { promisify } from 'util'

// Promisify exec for async/await usage.
const exec = promisify(execNode)

/**
 * Get the Drupal root directory by traversing up from the current working directory.
 *
 * @throws Will throw an error if the Drupal root directory cannot be found.
 *
 * @returns {string} The Drupal root directory.
 */
export const getRootDir = (): string => {
  let dir = `${process.cwd()}/${process.env.DRUPAL_WEB_ROOT}`
  let found = false
  for (let i = 0; i < 15; i++) {
    if (existsSync(`${dir}/index.php`) && existsSync(`${dir}/core/lib/Drupal.php`)) {
      found = true
      break
    }
    dir = resolve(dir, '..')
  }
  if (!found) {
    throw new Error('Unable to find Drupal root directory.')
  }
  return dir
}

/**
 * Get the Composer root directory by checking for composer.json in the current
 * or parent directory.
 *
 * @throws Will throw an error if the Composer root directory cannot be found.
 *
 * @returns {Promise<string | null>} The Composer root directory or null if not found.
 */
export const getComposerDir = async (): Promise<string | null> => {
  const rootDir = getRootDir()
  let composerRoot = rootDir
  if (!existsSync(`${composerRoot}/composer.json`)) {
    composerRoot = `${rootDir}/..`
  }
  return composerRoot
}

/**
 * Get the vendor directory by querying Composer configuration.
 *
 * @throws Will throw an error if the vendor directory cannot be found.
 *
 * @returns {Promise<string | null>} The vendor directory path or null if not found.
 */
export const getVendorDir = async (): Promise<string | null> => {
  const composerRoot = await getComposerDir()
  if (existsSync(`${composerRoot}/composer.json`)) {
    try {
      const { stdout }: { stdout: string } = await exec('composer config vendor-dir --no-interaction', {
        cwd: composerRoot,
      })
      return path.resolve(`${composerRoot}/${stdout.toString().trim()}`)
    } catch (error) {
      throw new Error(`Could not locate vendor directory: ${error}`)
    }
  }
  throw new Error('Could not locate vendor directory.')
}

/**
 * Get the Drupal module directory by reading Composer installer paths.
 *
 * @throws Will throw an error if the module directory cannot be found.
 *
 * @returns {Promise<string | null>} The Drupal module directory or null if not found.
 */
export const getModuleDir = async (): Promise<string | null> => {
  let modulePath = 'modules/contrib'
  const composerRoot = await getComposerDir()

  try {
    const composerJson = readFileSync(`${composerRoot}/composer.json`, 'utf8')
    const composerData = JSON.parse(composerJson)
    const installerPaths = composerData.extra['installer-paths']
    Object.keys(installerPaths).forEach(key => {
      if (installerPaths[key].includes('type:drupal-module')) {
        modulePath = key.replace('/{$name}', '')
      }
    })
  } catch (error) {
    console.error('Unable to locate module directory, using default value.')
  }

  return `${composerRoot}/${modulePath}`
}

/**
 * Check if Drush is available in the vendor/bin directory.
 *
 * @throws Will throw an error if the vendor directory cannot be found.
 *
 * @returns {Promise<boolean>} True if Drush is available, false otherwise.
 */
export const hasDrush = async (): Promise<boolean> => {
  const vendorDir = await getVendorDir()
  return existsSync(`${vendorDir}/bin/drush`)
}
