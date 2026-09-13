import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Demandes de suppression de restaurant', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the moderation queue is accessible without browser failures', async ({ page }) => {
    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];

    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()}`));

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    const response = await page.goto('/admin/restaurant-removal-requests');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: /Demandes de suppression/i }).first()).toBeVisible();
    await expect(page.getByRole('table')).toBeVisible();
    await expect(page.getByText('En attente', { exact: true }).first()).toBeVisible();

    expect(consoleErrors).toEqual([]);
    expect(failedRequests).toEqual([]);
  });
});
