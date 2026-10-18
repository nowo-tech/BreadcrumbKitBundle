import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Page } from '@playwright/test';

/** The Symfony WebProfiler toolbar (dev) is part of the demo frame: wait until it has loaded. */
async function waitForToolbar(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  if (await page.locator('.sf-toolbar').count()) {
    await expect(page.locator('.sf-toolbar .sf-toolbar-block').first()).toBeVisible();
  }
}

/** REQ-DEMO-013 — the dashboard in the demo frame (demo header + sidebar + UiKit page + Symfony toolbar). */
const outDir = process.env.SCREENSHOT_DIR ? resolve(process.env.SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/demo');
const ADMIN = '/breadcrumb-kit-admin';

/** Opens the items list of the "default" collection (ids change when the fixtures are reloaded). */
async function defaultItemsUrl(page: import('@playwright/test').Page): Promise<string> {
  await page.goto('/breadcrumb-kit-admin/collections?q=default');
  await page.getByRole('link', { name: 'default', exact: true }).first().click();
  await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
  return new URL(page.url()).pathname;
}

test.use({ viewport: { width: 1440, height: 900 } });

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('dashboard screenshots', () => {
  test('collections list', async ({ page }) => {
    await page.goto(`${ADMIN}/collections`);
    await expect(page.locator('[data-nowo-ui-page="list"]')).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'dashboard-collections.png'), fullPage: false });
  });

  test('items list with the item form dialog', async ({ page }) => {
    await defaultItemsUrl(page);
    await page.getByRole('row').filter({ has: page.getByRole('cell', { name: 'app_shop', exact: true }) }).getByRole('link', { name: 'Edit' }).click();
    const dialog = page.locator('#bk-item-form-dialog');
    await expect(dialog.locator('input[name="breadcrumb_item[routeName]"]')).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'dashboard-item-dialog.png'), fullPage: false });
  });

  test('public page with the trail', async ({ page }) => {
    await page.goto('/en/product/42');
    await expect(page.locator('.breadcrumb-kit').first()).toBeVisible();
    await waitForToolbar(page);
    await page.screenshot({ path: resolve(outDir, 'public-trail.png'), fullPage: false });
  });
});
