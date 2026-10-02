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
  expect(await desktopNavigation.getByRole('link').count()).toBeGreaterThan(0);
  await expect(page.locator('.nav-account').getByRole('link', { name: 'Mon compte' })).toBeVisible();
  await expect(page.locator('.nav-account').getByRole('link', { name: /Ajouter un restaurant/ })).toBeVisible();
  await expect(header.locator('input[type="search"], [aria-label*="recherche" i], [aria-label*="search" i]')).toHaveCount(0);
  await expect(page.locator('.site-footer')).toBeVisible();
  await expect(page.locator('.site-footer').getByRole('link', { name: 'Restaurants' })).toBeVisible();
  const footerBottom = page.locator('.footer-bottom');
  await expect(footerBottom.locator('.footer-copyright')).toBeVisible();
  await expect(footerBottom).toHaveCSS('justify-content', 'space-between');
  const legalLinks = footerBottom.locator('.footer-legal .nav-menu-list');
  if (await legalLinks.count()) {
    await expect(legalLinks).toHaveCSS('display', 'flex');
    await expect(legalLinks).toHaveCSS('flex-wrap', 'wrap');
    const legalLink = legalLinks.getByRole('link').first();
    await expect(legalLink).toHaveCSS('font-weight', '400');
    await expect(legalLink).toHaveCSS('text-decoration-line', 'none');
  }
  expect(errors).toEqual([]); expect(failures).toEqual([]);
});

test('mobile navigation opens, closes with Escape, and does not overflow horizontally', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium', 'Mobile-only navigation assertions.');
  const errors: string[] = []; const failures: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failures.push(request.url()));

  await page.goto('/');
  const toggle = page.locator('.menu-toggle');
  await expect(toggle).toBeVisible();
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#mobile-nav').getByRole('link', { name: 'Restaurants' })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#mobile-nav')).toBeHidden();
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await expect(page.locator('.site-footer')).toBeVisible();
  await expect(page.locator('.footer-bottom .footer-copyright')).toBeVisible();
  const legalLinks = page.locator('.footer-bottom .footer-legal .nav-menu-list');
  if (await legalLinks.count()) {
    await expect(legalLinks).toHaveCSS('display', 'flex');
    await expect(legalLinks).toHaveCSS('flex-wrap', 'wrap');
    const legalLink = legalLinks.getByRole('link').first();
    await expect(legalLink).toHaveCSS('font-weight', '400');
    await expect(legalLink).toHaveCSS('text-decoration-line', 'none');
  }
  expect(errors).toEqual([]); expect(failures).toEqual([]);
});

test('mobile navigation exposes linked submenus through independent chevrons', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium', 'Mobile-only submenu interaction.');
  const errors: string[] = []; const failures: string[] = [];
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
  page.on('requestfailed', request => failures.push(request.url()));

  await page.goto('/');
  const menuToggle = page.locator('.menu-toggle');
  await menuToggle.click();
  await expect(menuToggle).toHaveAttribute('aria-label', 'Fermer le menu');
  await expect(page.locator('body')).toHaveClass(/mobile-menu-open/);

  const owner = page.locator('#mobile-nav .has-submenu:has(> a)').first();
  const parentLink = owner.locator(':scope > a.nav-item');
  const submenuToggle = owner.locator(':scope > button.submenu-toggle');
  const submenu = owner.locator(':scope > .submenu');
  await expect(parentLink).toHaveAttribute('href', /\S+/);
  await expect(submenuToggle).toHaveAttribute('aria-expanded', 'false');
  await submenuToggle.focus();
  await submenuToggle.press('Enter');
  await expect(submenuToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(submenu).toBeVisible();
  await expect(submenu.getByRole('link').first()).toBeVisible();

  const otherOwner = page.locator('#mobile-nav .has-submenu').nth(1);
  if (await otherOwner.count()) {
    const otherToggle = otherOwner.locator(':scope > [data-submenu-toggle]').first();
    await otherToggle.click();
    await expect(submenu).toBeHidden();
    await expect(otherOwner.locator(':scope > .submenu')).toBeVisible();
  }

  await page.keyboard.press('Escape');
  await expect(page.locator('#mobile-nav')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.locator('#mobile-nav')).toBeHidden();
  await expect(menuToggle).toBeFocused();
  await expect(page.locator('body')).not.toHaveClass(/mobile-menu-open/);
  expect(errors).toEqual([]); expect(failures).toEqual([]);
});

test('mobile submenu parent and child destinations remain independently navigable SSR links', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium', 'Mobile-only linked submenu destinations.');

  await page.goto('/');
  await page.locator('.menu-toggle').click();
  const owner = page.locator('#mobile-nav .has-submenu:has(> a)').first();
  const parentLink = owner.locator(':scope > a.nav-item');
  const parentHref = await parentLink.getAttribute('href');
  expect(parentHref).toBeTruthy();
  await Promise.all([page.waitForURL(new RegExp(`${parentHref!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)), parentLink.click()]);

  await page.goto('/');
  await page.locator('.menu-toggle').click();
  const childOwner = page.locator('#mobile-nav .has-submenu:has(> a)').first();
  await childOwner.locator(':scope > button.submenu-toggle').click();
  const child = childOwner.locator(':scope > .submenu a').first();
  const childHref = await child.getAttribute('href');
  expect(childHref).toBeTruthy();
  await Promise.all([page.waitForURL(new RegExp(`${childHref!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`)), child.click()]);
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
