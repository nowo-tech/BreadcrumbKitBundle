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
/** One cropped widget image for docs/USAGE.md (REQ-DEMO-013 §3), kept out of the README set under docs/images/demo/. */
const usageDir = process.env.USAGE_SCREENSHOT_DIR ? resolve(process.env.USAGE_SCREENSHOT_DIR) : resolve(__dirname, '../../../../docs/images/usage');
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
  mkdirSync(usageDir, { recursive: true });
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

test.describe('usage screenshots', () => {
  test.use({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });

  test('collapsed trail at phone width with the previous pages menu open', async ({ page }) => {
    await page.goto('/en/demo/deep/s1/s2/s3/s4/s5/s6');
    const trail = page.locator('.breadcrumb-kit[data-breadcrumb-collapse]').first();
    await expect(trail).toBeVisible();
    await waitForToolbar(page);
    const toggle = trail.locator('.breadcrumb-collapse__toggle');
    await expect(toggle).toBeVisible();
    await toggle.click();
    const menu = trail.locator('.breadcrumb-collapse__menu');
    await expect(menu).toBeVisible();
    await expect(menu.locator('.breadcrumb-collapse__entry')).toHaveCount(7);
    // The menu fades in (120 ms keyframes): capture it once the animation has finished, fully opaque.
    await menu.evaluate((el) => Promise.all(el.getAnimations().map((a) => a.finished)));
    const box = await trail.boundingBox();
    const menuBox = await menu.boundingBox();
    expect(box).not.toBeNull();
    expect(menuBox).not.toBeNull();
    const pad = 16;
    const x = Math.max(0, Math.min(box!.x, menuBox!.x) - pad);
    const y = Math.max(0, box!.y - pad);
    const right = Math.min(390, Math.max(box!.x + box!.width, menuBox!.x + menuBox!.width) + pad);
    const bottom = Math.max(box!.y + box!.height, menuBox!.y + menuBox!.height) + pad;
    await page.screenshot({ path: resolve(usageDir, 'breadcrumb-collapse.png'), clip: { x, y, width: right - x, height: bottom - y } });
  });
});
