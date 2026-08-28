import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Filament administration', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('admin dashboard, navigation and operational lists work without browser errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    const networkErrors: string[] = [];

    page.on('console', (message) => {
      if (message.type() === 'error') consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => networkErrors.push(`${request.method()} ${request.url()}`));

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(/\/admin$/);
    await expect(page.getByRole('heading', { name: 'Tableau de bord' })).toBeVisible();
    await expect(page.getByText('Restaurants', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Avis à modérer', { exact: true })).toBeVisible();

    for (const [path, heading] of [
      ['/admin/restaurants', 'Restaurants'],
      ['/admin/articles', 'Articles'],
      ['/admin/restaurant-reviews', 'Avis'],
      ['/admin/restaurant-claims', 'Claims'],
      ['/admin/settings', 'Réglages'],
      ['/admin/admin-audit-logs', "Journal d'audit"],
    ]) {
      const response = await page.goto(path);
      expect(response?.status()).toBe(200);
      await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    }

    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });
});
