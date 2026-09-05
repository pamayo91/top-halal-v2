import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Gestion des utilisateurs', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('an administrator can open the user creation form and select the administrator role', async ({ page }) => {
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

    await page.goto('/admin/users');
    await page.getByRole('link', { name: 'Ajouter un utilisateur' }).click();
    await expect(page).toHaveURL(/\/admin\/users\/create$/);
    await expect(page.getByLabel('Nom')).toBeVisible();
    await expect(page.getByLabel('E-mail')).toBeVisible();
    await expect(page.getByLabel('Mot de passe initial')).toBeVisible();
    const role = page.getByLabel('Rôle');
    await expect(role).toBeVisible();
    await role.selectOption('admin');
    await expect(role).toHaveValue('admin');
    await expect(page.getByText('Forcer le changement de mot de passe', { exact: true })).toBeVisible();
    expect(consoleErrors).toEqual([]);
    expect(networkErrors).toEqual([]);
  });
});
