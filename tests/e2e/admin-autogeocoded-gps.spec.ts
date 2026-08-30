import { expect, test } from '@playwright/test';

const email = process.env.PREPROD_ADMIN_EMAIL;
const password = process.env.PREPROD_ADMIN_PASSWORD;

test.describe('GPS auto-géocodés Filament', () => {
  test.skip(!email || !password, 'PREPROD_ADMIN_EMAIL and PREPROD_ADMIN_PASSWORD are required.');

  test('Fresh Burritos and an automatically geocoded restaurant centre the marker on stored coordinates', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => { if (message.type() === 'error') errors.push(message.text()); });
    await page.goto('/admin');
    await page.getByLabel(/Adresse e-mail/).fill(email!);
    await page.getByLabel(/Mot de passe/).fill(password!);
    await page.getByRole('button', { name: 'Connexion' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    for (const id of [7641, 7698]) {
      await page.goto(`/admin/restaurants/${id}/edit`);
      await page.getByRole('tab', { name: 'Localisation' }).click();
      expect(errors).toEqual([]);
      await expect(page.locator('[data-top-halal-location-map] .leaflet-marker-icon')).toBeVisible();
    }
    expect(errors).toEqual([]);
  });
});
