import { expect, type Page, type BrowserContext, Locator } from '@playwright/test';

import dbConfig from '../playwright.db.config';

/**
 * Toggles the sidebar first drawer in the Display Builder UI.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {string} [targetId='library'] - The target ID for the toolbar button. Default is the Libraries button.
 * @returns {Promise<void>}
 */
export async function toggleSidebarView(page: Page, targetId: string = 'library'): Promise<void> {
  const sidebarFirst = page.locator('#db-first-drawer');
  const toolbarButton = page.locator(`.db-toolbar__start [data-target="${targetId}"]`);
  await expect(toolbarButton).toBeVisible();

  if (await sidebarFirst.isVisible()) {
    await toolbarButton.click();
    await expect(sidebarFirst).toBeHidden();
  } else {
    await toolbarButton.click();
    await expect(sidebarFirst).toBeVisible();
  }
}

/**
 * Opens the Libraries drawer and blocks in the Display Builder UI.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @returns {Promise<void>}
 */
export async function openLibrariesTab(page: Page, name: string = 'Blocks'): Promise<void> {
  const sidebarFirst = page.locator('#db-first-drawer');
  if (await sidebarFirst.isHidden()) {
    await toggleSidebarView(page);
  }
  await expect(
    sidebarFirst.getByRole('tab', { name, exact: true })
  ).toBeVisible()
  await sidebarFirst
    .getByRole('tab', { name, exact: true })
    .locator('div')
    .click()

  await this.builderIsReady(page)
}

/**
 * Move a component in the builder, library must be open.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {Locator} element - The element to drag to the target.
 * @param {Locator} target - The target where the component must be dragged.
 * @returns {Promise<void>}
 */
export async function dragElementFromLibraryById(page: Page, type: string = 'Components', id: string, target: Locator): Promise<void> {
  const element = page.locator(`.db-island-library [hx-vals*="${id}"]`).first()
  await this.dragElementFromLibrary(page, type, element, target)
}

/**
 * Move a component in the builder, library must be open.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {Locator} element - The element to drag to the target.
 * @param {Locator} target - The target where the component must be dragged.
 * @returns {Promise<void>}
 */
export async function dragElementFromLibrary(page: Page, type: string = 'Components', element: Locator, target: Locator): Promise<void> {
  await this.openLibrariesTab(page, type)
  await this.dragElement(page, element, target)
}

/**
 * Move a component in the builder, library must be open.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {Locator} element - The element to drag to the target.
 * @param {Locator} target - The target where the component must be dragged.
 * @returns {Promise<void>}
 */
export async function dragElement(page: Page, element: Locator, target: Locator): Promise<void> {
  await this.builderIsReady(page)

  await expect(target).toBeVisible()
  await expect(element).toBeVisible()

  // Js step by step drag.
  // await component.hover({ position: { x: 10, y: 10 } })
  // await expect(page.locator('.display-builder')).toContainClass('display-builder--onDrag')
  // await page.mouse.down()
  // await targetSlot.hover({ position: { x: 10, y: 10 } })
  // await page.mouse.up()
  // await expect(page.locator('.display-builder')).not.toContainClass('display-builder--onDrag')

  await element.dragTo(target, {
    force: true,
    targetPosition: {
      x: 10,
      y: 10,
    },
  });

  await this.builderIsReady(page)
}

/**
 * Drags a token block into a target slot and sets its value in a Playwright test.
 *
 * @async
 * @param {Page} page - The Playwright Page object representing the browser page.
 * @param {Locator} targetSlot - The Locator for the slot where the token should be dropped.
 * @param {string} value - The string value to set for the token in the settings dialog.
 * @returns {Promise<void>}
 */
export async function setElementValue(
  page: Page,
  element: Locator,
  value: string,
  valuePath?: Array<{ action: 'click' | 'fill'; locator: Locator }>
): Promise<void> {
  await expect(element).toBeVisible();

  await element.click();
  await this.builderIsReady(page);

  await expect(page.getByRole('dialog', { name: 'Settings' })).toBeVisible();

  if (valuePath && Array.isArray(valuePath)) {
    for (const step of valuePath) {
      await expect(step.locator).toBeVisible();
      if (step.action === 'click') {
        await step.locator.click();
      } else if (step.action === 'fill') {
        await step.locator.fill(value);
      }
    }
  } else {
    await page.locator('#edit-value').fill(value);
  }

  await page.getByRole('button', { name: 'Update' }).click();

  await this.builderIsReady(page);
}

/**
 * Saves the current state in the Display Builder from the UI
 *
 * @async
 * @param {Page} page - The Playwright Page object representing the browser page.
 * @returns {Promise<void>}
 */
export async function saveDisplayBuilder(page: Page): Promise<void> {
  await this.builderIsReady(page)
  await page.getByRole('button', { name: 'Save' }).click()
  await this.builderIsReady(page)
}

/**
 * Closes a specified drawer in the Display Builder UI.
 *
 * Locates the drawer by ID and clicks the Close button.
 * Verifies the drawer is hidden.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {string} [targetDrawer='first'] - Drawer identifier (default: 'first').
 * @returns {Promise<void>}
 */
export async function closeDialog(page: Page, targetDrawer: string = 'first'): Promise<void> {
  const drawer = page.locator(`#db-${targetDrawer}-drawer`);
  if (!drawer) {
    return;
  }
  await drawer.getByRole('button', { name: 'Close' }).click();
  await expect(drawer).toBeHidden();
}

/**
 * Waits for Drupal ajax requests and transitions to complete on the page.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @returns {Promise<void>}
 */
export async function ajaxReady(page: Page): Promise<void> {
  await expect(
    page.locator('.ajax-progress, .ajax-progress--throbber, .ajax-progress--message'),
  ).toHaveCount(0);
}

/**
 * Waits for all HTMX requests and transitions to complete on the page.
 *
 * Ensures there are no active HTMX request or transition elements.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @returns {Promise<void>}
 */
export async function htmxReady(page: Page): Promise<void> {
  await expect(
    page.locator('.htmx-request, .htmx-settling, .htmx-swapping, .htmx-added'),
  ).toHaveCount(0);
}

/**
 * Waits for WebComponents to be loaded and ready.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @returns {Promise<void>}
 */
export async function shoelaceReady(page: Page): Promise<void> {
  await page.addScriptTag({
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
 * Waits for builder to be loaded and ready.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @returns {Promise<void>}
 */
export async function builderIsReady(page: Page): Promise<void> {
  await shoelaceReady(page);
  await htmxReady(page);
}

/**
 * Refreshes the Display Builder instance view page.
 *
 * Navigates to the view page for the specified Display Builder instance.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {string} dbName - Name of the Display Builder instance.
 * @returns {Promise<void>}
 */
export async function refresh(page: Page, dbName: string): Promise<void> {
  await page.goto(dbConfig.dbViewUrl.replace('{db_id}', dbName));
  await this.builderIsReady(page)
}

/**
 * Create a Display Builder instance.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @param {string} dbName - Name of the Display Builder instance.
 * @param {string|null} fixture - (@todo) Name of the Display Builder fixture.
 * @returns {Promise<void>}
 */
export async function createDisplayBuilderFromUi(page: Page, dbName: string, fixture: string|null = null): Promise<void> {
  await page.goto(dbConfig.dbAddUrl)
  await page.getByRole('textbox', { name: 'Builder ID' }).fill(dbName)
  await page.locator('select[name="display_builder"]').selectOption('test')
  // @todo select a fixture
  if (fixture) {
    await page.locator('select[name="fixture_id"]').selectOption(fixture)
  }
  await page.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByRole('heading', { name: `Display builder: ${dbName}` })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Builder' })).toBeVisible()
  await this.shoelaceReady(page)
}
