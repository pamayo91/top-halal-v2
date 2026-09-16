import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Horaires restaurant Filament', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the restaurant editor exposes the seven-day hours tab without browser errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
      if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
    });
    page.on('requestfailed', (request) => errors.push(`${request.method()} ${request.url()}`));

    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.goto('/admin/restaurants/7699/edit');
    await page.getByRole('tab', { name: 'Horaires' }).click();

    await expect(page.getByText('Horaires d’ouverture')).toBeVisible();
    await expect(page.getByText('Lundi')).toBeVisible();
    await expect(page.getByText('Dimanche')).toBeVisible();
    await expect(page.getByRole('button', { name: /Enregistrer/ })).toBeVisible();
    expect(errors).toEqual([]);
  });
});
