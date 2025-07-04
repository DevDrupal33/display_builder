// @ts-check
import { test, expect } from '@playwright/test';

import * as dbCommands from '../utils/db_commands';
import * as dbUtilities from '../utils/db_utilities';

// import playwrightConfig from '../playwright.config';

import dbConfig from '../playwright.db.config';

// Standard accounts that use user accounts created
// by QA Accounts.
import qaUserAccounts from '../data/qaUsers.json';

test('Create Display Builder from UI', async ({ page, context }) => {
  const dbName = `test_${dbUtilities.createRandomString(6)}`;

  dbCommands.createUserWithUserObject(qaUserAccounts.admin, []);
  await dbCommands.logInViaForm(page, context, qaUserAccounts.admin);

  await page.goto(dbConfig.dbAddUrl);
  await page.getByRole('textbox', { name: 'Builder ID *' }).fill(dbName);
  await page.getByRole('button', { name: 'Save' }).click();

  await expect(page.getByText(`Display builder: ${dbName}`)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible();
  await expect(page.getByRole('tab', { name: 'Builder' })).toBeVisible();

  await page.goto(dbConfig.dbList);
  await expect(page.getByRole('link', { name: dbName })).toBeVisible();

  await page.goto(dbConfig.dbDeleteAllUrl);
  await page.getByRole('button', { name: 'Confirm' }).click();
});

test('Use Display Builder libraries and component', async ({
  page,
  context,
}) => {
  const name = `test_${dbUtilities.createRandomString(6)}`;
  dbCommands.createDisplayBuilderFromUi(name, 'devel', null, page, context);

  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible();

  // Open library and check component
  await expect(page.getByRole('button', { name: 'Libraries' })).toBeVisible();
  await page.getByRole('button', { name: 'Libraries' }).click();

  await expect(
    page.getByRole('button', { name: 'DB test component' }),
  ).toBeVisible();

  await page.getByRole('tab', { name: 'Variants' }).locator('div').click();
  await expect(page.getByRole('button', { name: 'Default' })).toBeVisible();

  // await page.getByRole('tab', { name: 'Mosaic' }).locator('div').click();
  // await expect(
  //   page
  //     .locator(`#db-${name}-components-tab---mosaic slot`)
  //     .filter({ hasText: 'DB test component' }),
  // ).toBeVisible();

  // Back to first tab.
  await page.getByRole('tab', { name: 'Grouped' }).locator('div').click();

  // Test preview with hover
  await page.getByRole('button', { name: 'DB test component' }).hover();
  await expect(page.getByRole('tooltip')).toBeVisible();

  // Move component to builder.
  await page.mouse.down();
  await page.locator(`#island-${name}-builder slot`).hover();
  await page.mouse.up();

  await page.getByRole('tab', { name: 'Layers' }).locator('slot').click();
  await page.getByRole('tab', { name: 'Preview' }).locator('slot').click();
});
