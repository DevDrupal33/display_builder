import { exec as execNode } from 'node:child_process';
import { promisify } from 'util';
import { getRootDir, getVendorDir } from './DrupalFilesystem';
import { DrupalSiteInstall } from '../fixtures/DrupalSite';

import * as path from 'node:path';
const execPromise = promisify(execNode);

export const exec = async (command: string, cwd?: string): Promise<string> => {
  let sudo = ``;
  if (
    process.env.DRUPAL_TEST_WEBSERVER_USER &&
    process.env.DRUPAL_TEST_WEBSERVER_USER.length > 0
  ) {
    sudo = `sudo -u ${process.env.DRUPAL_TEST_WEBSERVER_USER} `;
  }
  try {
    const { stdout }: { stdout: string } = await execPromise(
      `${sudo}${command}`,
      { cwd: cwd ?? getRootDir() },
    );
    return stdout;
  } catch (error) {
    console.error(error);
    throw new Error(error);
  }
};

export const execDrush = async (
  command: string,
  drupalSiteInstall: DrupalSiteInstall,
): Promise<string> => {
  const vendorDir = await getVendorDir();
  const rootDir = path.resolve(getRootDir());

  let cmdDrush = `HTTP_USER_AGENT=${drupalSiteInstall.userAgent} ${path.relative(rootDir, vendorDir)}/bin/drush ${command} -y --uri=${drupalSiteInstall.url}`;
  if (process.env.DRUPAL_TEST_DRUSH_PREFIX) {
    cmdDrush = `${process.env.DRUPAL_TEST_DRUSH_PREFIX} drush -y ${command}`;
  }

  // console.log(`[Info] Drush command: ${cmdDrush}`);
  try {
    const { stdout }: { stdout: string } = await execPromise(
      cmdDrush,
      { cwd: rootDir },
    );
    return stdout.toString().trim();
  } catch (error) {
    console.error(error);
    throw new Error(error);
  }
};
