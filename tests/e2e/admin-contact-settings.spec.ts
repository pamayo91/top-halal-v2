import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Réglages Contact', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('only exposes settings that affect the contact workflow', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
    });
    page.on('response', (response) => {
      if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`);
    });

    await page.goto('/admin');
    await page.locator('input[type="email"]').fill(email!);
    await page.locator('input[type="password"]').fill(password!);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/\/admin$/);

    const response = await page.goto('/admin/contact-settings');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('textbox', { name: 'Destinataire unique' })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Message après envoi' })).toBeVisible();
    await expect(page.getByText('Texte d’introduction', { exact: true })).toHaveCount(0);

    expect(errors).toEqual([]);
  });
});
