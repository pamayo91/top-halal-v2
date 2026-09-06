import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Réglages du maillage local', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the global radius and maximum are visible, persist, then restore their original values', async ({ page }) => {
    const consoleErrors: string[] = [];
    const networkErrors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => networkErrors.push(`${request.method()} ${request.url()}`));
    page.on('response', (response) => {
      if (response.status() >= 500) networkErrors.push(`${response.status()} ${response.url()}`);
    });

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/settings');

    const radius = page.getByLabel('Rayon des villes aux alentours (km)', { exact: true });
    const maximum = page.getByLabel('Nombre maximum de villes proches', { exact: true });
    await expect(radius).toBeVisible();
    await expect(maximum).toBeVisible();
    const originalRadius = await radius.inputValue();
    const originalMaximum = await maximum.inputValue();
    const temporaryRadius = String(originalRadius === '250' ? 249 : Number(originalRadius) + 1);

    try {
      await radius.fill(temporaryRadius);
      await page.getByRole('button', { name: 'Enregistrer', exact: true }).click();
      await expect(page.getByText('Réglages enregistrés', { exact: true })).toBeVisible();
      await expect(radius).toHaveValue(temporaryRadius);
    } finally {
      await radius.fill(originalRadius);
      await maximum.fill(originalMaximum);
      await page.getByRole('button', { name: 'Enregistrer', exact: true }).click();
      await expect(radius).toHaveValue(originalRadius);
      await expect(maximum).toHaveValue(originalMaximum);
    }

    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });
});
