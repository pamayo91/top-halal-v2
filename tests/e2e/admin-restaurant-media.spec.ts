import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('Médias restaurant Filament', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('the restaurant media tab shows whole-image previews and explicit gallery controls', async ({ page }) => {
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
    await page.getByRole('tab', { name: 'Médias' }).click();

    await expect(page.getByText(/Ajoutez des photos avec le bouton/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Ajouter des photos' })).toBeVisible();
    await expect(page.getByText('Couverture · position 1')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Monter' }).first()).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Descendre' }).first()).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Retirer' }).first()).toBeVisible();
    const preview = page.locator('img.object-contain').first();
    await expect(preview).toBeVisible();
    await expect(preview).toHaveAttribute('width', '480');
    await expect(preview).toHaveAttribute('height', '270');
    expect(errors).toEqual([]);
  });
});
