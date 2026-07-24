import { expect, Page } from '@playwright/test'
import { exec, execDrush } from '../utilities/DrupalExec'
import * as nodePath from 'node:path'
import * as fs from 'node:fs'
import { getModuleDir, getRootDir } from '../utilities/DrupalFilesystem'
import type { DrupalSite, DrupalSiteInstall } from '../fixtures/DrupalSite'
import * as utils from '../utilities/utils'
import config from '../playwright.config.loader'

/**
 * The `Drupal` class provides a suite of utility methods for interacting with a Drupal site
 * during Playwright-based end-to-end tests. It supports both Drush-based and UI-based operations,
 * allowing for flexible automation of common Drupal tasks such as user management, role and permission
 * assignment, module installation, and authentication.
 *
 * @remarks
 * This class is designed to facilitate automated testing of Drupal sites by abstracting
 * common administrative and user actions. It can operate with or without Drush, falling back
 * to UI interactions when Drush is unavailable.
 *
 * @example
 * ```typescript
 * const drupal = new Drupal({ page, drupalSite });
 * await drupal.loginAsAdmin();
 * await drupal.createRole({ name: 'editor' });
 * await drupal.addPermissions({ role: 'editor', permissions: ['edit articles'] });
 * await drupal.createUser({ username: 'john', password: 'secret', email: 'john@example.com', roles: ['editor'] });
 * ```
 *
 * @property {Page} page - The Playwright page instance used for browser automation.
 * @property {DrupalSite} drupalSite - The configuration object representing the Drupal site under test.
 */
export class Drupal {
  readonly page: Page
  readonly drupalSite: DrupalSite

  constructor({ page, drupalSite }: { page: Page; drupalSite: DrupalSite }) {
    this.page = page
    this.drupalSite = drupalSite
  }

  /**
   * Sets the cookie which determines which simpletest multisite to use.
   */
  async setTestCookie(): Promise<void> {
    const context = this.page.context()
    const simpletestCookie = {
      name: 'SIMPLETEST_USER_AGENT',
      value: encodeURIComponent(this.drupalSite.userAgent),
      url: this.drupalSite.url,
    }
    // const playwrightCookie = {
    //   name: 'DB_PLAYWRIGHT',
    //   value: 'true',
    //   url: this.drupalSite.url,
    // }
    // await context.addCookies([ simpletestCookie, playwrightCookie ])
    await context.addCookies([simpletestCookie])
  }

  /**
   * Gets drupalSettings from the browser window object.
   */
  async getDrupalSettings() {
    // Wait for the page to finish loading JavaScript
    // cspell:ignore domcontentloaded
    await this.page.waitForLoadState('domcontentloaded')
    // Give a brief moment for any remaining JS to execute
    await this.page.waitForTimeout(100)

    return await this.page.evaluate(() => {
      return window.drupalSettings || undefined
    })
  }

  hasDrush(): boolean {
    return this.drupalSite.hasDrush
  }

  disableDrush(): void {
    this.drupalSite.hasDrush = true
  }

  enableDrush(): void {
    this.drupalSite.hasDrush = false
  }

  setDrush(enabled: boolean): void {
    this.drupalSite.hasDrush = enabled
  }

  async drush(command: string): Promise<string> {
    return await execDrush(command, this.drupalSite)
  }

  async setupTestSite(): Promise<void> {
    const moduleDir = await getModuleDir()
    await this.enableTestExtensions()
    await this.writeBaseUrl()
  }

  async loginAsAdmin(uid: number = 1): Promise<void> {
    utils.debug('Login with Drush...')
    const logInUrl = await this.drush(`user:login --uid=${uid} --no-browser`)
    await this.page.goto(logInUrl)
  }

  async login(
    { username, password }: { username: string; password?: string } = {
      username: this.drupalSite.username,
      password: this.drupalSite.password,
    },
  ): Promise<void> {
    if (!this.drupalSite.hasDrush && !password) {
      throw new Error('Password is required when drush is not available.')
    }
    const page = this.page
    if (this.drupalSite.hasDrush) {
      const loginUrl = await this.drush(`user:login --name=${username} --no-browser`)
      await page.goto(loginUrl)
    } else {
      await page.goto(`${this.drupalSite.url}/${config.logInUrl}`)
      await page.locator('[data-drupal-selector="edit-name"]').fill(username)
      await page.locator('[data-drupal-selector="edit-pass"]').fill(password ?? 'test_admin')
      await page.locator('[data-drupal-selector="edit-submit"]').click()
    }
    await expect(page.locator('h1')).toHaveText(username)
  }

  async logout(): Promise<void> {
    await this.page.goto(`${this.drupalSite.url}/${config.logOutUrl}/confirm`)
    await this.page.getByRole('button', { name: 'Log out' }).click()
    let cookies = await this.page.context().cookies()
    cookies = cookies.filter(cookie => cookie.name.startsWith('SESS') || cookie.name.startsWith('SSESS'))
    expect(cookies).toHaveLength(0)
  }

  async isLoggedIn(): Promise<boolean> {
    const userId = await this.getUserId()
    return userId > 0
  }

  /**
   * Gets the uid of the currently logged in user.
   */
  async getUserId(): Promise<number> {
    const drupalSettings = await this.getDrupalSettings()
    if (drupalSettings && drupalSettings.user && drupalSettings.user.uid) {
      return parseInt(drupalSettings.user.uid, 10)
    }
    return 0
  }

  async createRole({ name }: { name: string }): Promise<void> {
    if (this.drupalSite.hasDrush) {
      await this.drush(`role:create ${name}`)
    } else {
      const page = this.page
      await page.goto(`${this.drupalSite.url}/admin/people/roles/add`)
      await page.locator('[data-drupal-selector="edit-label"]').fill(name)
      await page.locator('[data-drupal-selector="edit-submit"]').click()
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText('has been added.')
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(name)
    }
  }

  async addPermissions({ role, permissions }: { role: string; permissions: string[] }): Promise<void> {
    if (this.drupalSite.hasDrush) {
      await this.drush(`role:perm:add ${role} '${permissions.join(',')}'`)
    } else {
      const page = this.page
      await page.goto(`${this.drupalSite.url}/admin/people/permissions`)
      for (const permission of permissions) {
        await page
          .locator(
            `[data-drupal-selector="edit-${this.normalizeAttribute(role)}-${this.normalizeAttribute(permission)}"]`,
          )
          .check()
      }
      await page.locator('[data-drupal-selector="edit-submit"]').click()
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText('The changes have been saved')
    }
  }

  async createUser({
    username,
    password,
    email,
    roles,
  }: {
    username: string
    password: string
    email: string
    roles: string[]
  }): Promise<void> {
    if (this.drupalSite.hasDrush) {
      await this.drush(`user:create ${username} --password=${password} --mail=${email}`)
      for (const role of roles) {
        await this.drush(`user:role:add '${role}' '${username}'`)
      }
    } else {
      const page = this.page
      await page.goto(`${this.drupalSite.url}/admin/people/create`)
      await page.locator('[data-drupal-selector="edit-mail"]').fill(email)
      await page.locator('[data-drupal-selector="edit-name"]').fill(username)
      await page.locator('[data-drupal-selector="edit-pass-pass1"]').fill(password)
      await page.locator('[data-drupal-selector="edit-pass-pass2"]').fill(password)
      for (const role of roles) {
        await page.locator(`[data-drupal-selector="edit-roles-${this.normalizeAttribute(role)}"]`).check()
      }
      await page.locator('[data-drupal-selector="edit-submit"]').click()
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText('Created a new user account for')
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(username)
      const href = await page.locator('//*[@data-drupal-messages]//a').getAttribute('href')
      const match = href?.match(/\/user\/(\d+)/)
      let userId: number | undefined
      if (match && match[1]) {
        userId = parseInt(match[1])
      }
      if (userId === undefined || isNaN(userId)) {
        throw new Error(`No user ID found for ${username}`)
      }
    }
  }

  async installModules(modules: string[]): Promise<void> {
    if (this.drupalSite.hasDrush) {
      await this.drush(`pm:enable ${modules.join(' ')}`)
    } else {
      await this.page.goto(config.modules)
      for (const module of modules) {
        await this.page.locator(`input[name="modules[${module}][enable]"]`).check()
      }
      await this.page.locator('[data-drupal-selector="edit-submit"]').click()
      if (await this.page.locator('[data-drupal-selector="system-modules-confirm-form"]').count()) {
        await this.page.locator('[data-drupal-selector="edit-submit"]').click()
      }
      for (const module of modules) {
        const checkbox = this.page.locator(`input[name="modules[${module}][enable]"]`)
        expect(checkbox).toBeTruthy()
        await expect(checkbox).toBeDisabled()
      }
    }
  }

  async createContentType(bundle: string, name: string): Promise<void> {
    if (this.drupalSite.hasDrush) {
      const cmd = `php:eval "Drupal\\node\\Entity\\NodeType::create([
          'type' => '${bundle}',
          'name' => 'Test ${name}',
        ])->save();"`.trim()
      await this.drush(cmd)
      await this.createBodyField(bundle, name)
    } else {
      await this.page.goto(config.contentTypesAdd)
      await this.page.getByLabel('Name', { exact: true }).fill(`Test ${name}`)
      await this.page.getByText('Save', { exact: true }).click()
      await this.expectMessage(`The content type Test ${name} has been added.`)
    }
  }

  async createBodyField(bundle: string, name: string): Promise<void> {
    if (this.drupalSite.hasDrush) {
      await this.drush(
        `field:create -y node ${bundle} --field-name=field_test_${name} --field-label="Body" --field-type=string_long --field-widget=string_textarea --is-required=0 --cardinality=1 --is-translatable=0`,
      )
    } else {
      throw new Error('Field creation without Drush is not supported!')
    }
  }

  async enableTestExtensions() {
    const settingsFile = nodePath.resolve(getRootDir(), `${this.drupalSite.sitePath}/settings.php`)
    fs.chmodSync(settingsFile, 0o775)
    return await exec(`echo '$settings["extension_discovery_scan_tests"] = TRUE;' >> ${settingsFile}`)
  }

  async writeBaseUrl() {
    // \Drupal\Core\StreamWrapper\PublicStream::baseUrl needs a base-url set,
    // otherwise it will default to $GLOBALS['base_url']. When a recipe is being
    // run via core/scripts/drupal, that defaults to core/scripts/drupal.
    const settingsFile = nodePath.resolve(getRootDir(), `${this.drupalSite.sitePath}/settings.php`)
    fs.chmodSync(settingsFile, 0o775)
    return await exec(
      `echo '$settings["file_public_base_url"] = "${this.drupalSite.url}/${this.drupalSite.sitePath}/files";' >> ${settingsFile}`,
    )
  }

  async getSettings() {
    const value = await this.page.evaluate(() => {
      return window.drupalSettings
    })
    return value
  }

  async ajaxReady(): Promise<void> {
    await expect(this.page.locator('.ajax-progress, .ajax-progress--throbber, .ajax-progress--message')).toHaveCount(0)
  }

  async expectMessage(text: string): Promise<void> {
    // The status box needs a moment to appear.
    const message = this.page.getByRole('contentinfo', { name: 'Status message' })
    expect(await message.textContent()).toContain(text)
  }

  async clearCache(): Promise<void> {
    await this.page.goto(config.performance)
    await this.page.locator('input[data-drupal-selector="edit-clear"]').click()
    await expect(this.page.locator('//*[@data-drupal-messages]')).toContainText('Caches cleared')
  }

  async setPreprocessing({ css, javascript }: { css?: boolean; javascript?: boolean }): Promise<void> {
    if (this.drupalSite.hasDrush) {
      if (css === true) {
        await this.drush(`config:set system.performance css.preprocess 1`)
      } else {
        await this.drush(`config:set system.performance css.preprocess 0`)
      }
      if (javascript === true) {
        await this.drush(`config:set system.performance js.preprocess 1`)
      } else {
        await this.drush(`config:set system.performance js.preprocess 0`)
      }
    } else {
      const cssCheckbox =
        'form[data-drupal-selector="system-performance-settings"] [data-drupal-selector="edit-preprocess-css"]'
      const jsCheckbox =
        'form[data-drupal-selector="system-performance-settings"] [data-drupal-selector="edit-preprocess-js"]'
      await this.page.goto('/admin/config/development/performance')
      if (css !== undefined) {
        await this.page.locator(cssCheckbox).setChecked(css)
      }
      if (javascript !== undefined) {
        await this.page.locator(jsCheckbox).setChecked(javascript)
      }
      await this.page
        .locator('form[data-drupal-selector="system-performance-settings"] [data-drupal-selector="edit-submit"]')
        .click()

      if (css !== undefined) {
        if (css) {
          await expect(this.page.locator(cssCheckbox)).toBeChecked()
        } else {
          await expect(this.page.locator(cssCheckbox)).not.toBeChecked()
        }
      }

      if (javascript !== undefined) {
        if (javascript) {
          await expect(this.page.locator(jsCheckbox)).toBeChecked()
        } else {
          await expect(this.page.locator(jsCheckbox)).not.toBeChecked()
        }
      }
    }
  }

  /**
   * Inputs text into a specific CKEditor instance on a Playwright-controlled page.
   *
   * This function waits for CKEditor elements to be present on the page,
   * then attempts to input the provided text into the specified editor instance.
   * If no instance number is provided, it defaults to the first editor found.
   *
   * @async
   * @param {string} text - The text to be input into the CKEditor.
   * @param {number} [instanceNumber=0] - The zero-based index of the CKEditor instance
   *                                      to target (default is 0, i.e., the first instance).
   * @throws {Error} Throws an error if the specified CKEditor instance is
   *                 not found or if unable to input text.
   * @returns {Promise<void>}
   *
   * @example
   * // Input text into the first CKEditor instance
   * await inputTextIntoCKEditor(page, 'Hello, world!');
   *
   * // Input text into the third CKEditor instance (index 2)
   * await inputTextIntoCKEditor(page, 'Hello, third editor!', 2);
   */
  async inputTextIntoCKEditor(text: string, instanceNumber: number = 0): Promise<void> {
    // Wait for the text areas to appear.
    await this.page.waitForSelector('.ck-editor__editable')

    // Type into the specified CKEditor instance found on the page.
    await this.page.evaluate(
      ({ inputText, editorIndex }) => {
        const editorElements = document.querySelectorAll('.ck-editor__editable')
        if (editorElements.length > editorIndex) {
          const targetEditorElement = editorElements[editorIndex]

          // Attempt to get the CKEditor instance.
          const editorInstance =
            targetEditorElement.ckeditorInstance ||
            // eslint-disable-next-line no-undef
            Object.values(CKEDITOR.instances)[editorIndex] ||
            // eslint-disable-next-line no-undef
            Object.values(ClassicEditor.instances)[editorIndex]

          if (editorInstance) {
            // Set the data in the editor.
            if (typeof editorInstance.setData === 'function') {
              editorInstance.setData(inputText)
            } else if (
              typeof editorInstance.getData === 'function' &&
              typeof editorInstance.insertHtml === 'function'
            ) {
              // For older CKEditor versions.
              editorInstance.setData('')
              editorInstance.insertHtml(inputText)
            } else {
              throw new Error('Unable to set data: setData method not found')
            }
          } else {
            throw new Error(`CKEditor instance not found at index ${editorIndex}`)
          }
        } else {
          throw new Error(`No CKEditor element found at index ${editorIndex}`)
        }
      },
      {
        inputText: text,
        editorIndex: instanceNumber,
      },
    )
  }

  /**
   * Takes a screenshot of the current page and saves it to the specified file.
   * The screenshot is saved in the 'test-results' directory, which is created if it
   * does not exist. The filename is determined based on the environment (CI or local).
   *
   * @async
   * @param {string} fileName - The name of the file to save the screenshot
   * @param {boolean} [fullPage=true] - Whether to capture the full page (default is true).
   * @throws {Error} Throws an error if the screenshot cannot be taken or saved.
   * @returns {Promise<void>}
   */
  async screenshot(fileName: string, fullPage: boolean = true): Promise<void> {
    let path: string
    if (process.env.CI && process.env.CI === 'true') {
      path = `${getRootDir()}/../test-results/${fileName}`
    } else {
      path = nodePath.resolve(__dirname, `../../../../test-results/${fileName}`)
    }
    await this.page.screenshot({ path, fullPage })
  }

  normalizeAttribute(attribute: string): string {
    return attribute.replaceAll(' ', '-').replaceAll('_', '-')
  }
}
