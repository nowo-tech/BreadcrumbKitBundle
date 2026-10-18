import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 / REQ-UI-003 — the Breadcrumb Kit dashboard on the UiKit page contract: lists, dialogs (forms loaded
 * into UiKit dialogs), full-page forms, the confirm dialog and the no-JS confirm page, rendered inside the demo host
 * layout (nowo_ui_kit.layouts.admin) with the BreadcrumbKit trail in the page header.
 */
const ADMIN = '/breadcrumb-kit-admin';

/** Opens the items list of the "default" collection (ids change when the fixtures are reloaded). */
async function defaultItemsUrl(page: import('@playwright/test').Page): Promise<string> {
  await page.goto('/breadcrumb-kit-admin/collections?q=default');
  await page.getByRole('link', { name: 'default', exact: true }).first().click();
  await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
  return new URL(page.url()).pathname;
}

test.describe('breadcrumb kit dashboard', () => {
  test('public page renders the breadcrumb trail', async ({ page }) => {
    const response = await page.goto('/en/shop');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('.breadcrumb-kit nav[aria-label="Breadcrumb"]').first()).toBeVisible();
  });

  test('collections list uses the UiKit list scaffold and the BreadcrumbKit trail', async ({ page }) => {
    await page.goto(`${ADMIN}/collections`);
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: 'Collections' })).toBeVisible();
    await expect(page.locator('[data-nowo-ui-region="header"] .breadcrumb-kit')).toContainText('Breadcrumb collections');
    await expect(page.locator('tbody tr', { hasText: 'default' })).toBeVisible();
  });

  test('new collection form opens in a UiKit dialog and saves', async ({ page }) => {
    const code = `e2e-${Date.now()}`;
    await page.goto(`${ADMIN}/collections`);
    await page.getByTestId('bk-collection-new').click();
    const dialog = page.locator('#bk-collection-form-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('input[name="breadcrumb_collection[code]"]')).toBeVisible();
    await dialog.locator('input[name="breadcrumb_collection[code]"]').fill(code);
    await dialog.getByRole('button', { name: 'Create' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Collection created.' })).toBeVisible();
    await page.goto(`${ADMIN}/collections?q=${code}`);
    await expect(page.locator('tbody tr', { hasText: code })).toBeVisible();
  });

  test('collection delete goes through the UiKit confirm dialog', async ({ page }) => {
    const code = `e2e-del-${Date.now()}`;
    await page.goto(`${ADMIN}/collections/new`);
    await expect(page.locator('[data-nowo-ui-page="form"]')).toBeVisible();
    await page.locator('input[name="breadcrumb_collection[code]"]').fill(code);
    await page.getByRole('button', { name: 'Create' }).click();

    await page.goto(`${ADMIN}/collections?q=${code}`);
    const row = page.locator('tbody tr', { hasText: code });
    await row.getByRole('button', { name: 'Delete' }).click();
    const dialog = page.locator('dialog[open]');
    await expect(dialog).toContainText(`Delete collection “${code}” and all its items?`);
    await dialog.getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Collection deleted.' })).toBeVisible();
    await expect(page.locator('tbody tr', { hasText: code })).toHaveCount(0);
  });

  test('item delete has a no-JS confirm page (POST with CSRF)', async ({ page }) => {
    const items = await defaultItemsUrl(page);
    await page.goto(`${items}/new`);
    const route = `app_e2e_${Date.now()}`;
    await page.locator('input[name="breadcrumb_item[routeName]"]').fill(route);
    await page.getByRole('button', { name: 'Create' }).click();
    await page.goto(`${items}?q=${route}`);
    const href = await page.locator('tbody tr', { hasText: route }).locator('noscript').first().innerHTML();
    const url = /href="([^"]+)"/.exec(href)?.[1] ?? '';
    expect(url).toMatch(/\/items\/\d+\/delete$/);

    await page.goto(url.replace(/&amp;/g, '&'));
    await expect(page.locator('[data-nowo-ui-page="confirm"]')).toBeVisible();
    await page.getByTestId('breadcrumb-kit-item-delete').getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'Item deleted.' })).toBeVisible();
  });

  test('items list, filters and the import dialog', async ({ page }) => {
    await defaultItemsUrl(page);
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await expect(page.getByRole('cell', { name: 'app_shop', exact: true })).toBeVisible();
    await page.locator('input[name="q"]').fill('app_shop');
    await page.getByRole('button', { name: 'Filter' }).click();
    await expect(page.locator('caption')).toContainText('result(s) for “app_shop”');

    await page.goto(`${ADMIN}/collections`);
    await page.getByTestId('bk-import-open').click();
    await expect(page.getByTestId('bk-import-dialog')).toBeVisible();
    await expect(page.getByTestId('bk-import-dialog').locator('input[type="file"]')).toBeVisible();
  });
});
