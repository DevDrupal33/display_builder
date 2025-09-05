import { expect, Locator, Page } from '@playwright/test'

import config from '../playwright.config.loader'

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
   * @returns {Promise<void>}
   */
  async saveDisplayBuilder(): Promise<void> {
    await this.htmxReady()
    await this.page.locator('.db-toolbar__end [data-keyboard="S"]').click()
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
          customElements.whenDefined('sl-tooltip'),
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
    await this.page.goto(config.dbViewUrl.replace('{db_id}', dbName))
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
    await this.page.goto(config.dbAddUrl)
    await this.page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
    await this.page.getByLabel('Profile').selectOption('test')
    if (fixture) {
      await this.page.getByLabel('Initial data').selectOption(fixture)
    }
    await this.page.getByRole('button', { name: 'Save' }).click()
    // await expect(this.page.getByRole('heading', { name: `Display builder: ${dbName}` })).toBeVisible()
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
    await this.page.goto(config.dbList)
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

  async dragSimpleComponentsWithToken(tokenTest: string = 'I am a test token in a slot!'): Promise<void> {
    await this.toggleSidebarView()
    await this.dragElementFromLibraryById(
      'Components',
      'test_simple',
      this.page.locator(`.db-island-builder > slot.db-dropzone`)
    )
    const componentSimpleSlot = this.page.locator(`.db-island-builder .test_simple .slot_test [data-slot-id="slot_1"]`)
    await this.dragElementFromLibraryById('Blocks', 'token', componentSimpleSlot)
    await this.setElementValue(
      this.page.locator(`.db-island-builder [data-instance-title="Token"]`).first(),
      tokenTest,
      [
        {
          action: 'fill',
          locator: this.page.locator('#edit-value'),
        },
      ]
    )
  }

  /**
   * Test the blocks tab to ensure context blocks are available in library.
   *
   * @async
   * @param {Object} blocks - The list of expected blocks in library and in the builder.
   * @param {boolean} builder - Check in the builder as well..
   * @returns {Promise<void>}
   */
  async expectBlocksAvailable(blocks: Object, builder: boolean = true): Promise<void> {
    await this.openLibrariesTab('Blocks')

    // @todo handle hx-vals instead of simple button.
    for (const [ source, label ] of Object.entries(blocks)) {
      // await expect(this.page.locator(`.db-island-block_library [hx-vals*="${source}"]`)).toHaveCount(1)
      await expect(this.page.locator('.db-island-block_library').getByRole('button', { name: label })).toHaveCount(1)
      if (builder) {
        await expect(this.page.locator(`.db-island-builder [data-instance-title="${label}"]`)).toHaveCount(1)
      }
    }
  }

  /**
   * Test the preview tab with an Aria snapshot and go back to the builder.
   *
   * @async
   * @param {string} snapshotName - The expected Aria snapshot string.
   * @param {string} locatorClass - The locator parameter, default '.db-island-preview'.
   * @returns {Promise<void>}
   */
  async expectPreviewAriaSnapshot(snapshotName: string, locatorClass: string = '.db-island-preview'): Promise<void> {
    await this.page.getByRole('tab', { name: 'Preview' }).click()
    await expect(this.page.locator(locatorClass)).toMatchAriaSnapshot({ name: snapshotName })
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
    await this.page.waitForTimeout(config.keyboardTimeout)
    await this.htmxReady()
  }
}
