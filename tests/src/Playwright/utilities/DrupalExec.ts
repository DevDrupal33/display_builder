import { exec as execNode } from 'node:child_process'
import { promisify } from 'util'
import { getRootDir, getVendorDir } from './DrupalFilesystem'
import { DrupalSiteInstall } from '../fixtures/DrupalSite'
import * as path from 'node:path'
import * as utils from './utils'

// Promisify exec for async/await usage.
const execPromise = promisify(execNode)

/**
 * Execute a shell command in the Drupal root directory.
 *
 * If the environment variable DRUPAL_TEST_WEBSERVER_USER is set,
 * the command will be executed as that user using sudo.
 *
 * @throws Will throw an error if the command fails.
 *
 * @param {string} command - The shell command to execute.
 * @param {string} [cwd] - The current working directory to execute the command in. Defaults to Drupal root.
 * @returns {Promise<string>} The standard output from the command.
 */
export const exec = async (command: string, cwd?: string): Promise<string> => {
  let sudo = ``
  if (process.env.DRUPAL_TEST_WEBSERVER_USER && process.env.DRUPAL_TEST_WEBSERVER_USER.length > 0) {
    sudo = `sudo -u ${process.env.DRUPAL_TEST_WEBSERVER_USER} `
  }
  try {
    const { stdout }: { stdout: string } = await execPromise(`${sudo}${command}`, { cwd: cwd ?? getRootDir() })
    return stdout
  } catch (error) {
    console.error(error)
    throw new Error(error)
  }
}

/**
 * Execute a Drush command in the Drupal root directory.
 *
 * The command will be executed with the HTTP_USER_AGENT set to the
 * value from the DrupalSiteInstall object, and the --uri option set
 * to the URL from the DrupalSiteInstall object.
 *
 * If the environment variable DRUPAL_TEST_DRUSH_PREFIX is set,
 * it will be used as a prefix for the Drush command.
 *
 * @throws Will throw an error if the command fails.
 *
 * @param {string} command - The Drush command to execute.
 * @param {DrupalSiteInstall} drupalSiteInstall - The Drupal site installation details.
 * @returns {Promise<string>} The standard output from the command.
 */
export const execDrush = async (command: string, drupalSiteInstall: DrupalSiteInstall): Promise<string> => {
  const vendorDir = await getVendorDir()
  const rootDir = path.resolve(getRootDir())

  let cmdDrush = `HTTP_USER_AGENT=${drupalSiteInstall.userAgent} ${path.relative(
    rootDir,
    vendorDir,
  )}/bin/drush ${command} -y --uri=${drupalSiteInstall.url}`
  if (process.env.DRUPAL_TEST_DRUSH_PREFIX) {
    cmdDrush = `${process.env.DRUPAL_TEST_DRUSH_PREFIX} drush -y ${command}`
  }

  utils.debug(`${cmdDrush}`)

  try {
    const { stdout }: { stdout: string } = await execPromise(cmdDrush, { cwd: rootDir })
    return stdout.toString().trim()
  } catch (error) {
    console.error(error)
    throw new Error(error)
  }
}
