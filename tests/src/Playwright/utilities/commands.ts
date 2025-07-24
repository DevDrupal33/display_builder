import { expect, type Page, type BrowserContext } from '@playwright/test';
import { execSync } from 'child_process';

import dbConfig from '../playwright.db.config';

/**
 * Opens the Libraries drawer in the Display Builder UI.
 *
 * Waits for the Libraries button to be visible, clicks it,
 * and verifies the drawer is open.
 *
 * @async
 * @param {Page} page - Playwright Page object.
 * @returns {Promise<void>}
 */
export async function openLibraries(page: Page): Promise<void> {
  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible();
  await page.getByRole('button', { name: 'Libraries' }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
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
  await page.locator('select[name="display_builder_id"]').selectOption('test')
  // @todo select a fixture
  if (fixture) {
    await page.locator('select[name="fixture_id"]').selectOption(fixture)
  }
  await page.getByRole('button', { name: 'Save' }).click()
  await expect(page.getByRole('heading', { name: `Display builder: ${dbName}` })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Builder' })).toBeVisible()
}