import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Email templates administration', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('shows the functional name of every transactional template', async ({ page }) => {
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

    const response = await page.goto('/admin/email-templates');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Email Templates', exact: true })).toBeVisible();

    for (const name of [
      'Vérification e-mail',
      'Réinitialisation du mot de passe',
      'Mot de passe modifié',
      'Revendication reçue',
      'Revendication acceptée',
      'Revendication refusée',
      'Nouveau message de contact',
      'Accusé de réception Contact',
    ]) {
      await expect(page.getByRole('table')).toContainText(name);
    }

    await page.getByRole('button', { name: 'Aperçu' }).first().click();
    const preview = page.getByRole('dialog');
    await expect(preview).toBeVisible();
    await expect(preview).toContainText('Top Halal');
    await expect(preview).toContainText('Exemple');
    await expect(preview.locator('table[width="600"]')).toBeVisible();
    await expect(preview).not.toContainText('\\n');

    expect(consoleErrors).toEqual([]);
    expect(failedRequests).toEqual([]);
  });
});
