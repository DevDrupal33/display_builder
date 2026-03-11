import { expect, Locator, Page } from '@playwright/test'
import config from '../playwright.config.loader'
import * as utils from '../utilities/utils'
import { Drupal } from './Drupal'

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
    const sidebarFirst = this.page.locator(config.startDrawerID)
    const toolbarButton = this.page.locator(`[data-target="${targetId}"]`)

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
    const sidebarFirst = this.page.locator(config.startDrawerID)
    if (await sidebarFirst.isHidden()) {
      await this.toggleSidebarView()
    }

    await sidebarFirst.getByRole('tab', { name, exact: true }).locator('div').click()

    await this.htmxReady()
  }

  /**
   * Drag an element from the library into a target by the element's id.
   *
   * @async
   * @param {string} type - The library tab/category to open (defaults to "Components").
   * @param {string} id - The identifier used to find the library element via its `hx-vals` attribute.
   * @param {Locator} target - Playwright Locator representing the drop target.
   * @param {any} targetPosition - Optional offset within the target to drop to (e.g. `{ x: 20, y: 10 }`).
   * @returns {Promise<void>}
   */
  async dragElementFromLibraryById(
    type: string = 'Components',
    id: string,
    target: Locator,
    targetPosition: any = {
      x: 10,
      y: 10,
    }
  ): Promise<void> {
    await this.openLibrariesTab(type)
    const element = this.page.locator(`.db-island-library [hx-vals*="${id}"]`).first()

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
    },
    sourcePosition: any = {
      x: 20,
      y: 10,
    },
  ): Promise<void> {
    await this.htmxReady()

    // await expect(target).toBeVisible()
    // await expect(element).toBeVisible()

    // await element.scrollIntoViewIfNeeded()
    // await target.scrollIntoViewIfNeeded()

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
      sourcePosition,
    })

    await this.htmxReady()
  }

  /**
   * Set a block textfield value in a Playwright test.
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

    await element.click({ position: { x: 5, y: 10 } })
    await this.htmxReady()

    if (valuePath && Array.isArray(valuePath)) {
      for (const step of valuePath) {
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
   * Publish the current state in the Display Builder from the UI
   *
   * @async
   * @returns {Promise<void>}
   */
  async publishDisplayBuilder(): Promise<void> {
    await this.page.locator('[data-island-action="publish"]').click()
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
   * Create a Display Builder instance from dev UI.
   *
   * @async
   * @param {string} dbName - Name of the Display Builder instance.
   * @param {string|null} fixture - (@todo) Name of the Display Builder fixture.
   * @returns {Promise<void>}
   */
  async createDisplayBuilderFromUi(dbName: string, fixture: string | null = null): Promise<void> {
    await this.page.goto(config.devAddInstance)
    await this.page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
    await this.page.getByLabel('Profile').selectOption('test')
    if (fixture) {
      await this.page.getByLabel('Initial data').selectOption(fixture)
    }
    await this.page.getByRole('button', { name: 'Save' }).click()

    await expect(this.page.getByRole('heading', { name: 'Display Builder instance devel' })).toBeVisible()
  }

  /**
   * Delete a Display Builder instance.
   *
   * @async
   * @param {string} dbName - Name of the Display Builder instance.
   * @returns {Promise<void>}
   */
  async deleteDisplayBuilderFromDevUi(dbName: string): Promise<void> {
    await this.page.goto(config.dbList)
    await this.page
      // .getByRole('row', { name: `Dev tools ${config.develPrefix}${dbName} Test` })
      .locator(`tr.${config.develPrefix}${dbName}`)
      .getByRole('button')
      .click()
    await this.page.getByRole('link', { name: 'Delete', exact: true }).click()
    await this.page.getByRole('button', { name: 'Confirm' }).click()
    await expect(this.page.getByRole('link', { name: dbName })).not.toBeVisible()
  }

  /**
   * Drag test component with a textfield in the UI.
   *
   * @async
   * @param {string} text - Text for the textfield, required.
   * @param {string|null} panel_locator - (Optional) The panel locator, default to '.db-island-builder'.
   * @param {string} componentId - (Optional) The id of the component in the library to drag, default to 'test_simple'.
   * @param {string} slot_id - (Optional) The slot id where the component should be dropped, default to 'slot_1'.
   * @returns {Promise<void>}
   */
  async dragComponentsAndTextfield(text: string, panel_locator: string|null = '.db-island-builder', componentId: string = 'test_simple', slot_id: string = 'slot_1'): Promise<void> {
    await this.dragElementFromLibraryById(
      'Components',
      componentId,
      this.page.locator(`${panel_locator} > div.db-dropzone`).first(),
      {x: 40, y: 15},
    )
    const component = this.page.locator(`${panel_locator} [data-slot-id="${slot_id}"]`).first()

    await this.dragElementFromLibraryById('Blocks', 'textfield', component, {x: 40, y: 15},)
    await this.setElementValue(
      this.page.locator(`${panel_locator} [data-node-type="textfield"]`).first(),
      text,
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
      await expect(this.page.locator(`.db-island-block_library [hx-vals*="${source}"]`)).toHaveCount(1)
      if (builder) {
        await expect(this.page.locator('.db-island-builder').getByRole('button', { name: label })).toHaveCount(1)
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
  }

  /**
   * Activate the Highlight.
   *
   * @async
   * @returns {Promise<void>}
   */
  async highlight(): Promise<void> {
    await this.page.locator('[data-island-action="highlight"]').click()
    await this.htmxReady()
  }

  /**
   * Activate the Fullscreen.
   *
   * @async
   * @returns {Promise<void>}
   */
  async fullscreen(): Promise<void> {
    await this.page.locator('[data-island-action="fullscreen"]').click()
    await this.htmxReady()
  }

  /**
   * Activate the Fullscreen and highlight.
   *
   * @async
   * @returns {Promise<void>}
   */
  async fullHighlight(): Promise<void> {
    await this.highlight()
    await this.fullscreen()
  }

  /**
   * Initialize tests by creating a Page Layout and logging in.
   *
   * @async
   * @param {Drupal} drupal - The Drupal object for managing Page Layouts and user authentication.
   * @returns {Promise<void>}
   */
  async initTestsWithPageLayout(drupal: Drupal): Promise<void> {
    await this.createUserAndLogin(drupal)
    await this.createPageLayout(drupal)
  }

  /**
   * Create a user with Display Builder role and log in.
   *
   * @async
   * @param {Drupal} drupal - The Drupal object for user management and login.
   * @param {string[]} [roles=[]] - An array of roles to assign to the user, append to basic role 'db_test_page'.
   * @param {string|null} id - The to use, default to random.
   * @returns {Promise<void>}
   */
  async createUserAndLogin(drupal: Drupal, roles: string[] = [], id: string | null = null): Promise<void> {
    if (!id) {
      id = utils.createRandomString()
    }
    const username = `test_${id}`
    roles = [ 'db_test_page', ...roles ]
    await drupal.createUser({
      username,
      password: id,
      email: `${username}}@${id}.com`,
      roles,
    })
    await drupal.login({ username })
  }

  /**
   * Create a Page Layout from Drush and view it.
   *
   * The Page Layout is created with the 'test' profile. A minimal source is
   * defined to ensure tests consistency.
   *
   * @async
   * @param {Drupal} drupal - The Drupal object.
   */
  async createPageLayout(drupal: Drupal): Promise<void> {
    const id = utils.createRandomString()
    const profile = 'test'

    const cmd = `
      php:eval "\\Drupal\\display_builder_page_layout\\Entity\\PageLayout::create([
        'id' => 'test_${id}',
        'label'=> 'Test ${id}',
        'sources' => [['source_id' => '']],
        \\Drupal\\display_builder\\DisplayBuildableInterface::PROFILE_PROPERTY => '${profile}',
      ])->save();"
    `.trim();

    await drupal.drush(cmd);
    await this.page.goto(`${config.pageViewUrl.replace('{instance_id}', `test_${id}`)}`)
    await this.shoelaceReady()
  }

  /**
   * Manually drags a component to a slot using mouse events.
   *
   * @param {Locator} component - The Playwright Locator for the component to be dragged.
   * @param {Locator} slot - The Playwright Locator for the target slot where the component should be dropped.
   * @returns A Promise that resolves when the drag-and-drop action is complete.
   */
  async dragManual(component: Locator, slot: Locator): Promise<void> {
    await component.hover({ position: { x: 10, y: 10 }, force: true })
    await this.page.mouse.down()
    await slot.hover({ position: { x: 10, y: 10 }, force: true })
    await this.page.mouse.up()
    await await this.htmxReady()
  }
}
