import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;
const testRecipient = process.env.PREPROD_SMTP_TEST_RECIPIENT;

async function signIn(page: import('@playwright/test').Page) {
  await page.goto('/admin');
  await page.locator('input[type="email"]').fill(email!);
  await page.locator('input[type="password"]').fill(password!);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/admin$/);
}

test.describe('SMTP configuration administration', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('offers the SMTP test action without browser errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) consoleErrors.push(message.text());
    });
    page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()}`));

    await signIn(page);
    const response = await page.goto('/admin/email-settings');
    expect(response?.status()).toBe(200);
    await page.getByRole('button', { name: 'Envoyer un e-mail de test' }).click();
    await expect(page.getByRole('dialog')).toContainText('Envoyer un e-mail de test');
    await expect(page.getByRole('dialog').locator('input[type="email"]')).toBeVisible();

    expect(consoleErrors).toEqual([]);
    expect(failedRequests).toEqual([]);
  });

  test('sends an immediate SMTP test only when an explicit recipient is configured', async ({ page }) => {
    test.skip(!testRecipient, 'PREPROD_SMTP_TEST_RECIPIENT is required for a real SMTP delivery test.');

    await signIn(page);
    await page.goto('/admin/email-settings');
    await page.getByRole('button', { name: 'Envoyer un e-mail de test' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.locator('input[type="email"]').fill(testRecipient!);
    await dialog.getByRole('button', { name: 'Soumettre' }).click();
    await expect(page.getByText('E-mail de test envoyé avec succès')).toBeVisible();
  });
});
