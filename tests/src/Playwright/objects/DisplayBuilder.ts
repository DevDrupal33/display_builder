import { expect, Locator, Page } from '@playwright/test'

import dbConfig from '../playwright.db.config'

export class Displaybuilder {
  readonly page: Page

  constructor({ page }: { page: Page }) {
    this.page = page
  }

  /**
   * Toggles the sidebar first drawer in the Display Builder UI.
   *
   * @async
   * @param {string} [targetId='library'] - The target ID for the toolbar button. Default is the Libraries button.
   * @returns {Promise<void>}
   */
  async toggleSidebarView(targetId: string = 'library'): Promise<void> {
    const sidebarFirst = this.page.locator('#db-first-drawer')
    const toolbarButton = this.page.locator(`.db-toolbar__start [data-target="${targetId}"]`)
    await expect(toolbarButton).toBeVisible()

    if (await sidebarFirst.isVisible()) {
      await toolbarButton.click()
      await expect(sidebarFirst).toBeHidden()
    } else {
      await toolbarButton.click()
      await expect(sidebarFirst).toBeVisible()
    }
  }

  /**
   * Opens the Libraries drawer and blocks in the Display Builder UI.
   *
   * @async
   * @returns {Promise<void>}
   */
  async openLibrariesTab(name: string = 'Blocks'): Promise<void> {
    const sidebarFirst = this.page.locator('#db-first-drawer')
    if (await sidebarFirst.isHidden()) {
      await this.toggleSidebarView()
    }
    await expect(sidebarFirst.getByRole('tab', { name, exact: true })).toBeVisible()
    await sidebarFirst.getByRole('tab', { name, exact: true }).locator('div').click()

    await this.htmxReady()
  }

  /**
   * Move a component in the builder, library must be open.
   *
   * @async
   * @param {Locator} element - The element to drag to the target.
   * @param {Locator} target - The target where the component must be dragged.
   * @returns {Promise<void>}
   */
  async dragElementFromLibraryById(
    type: string = 'Components',
    id: string,
    target: Locator,
    targetPosition: any = {
      x: 20,
      y: 10,
    }
  ): Promise<void> {
    await this.openLibrariesTab(type)
    const element = this.page.locator(`.db-island-library [hx-vals*="${id}"]`).first()
    await expect(element).toBeVisible()
    await this.dragElementFromLibrary(type, element, target, targetPosition)
  }

  /**
   * Move a component in the builder, library must be open.
   *
   * @async
   * @param {Locator} element - The element to drag to the target.
   * @param {Locator} target - The target where the component must be dragged.
   * @returns {Promise<void>}
   */
  async dragElementFromLibrary(
    type: string = 'Components',
    element: Locator,
    target: Locator,
    targetPosition: any = {
      x: 20,
      y: 10,
    }
  ): Promise<void> {
    await this.openLibrariesTab(type)
    await this.dragElement(element, target, targetPosition)
  }

  /**
   * Move a component in the builder, library must be open.
   *
   * @async
   * @param {Locator} element - The element to drag to the target.
   * @param {Locator} target - The target where the component must be dragged.
   * @returns {Promise<void>}
   */
  async dragElement(
    element: Locator,
    target: Locator,
    targetPosition: any = {
      x: 20,
      y: 10,
    }
  ): Promise<void> {
    await this.htmxReady()

    await expect(target).toBeVisible()
    await expect(element).toBeVisible()

    // Js step by step drag.
    // await component.hover({ position: { x: 10, y: 10 } })
    // await expect(page.locator('.display-builder')).toContainClass('display-builder--onDrag')
    // await this.page.mouse.down()
    // await targetSlot.hover({ position: { x: 10, y: 10 } })
    // await this.page.mouse.up()
    // await expect(page.locator('.display-builder')).not.toContainClass('display-builder--onDrag')

    // Position is important, otherwise the drag is not working. X must > 10.
    await element.dragTo(target, {
      force: true,
      targetPosition,
    })

    await this.htmxReady()
  }

  /**
   * Drags a token block into a target slot and sets its value in a Playwright test.
   *
   * @async
   * @param {Page} page - The Playwright Page object representing the browser this.page.
   * @param {Locator} element - The Locator for the element to interact with.
   * @param {string} value - The string value to set in the instance form.
   * @returns {Promise<void>}
   */
  async setElementValue(
    element: Locator,
    value: string,
    valuePath?: Array<{ action: 'click' | 'fill'; locator: Locator }>
  ): Promise<void> {
    await expect(element).toBeVisible()

    await element.click({ position: { x: 5, y: 10 } })
    await this.htmxReady()

    await expect(this.page.getByRole('dialog', { name: 'Settings' })).toBeVisible()

    if (valuePath && Array.isArray(valuePath)) {
      for (const step of valuePath) {
        await expect(step.locator).toBeVisible()
        if (step.action === 'click') {
          await step.locator.click()
        } else if (step.action === 'fill') {
          await step.locator.fill(value)
        }
      }
    } else {
      await this.page.locator('#edit-value').fill(value)
    }

    await this.page.getByRole('button', { name: 'Update' }).click()

    await this.htmxReady()
  }

  /**
   * Saves the current state in the Display Builder from the UI
   *
   * @async
   * @param {Page} page - The Playwright Page object representing the browser this.page.
   * @returns {Promise<void>}
   */
  async saveDisplayBuilder(page: Page): Promise<void> {
    await this.htmxReady()
    await this.page.getByRole('button', { name: 'Save' }).click()
    await this.htmxReady()
  }

  /**
   * Closes a specified drawer in the Display Builder UI.
   *
   * Locates the drawer by ID and clicks the Close button.
   * Verifies the drawer is hidden.
   *
   * @async
   * @param {string} [targetDrawer='first'] - Drawer identifier (default: 'first'). Can be first, second, both.
   * @returns {Promise<void>}
   */
  async closeDialog(targetDrawer: string = 'first'): Promise<void> {
    if (targetDrawer === 'both') {
      await this.closeDialog('first')
      await this.closeDialog('second')
      return
    }

    const drawer = this.page.locator(`#db-${targetDrawer}-drawer`)
    if (!drawer) {
      return
    }
    await drawer.getByRole('button', { name: 'Close' }).click()
    await expect(drawer).toBeHidden()
  }

  /**
   * Waits for all HTMX requests and transitions to complete on the this.page.
   *
   * Ensures there are no active HTMX request or transition elements.
   *
   * @async
   * @returns {Promise<void>}
   */
  async htmxReady(): Promise<void> {
    await expect(this.page.locator('.htmx-request, .htmx-settling, .htmx-swapping, .htmx-added')).toHaveCount(0)
  }

  /**
   * Waits for WebComponents to be loaded and ready.
   *
   * @async
   * @returns {Promise<void>}
   */
  async shoelaceReady(): Promise<void> {
    await this.page.addScriptTag({
      content: `
        Promise.allSettled([
          customElements.whenDefined('sl-button'),
          customElements.whenDefined('sl-button-group'),
          customElements.whenDefined('sl-drawer'),
          customElements.whenDefined('sl-input'),
          customElements.whenDefined('sl-menu'),
          customElements.whenDefined('sl-icon'),
          customElements.whenDefined('sl-icon-button'),
          customElements.whenDefined('sl-card'),
          customElements.whenDefined('sl-dropdown'),
          customElements.whenDefined('sl-tab'),
          customElements.whenDefined('sl-tab-group'),
          customElements.whenDefined('sl-tree'),
          customElements.whenDefined('sl-tree-item'),
        ]).then(() => console.log('[OK] Shoelace is loaded!'));
      `,
    })
  }

  /**
   * Refreshes the Display Builder instance view this.page.
   *
   * @async
   * @param {string} dbName - Name of the Display Builder instance.
   * @returns {Promise<void>}
   */
  async refresh(dbName: string): Promise<void> {
    await this.page.goto(dbConfig.dbViewUrl.replace('{db_id}', dbName))
    await this.htmxReady()
  }

  /**
   * Create a Display Builder instance from UI.
   *
   * @async
   * @param {string} dbName - Name of the Display Builder instance.
   * @param {string|null} fixture - (@todo) Name of the Display Builder fixture.
   * @returns {Promise<void>}
   */
  async createDisplayBuilderFromUi(dbName: string, fixture: string | null = null): Promise<void> {
    await this.page.goto(dbConfig.dbAddUrl)
    await this.page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
    await this.page.getByLabel('Profile').selectOption('test')
    if (fixture) {
      await this.page.getByLabel('Initial data').selectOption(fixture)
    }
    await this.page.getByRole('button', { name: 'Save' }).click()
    await expect(this.page.getByRole('heading', { name: `Display builder: ${dbName}` })).toBeVisible()
    await expect(this.page.getByRole('tab', { name: 'Builder' })).toBeVisible()
    await this.shoelaceReady()
  }

  /**
   * Delete a Display Builder instance.
   *
   * @async
   * @param {string} dbName - Name of the Display Builder instance.
   * @returns {Promise<void>}
   */
  async deleteDisplayBuilderFromUi(dbName: string): Promise<void> {
    await this.page.goto(dbConfig.dbList)
    await expect(this.page.getByRole('link', { name: dbName })).toBeVisible()
    await this.page
      .getByRole('row', { name: `${dbName}` })
      .getByRole('button')
      .click()
    await this.page.getByRole('link', { name: 'Delete', exact: true }).click()
    await expect(this.page.getByRole('heading', { name: `Do you want to delete ${dbName}?` })).toBeVisible()
    await this.page.getByRole('button', { name: 'Confirm' }).click()
    await expect(this.page.getByRole('link', { name: dbName })).not.toBeVisible()
  }

  /**
   * Test the preview tab with an Aria snapshot and go back to the builder.
   *
   * @async
   * @param {string} dbName - Name of the Display Builder instance.
   * @param {string}snapshot - The expected Aria snapshot string.
   * @returns {Promise<void>}
   */
  async expectPreviewAriaSnapshot(dbName: string, snapshot: string): Promise<void> {
    await this.page.getByRole('tab', { name: 'Preview' }).click()
    await expect(this.page.locator(`#island-${dbName}-preview`)).toMatchAriaSnapshot(snapshot)
    await this.page.getByRole('tab', { name: 'Builder' }).click()
  }

  /**
   * Simulates a keyboard shortcut in the Display Builder UI.
   *
   * @async
   * @param {string} key - The key or key combination to simulate (e.g., 'u' for undo, 'r' for redo).
   * @returns {Promise<void>}
   */
  async keyboardShortcut(key: string): Promise<void> {
    await this.page.keyboard.press(key)
    await this.page.waitForTimeout(dbConfig.keyboardTimeout)
    await this.htmxReady()
  }
}
