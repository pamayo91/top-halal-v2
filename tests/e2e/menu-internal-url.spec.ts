import { expect, test } from '@playwright/test';

const adminEmail = process.env.PREPROD_ADMIN_EMAIL;
const adminPassword = process.env.PREPROD_ADMIN_PASSWORD;

test('menu editor accepts relative and same-site absolute internal URLs', async ({ page }) => {
  test.skip(!adminEmail || !adminPassword, 'Dedicated preproduction administrator required.');

  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(adminEmail!);
  await page.locator('input[type="password"]').fill(adminPassword!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
  await page.goto('/admin/menus');
  await page.getByRole('link', { name: 'Header principal', exact: true }).click();
  await page.getByRole('button', { name: /Ajouter un élément/ }).click();
  await page.locator('.menu-editor__modal select').selectOption('internal_url');

  const url = page.locator('input[inputmode="url"]');
  await expect(url).toHaveAttribute('type', 'text');

  await url.fill('/quick-hallal');
  expect(await url.evaluate((input: HTMLInputElement) => input.checkValidity())).toBe(true);
  await url.fill('https://dev.top-halal.fr/quick-hallal');
  expect(await url.evaluate((input: HTMLInputElement) => input.checkValidity())).toBe(true);
});
