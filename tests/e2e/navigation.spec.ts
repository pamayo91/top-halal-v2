import { expect, test } from '@playwright/test';

const adminEmail = process.env.PREPROD_ADMIN_EMAIL;
const adminPassword = process.env.PREPROD_ADMIN_PASSWORD;

test('desktop navigation is SSR, has no search control, and keeps the account CTAs', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium', 'Desktop-only header assertions.');
  const errors: string[] = []; const failures: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failures.push(request.url()));

  await page.goto('/');
  const header = page.locator('.site-header'); const desktopNavigation = page.locator('.main-nav');
  await expect(desktopNavigation.getByRole('link', { name: 'Restaurants' })).toBeVisible();
  for (const label of ['Villes', 'Cuisines', 'Guides', 'Blog']) await expect(desktopNavigation.getByText(label, { exact: true })).toBeVisible();
  await expect(page.locator('.nav-account').getByRole('link', { name: 'Mon compte' })).toBeVisible();
  await expect(page.locator('.nav-account').getByRole('link', { name: /Ajouter un restaurant/ })).toBeVisible();
  await expect(header.locator('input[type="search"], [aria-label*="recherche" i], [aria-label*="search" i]')).toHaveCount(0);
  await expect(page.locator('.site-footer')).toBeVisible();
  await expect(page.locator('.site-footer').getByRole('link', { name: 'Restaurants' })).toBeVisible();
  expect(errors).toEqual([]); expect(failures).toEqual([]);
});

test('mobile navigation opens, closes with Escape, and does not overflow horizontally', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium', 'Mobile-only navigation assertions.');
  const errors: string[] = []; const failures: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failures.push(request.url()));

  await page.goto('/');
  const toggle = page.getByRole('button', { name: 'Menu' });
  await expect(toggle).toBeVisible();
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#mobile-nav').getByRole('link', { name: 'Restaurants' })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#mobile-nav')).toBeHidden();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await expect(page.locator('.site-footer')).toBeVisible();
  expect(errors).toEqual([]); expect(failures).toEqual([]);
});

test('navigation back office groups Menus, Header and Footer without browser errors', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop-chromium' || !adminEmail || !adminPassword, 'Dedicated preproduction administrator required.');
  const errors: string[] = []; const failures: string[] = [];
  page.on('console', message => { if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text()); });
  page.on('requestfailed', request => failures.push(request.url()));

  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(adminEmail!);
  await page.locator('input[type="password"]').fill(adminPassword!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
  for (const [path, heading] of [['/admin/menus', 'Menus'], ['/admin/navigation/header', 'Header'], ['/admin/navigation/footer', 'Footer']]) {
    const response = await page.goto(path);
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
  }
  expect(errors).toEqual([]); expect(failures).toEqual([]);
});
